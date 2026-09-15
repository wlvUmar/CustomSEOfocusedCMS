<?php
// path: ./models/ai/ContextBuilder.php
// Constructs model context: system + summary + window + pending + user.
// Summarizes evicted turns via the same Opencode provider (low-cost).

require_once BASE_PATH . '/models/ai/PromptLoader.php';

class ContextBuilder {

    public static function buildMessages(string $systemPrompt, ?string $summary, array $historyWindow, array $pending, string $userMessage, bool $alreadyInHistory): array {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        if ($summary !== null && trim($summary) !== '') {
            $messages[] = ['role' => 'system', 'content' => "CONVERSATION SUMMARY (compacted, may be stale — prefer fresh tool reads for HTML):\n" . mb_substr($summary, 0, 4000)];
        }
        foreach ($historyWindow as $m) $messages[] = $m;
        foreach ($pending as $p) $messages[] = $p;
        if (!$alreadyInHistory) $messages[] = ['role' => 'user', 'content' => $userMessage];
        return $messages;
    }

    public static function summarizeIfNeeded(string $sessionId, int $uid, array $fullHistory): ?string {
        if (count($fullHistory) <= 24) return null;
        $evicted = array_slice($fullHistory, 0, count($fullHistory) - 24);
        if (empty($evicted)) return null;
        try {
            $db = Database::getInstance();
            $row = $db->fetchOne("SELECT summary FROM ai_sessions WHERE id=? AND user_id=?", [$sessionId, $uid]);
            $prevSummary = $row['summary'] ?? '';
        } catch (Throwable $e) { $prevSummary = ''; }

        $evictedText = '';
        foreach (array_slice($evicted, -12) as $m) {
            $role = $m['role'] ?? 'unknown';
            $content = mb_substr((string)($m['content'] ?? ''), 0, 500);
            if (isset($m['tool_calls'])) $content .= ' [tool_calls:' . count($m['tool_calls']) . ']';
            $evictedText .= strtoupper($role) . ": " . $content . "\n";
        }

        try {
            $prompt = PromptLoader::render('summarizer-user', [
                'prev_summary' => mb_substr((string)$prevSummary, 0, 1000),
                'evicted_turns' => $evictedText,
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
                600,
                0,
                'auto',
                $sessionId
            );
            $summary = trim((string)($resp['content'] ?? ''));
            if ($summary === '') return null;
            if (mb_strlen($summary) > 2000) $summary = mb_substr($summary, 0, 2000);
            // Merge with previous if exists
            if (!empty($prevSummary) && mb_strlen($prevSummary) < 1500) {
                $summary = trim($prevSummary) . "\n" . $summary;
                if (mb_strlen($summary) > 2000) $summary = mb_substr($summary, -2000);
            }
            return $summary;
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
