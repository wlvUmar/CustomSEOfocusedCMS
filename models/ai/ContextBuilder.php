<?php
// path: ./models/ai/ContextBuilder.php
// Constructs model context: system (+ summary) + window + pending + user.
// Summarizes evicted turns via the same Opencode provider (low-cost).

require_once BASE_PATH . '/models/ai/PromptLoader.php';
require_once BASE_PATH . '/models/ai/AiSessionStore.php';

class ContextBuilder {

    /**
     * [static system prompt] + history + pending + [context block] + current user message.
     * The system message never changes between calls, so the provider's prompt cache can reuse it and the history
     * after it. The per-run context block (summary / memory / session state) is a run-internal message
     * (`_syn` = never persisted, `_ctx` = kept through trimming) placed just before the user's current message.
     *
     * Truth tiers (highest first — a lower tier never overrides a higher one):
     *   Tier 1: fresh tool results in the visible window (status/call_id/fresh_hash).
     *   Tier 2: summary ledger lines for writes with verified:true + fresh_hash.
     *   Tier 3: model text and unverified claims — background only, never cited as fact.
     * A user challenge ("you're hallucinating", "that's wrong") or a hash/status mismatch
     * always triggers a fresh read; an apology without re-reading is never acceptable.
     */
    public static function buildMessages(string $systemPrompt, string $contextBlock, array $historyWindow, array $pending, string $userMessage, bool $alreadyInHistory): array {
        $body = array_values($historyWindow);
        foreach ($pending as $p) $body[] = $p;
        // Fail-safe delivery: if the caller claims the message is already in
        // history but the last user turn doesn't actually match it, append —
        // dropping the user's words is worse than a duplicate.
        if ($alreadyInHistory) {
            $lastUser = null;
            for ($i = count($body) - 1; $i >= 0; $i--) {
                if (($body[$i]['role'] ?? '') === 'user' && empty($body[$i]['_syn'])) { $lastUser = $body[$i]; break; }
            }
            $norm = function (string $s): string {
                $s = str_replace("\r\n", "\n", $s);
                $s = preg_replace('/\s+/u', ' ', trim($s));
                return $s ?? '';
            };
            if ($lastUser === null || $norm((string)($lastUser['content'] ?? '')) !== $norm($userMessage)) {
                $alreadyInHistory = false;
            }
        }
        if (!$alreadyInHistory) $body[] = ['role' => 'user', 'content' => $userMessage];
        $contextBlock = trim($contextBlock);
        if ($contextBlock !== '') {
            $at = count($body);
            for ($i = count($body) - 1; $i >= 0; $i--) {
                if (($body[$i]['role'] ?? '') === 'user') { $at = $i; break; }
            }
            array_splice($body, $at, 0, [[
                'role' => 'user',
                'content' => "[SESSION CONTEXT — injected by the system, not written by the user. Use it as background; the user's actual request follows.]\n"
                    . "TRUTH TIERS (highest first; lower never overrides higher):\n"
                    . "1) fresh tool results in the visible window (check status/call_id/fresh_hash);\n"
                    . "2) summary DONE lines only when they show verified:true + fresh_hash;\n"
                    . "3) model text and anything tagged UNVERIFIED = background only, never cite as fact.\n"
                    . "If the user contests a claim, or status/hash disagrees with what you said: stop, re-read the fresh state, then correct. Never apologize-and-restate without a new read.\n"
                    . $contextBlock,
                '_syn' => true,
                '_ctx' => true,
            ]]);
        }
        return array_merge([['role' => 'system', 'content' => $systemPrompt]], $body);
    }

    /**
     * One compact line per event so the summarizer sees WHAT was done (tool, target, outcome).
     * Provenance-preserving: every line carries the tool-call id + status + key identifiers
     * (page/section/query/window) + outcome hash. The summary may only promote a write to
     * DONE when the result line shows verified:true with a fresh_hash; anything else stays
     * UNVERIFIED and must be re-read before it is cited or built upon.
     */
    private static function renderEvicted(array $evicted): string {
        $lines = [];
        foreach ($evicted as $m) {
            $role = $m['role'] ?? '';
            $content = trim((string)($m['content'] ?? ''));
            if ($role === 'user') {
                $lines[] = 'USER: ' . mb_substr($content, 0, 300);
            } elseif ($role === 'assistant') {
                if ($content !== '') $lines[] = 'ASSISTANT: ' . mb_substr($content, 0, 300);
                foreach (($m['tool_calls'] ?? []) as $tc) {
                    $fn = (string)($tc['function']['name'] ?? '?');
                    $cid = (string)($tc['id'] ?? '');
                    $args = json_decode((string)($tc['function']['arguments'] ?? ''), true);
                    $ident = self::callIdentifiers($fn, is_array($args) ? $args : []);
                    $lines[] = 'CALL ' . $fn . ($cid !== '' ? ' id=' . substr($cid, 0, 12) : '') . ($ident !== '' ? ' ' . $ident : '');
                }
            } elseif ($role === 'tool') {
                $d = json_decode($content, true);
                $res = '';
                if (is_array($d)) {
                    $bits = [];
                    // Status + provenance first: the summarizer must not promote
                    // applied_unverified / verification_failed to DONE.
                    foreach (['_status', 'status', 'ok', 'verified', 'code', 'fresh_hash', 'hash'] as $k) {
                        if (!isset($d[$k]) || !is_scalar($d[$k])) continue;
                        $v = is_bool($d[$k]) ? ($d[$k] ? 'true' : 'false') : mb_substr((string)$d[$k], 0, 60);
                        $bits[] = $k . '=' . $v;
                    }
                    foreach (['page_id', 'slug', 'page_slug', 'section', 'field', 'lang', 'faq_id', 'days', 'query', 'count', 'chars', 'after_chars', 'error', 'skipped'] as $k) {
                        if (!isset($d[$k]) || !is_scalar($d[$k])) continue;
                        $v = is_bool($d[$k]) ? ($d[$k] ? 'true' : 'false') : mb_substr((string)$d[$k], 0, 80);
                        if ($v === '' || $v === '0') continue;
                        $bits[] = $k . '=' . $v;
                    }
                    $res = implode(' ', $bits);
                }
                $lines[] = '  => ' . ($res !== '' ? $res : mb_substr($content, 0, 200));
            }
        }
        $text = implode("\n", $lines);
        return mb_strlen($text) > 8000 ? mb_substr($text, -8000) : $text;
    }

