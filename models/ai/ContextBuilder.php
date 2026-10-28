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
     */
    public static function buildMessages(string $systemPrompt, string $contextBlock, array $historyWindow, array $pending, string $userMessage, bool $alreadyInHistory): array {
        $body = array_values($historyWindow);
        foreach ($pending as $p) $body[] = $p;
        if (!$alreadyInHistory) $body[] = ['role' => 'user', 'content' => $userMessage];
        $contextBlock = trim($contextBlock);
        if ($contextBlock !== '') {
            $at = count($body);
            for ($i = count($body) - 1; $i >= 0; $i--) {
                if (($body[$i]['role'] ?? '') === 'user') { $at = $i; break; }
            }
            array_splice($body, $at, 0, [[
                'role' => 'user',
                'content' => "[SESSION CONTEXT — injected by the system, not written by the user. Use it as background; the user's actual request follows.]\n" . $contextBlock,
                '_syn' => true,
                '_ctx' => true,
            ]]);
        }
        return array_merge([['role' => 'system', 'content' => $systemPrompt]], $body);
    }

    /**
     * One compact line per event so the summarizer sees WHAT was done (tool, target, outcome),
     * not "[tool_calls:2]" and a 500-char slice of JSON.
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
                    $args = preg_replace('/\s+/u', ' ', (string)($tc['function']['arguments'] ?? ''));
                    $lines[] = 'CALL ' . (string)($tc['function']['name'] ?? '?') . ' ' . mb_substr((string)$args, 0, 160);
                }
            } elseif ($role === 'tool') {
                $d = json_decode($content, true);
                $res = '';
                if (is_array($d)) {
                    $bits = [];
                    foreach (['ok', 'verified', 'fresh_hash', 'hash', 'error', 'code', 'skipped'] as $k) {
                        if (!isset($d[$k]) || !is_scalar($d[$k])) continue;
                        $v = is_bool($d[$k]) ? ($d[$k] ? 'true' : 'false') : mb_substr((string)$d[$k], 0, 60);
                        $bits[] = $k . '=' . $v;
                    }
                    $res = implode(' ', $bits);
                }
                $lines[] = '  => ' . ($res !== '' ? $res : mb_substr($content, 0, 100));
            }
        }
        $text = implode("\n", $lines);
        return mb_strlen($text) > 6000 ? mb_substr($text, -6000) : $text;
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