    /**
     * Stable identifiers per tool so a summary line stays re-readable without
     * re-fetching: page/section for content, query+window for GSC, slug for FAQs.
     */
    private static function callIdentifiers(string $fn, array $args): string {
        $pick = function (array $keys) use ($args) {
            $out = [];
            foreach ($keys as $k) {
                if (!isset($args[$k]) || !is_scalar($args[$k])) continue;
                $v = trim((string)$args[$k]);
                if ($v === '') continue;
                $out[] = $k . '=' . mb_substr(preg_replace('/\s+/u', ' ', $v), 0, 80);
            }
            return implode(' ', $out);
        };
        if (in_array($fn, ['get_section', 'patch_section', 'update_section', 'list_sections', 'get_content_chunk', 'insert_section'], true)) {
            return $pick(['page_id', 'slug', 'section', 'lang']);
        }
        if (in_array($fn, ['get_page', 'str_replace_field', 'set_field'], true)) {
            return $pick(['page_id', 'slug', 'field']);
        }
        if (in_array($fn, ['list_faqs', 'get_faq'], true)) {
            return $pick(['page_slug', 'faq_id']);
        }
        if (in_array($fn, ['update_faq', 'delete_faq', 'create_faq'], true)) {
            return $pick(['faq_id', 'page_slug']);
        }
        if (str_starts_with($fn, 'get_gsc') || str_starts_with($fn, 'query_gsc') || $fn === 'search_gsc_queries') {
            return $pick(['query', 'page', 'days', 'limit']);
        }
        if (in_array($fn, ['get_monthly_summary', 'get_site_overview', 'get_top_pages'], true)) {
            return $pick(['days']);
        }
        return $pick(['slug', 'page_id']);
    }

    /**
     * $messages is the full run list (system + loaded window + new turns). Whatever the turn-based
     * window drops from it is folded into the stored summary. Returns the complete updated summary.
     */
    public static function summarizeIfNeeded(string $sessionId, int $uid, array $messages): ?string {
        [$evicted] = AiSessionStore::splitWindow(AiSessionStore::historyFromMessages($messages));
        if (empty($evicted)) return null;
        try {
            $db = Database::getInstance();
            $row = $db->fetchOne("SELECT summary FROM ai_sessions WHERE id=? AND user_id=?", [$sessionId, $uid]);
            $prevSummary = $row['summary'] ?? '';
        } catch (Throwable $e) { $prevSummary = ''; }

        try {
            $prompt = PromptLoader::render('summarizer-user', [
                // render() throws on any leftover [[..]], so neutralise it in free text
                'prev_summary' => str_replace('[[', '[ [', mb_substr((string)$prevSummary, 0, 2000)),
                'evicted_turns' => str_replace('[[', '[ [', self::renderEvicted($evicted)),
            ]);
            $summarizerSystem = PromptLoader::load('summarizer-system');
        } catch (Throwable $e) {
            return null;
        }

        try {
            require_once BASE_PATH . '/models/Opencode.php';
            $resp = Opencode::chatWithTools(
                [['role'=>'system','content'=>$summarizerSystem], ['role'=>'user','content'=>$prompt]],
                Opencode::DEFAULT_MODEL,
                [],
                0.0,
                700,
                0,
                'auto',
                $sessionId
            );
            $summary = trim((string)($resp['content'] ?? ''));
            if ($summary === '') return null;
            // The model returns the full merged summary (previous one is in its input) — replace, never concatenate.
            return mb_strlen($summary) > 2000 ? mb_substr($summary, 0, 2000) : $summary;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function persistSummary(string $sessionId, int $uid, string $summary): void {
        try {
            Database::getInstance()->query("UPDATE ai_sessions SET summary=?, summary_updated_at=NOW() WHERE id=? AND user_id=?", [$summary, $sessionId, $uid]);
        } catch (Throwable $e) {}
    }

    public static function loadSummary(string $sessionId, int $uid): ?string {
        try {
            $row = Database::getInstance()->fetchOne("SELECT summary FROM ai_sessions WHERE id=? AND user_id=?", [$sessionId, $uid]);
            $s = $row['summary'] ?? null;
            return is_string($s) && trim($s) !== '' ? $s : null;
        } catch (Throwable $e) { return null; }
    }
}
