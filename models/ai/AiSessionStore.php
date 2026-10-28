<?php
// path: ./models/ai/AiSessionStore.php
// Cross-session persistence for AI Studio. Owns all ai_sessions and
// ai_pending_approvals SQL. Static with explicit $uid params so the
// shutdown handler can call it without touching $_SESSION.

class AiSessionStore {

    // History is windowed by real user prompts (each one drags along its tool pairs),
    // not by raw message count — 24 messages was only 2-4 prompts of a build session.
    // Generous on purpose: the token budget (staged compaction in the controller) is what actually bounds context.
    public const WINDOW_USER_TURNS = 12;
    public const WINDOW_MAX_MESSAGES = 200;
    public const LIST_LIMIT = 50;
    public const TITLE_LEN = 120;

    /** user/assistant/tool messages only, minus run-internal ones tagged `_syn` (loop nudges/redirects). */
    public static function historyFromMessages(array $messages): array {
        $out = [];
        foreach ($messages as $m) {
            if (!is_array($m) || !in_array($m['role'] ?? '', ['user', 'assistant', 'tool'], true)) continue;
            if (!empty($m['_syn'])) continue;
            $out[] = $m;
        }
        return $out;
    }

    /**
     * Split history into [evicted, kept]. Kept starts at a user prompt (never mid tool pair)
     * and holds the last $userTurns prompts, capped at $maxMessages.
     */
    public static function splitWindow(array $history, int $userTurns = self::WINDOW_USER_TURNS, int $maxMessages = self::WINDOW_MAX_MESSAGES): array {
        $history = array_values($history);
        $n = count($history);
        $starts = [];
        foreach ($history as $i => $m) {
            if (($m['role'] ?? '') === 'user') $starts[] = $i;
        }
        $cut = count($starts) > $userTurns ? $starts[count($starts) - $userTurns] : 0;
        if ($n - $cut > $maxMessages) {
            $cut = $n - $maxMessages;
            foreach ($starts as $s) {
                if ($s >= $cut) { $cut = $s; break; }
            }
        }
        while ($cut < $n && ($history[$cut]['role'] ?? '') === 'tool') $cut++;
        return [array_slice($history, 0, $cut), array_slice($history, $cut)];
    }

    public static function windowByTurns(array $history): array {
        return self::splitWindow($history)[1];
    }

    private static bool $sessionsTableEnsured = false;
    private static bool $pendingTableEnsured = false;

    public static function ensureSessionsTable(): void {
        // DDL lives in migrations/006_add_ai_sessions.php — never here.
        self::$sessionsTableEnsured = true;
    }

    public static function ensurePendingTable(): void {
        // DDL lives in migrations/012_ai_pending_approvals.php — never here.
        self::$pendingTableEnsured = true;
    }

    public static function ensureTables(): void {
        self::ensureSessionsTable();
        self::ensurePendingTable();
    }

    public static function listSessions(int $uid): array {
        self::ensureSessionsTable();
        $rows = Database::getInstance()->fetchAll("SELECT id, title, model, mode, updated_at, created_at FROM ai_sessions WHERE user_id = ? ORDER BY updated_at DESC LIMIT 50", [$uid]);
        return is_array($rows) ? $rows : [];
    }

    public static function getSession(string $sessionId, int $uid): ?array {
        self::ensureSessionsTable();
        $row = Database::getInstance()->fetchOne("SELECT * FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, $uid]);
        return is_array($row) ? $row : null;
    }

    public static function deleteSession(string $sessionId, int $uid): void {
        Database::getInstance()->query("DELETE FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, $uid]);
        try { self::ensurePendingTable(); Database::getInstance()->query("DELETE FROM ai_pending_approvals WHERE session_id=? AND user_id=?", [$sessionId, $uid]); } catch (Throwable $e) {}
        try { Database::getInstance()->query("DELETE FROM ai_run_queue WHERE session_id=? AND user_id=?", [$sessionId, $uid]); } catch (Throwable $e) {}
    }

    public static function queueStatus(string $sessionId, int $uid): array {
        $row = Database::getInstance()->fetchOne("SELECT run_state, cancel_requested FROM ai_sessions WHERE id=? AND user_id=?", [$sessionId, $uid]);
        if (!is_array($row)) $row = [];
        return [
            'run_state' => $row['run_state'] ?? 'idle',
            'cancel_requested' => (int)($row['cancel_requested'] ?? 0),
            'queued' => AiRunGuard::queuedCount($sessionId, $uid),
        ];
    }

    public static function loadHistory(string $sessionId, int $uid): array {
        try {
            self::ensureSessionsTable();
            $row = Database::getInstance()->fetchOne("SELECT history FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, $uid]);
            if (!$row || empty($row['history'])) return [];
            $arr = is_string($row['history']) ? json_decode($row['history'], true) : $row['history'];
            return is_array($arr) ? $arr : [];
        } catch (Throwable $e) { return []; }
    }

    public static function loadContext(string $sessionId, int $uid): array {
        try {
            self::ensureSessionsTable();
            $row = Database::getInstance()->fetchOne("SELECT context FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, $uid]);
            if (!$row || empty($row['context'])) return [];
            $arr = is_string($row['context']) ? json_decode($row['context'], true) : $row['context'];
            return is_array($arr) ? $arr : [];
        } catch (Throwable $e) { return []; }
    }

    public static function loadSession(string $sessionId, int $uid): array {
        return [
            'history' => self::loadHistory($sessionId, $uid),
            'context' => self::loadContext($sessionId, $uid),
        ];
    }

    public static function saveSession(string $sessionId, int $uid, array $history, array $context, string $model, string $mode): void {
        try {
            self::ensureSessionsTable();
            $history = self::windowByTurns($history);
            $title = '';
            foreach ($history as $m) { if (($m['role'] ?? '') === 'user' && !empty($m['content'])) { $raw = trim((string)$m['content']); $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw); $raw = strip_tags($raw); $raw = preg_replace('/\s+/u', ' ', $raw); $title = mb_substr($raw, 0, self::TITLE_LEN); break; } }
            $histJson = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ctxJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $exists = Database::getInstance()->fetchOne("SELECT id, version FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, $uid]);
            if ($exists) {
                try {
                    // Title is kept once set: the window drops the opening prompt, so re-deriving it would rename the session.
                    Database::getInstance()->query("UPDATE ai_sessions SET history=?, context=?, model=?, mode=?, title=IF(title IS NULL OR title='', ?, title), version=version+1, updated_at=NOW() WHERE id=? AND user_id=?", [$histJson, $ctxJson, $model, $mode, $title, $sessionId, $uid]);
                } catch (Throwable $e) {
                    Database::getInstance()->query("UPDATE ai_sessions SET history=?, context=?, model=?, mode=?, title=IF(title IS NULL OR title='', ?, title), updated_at=NOW() WHERE id=? AND user_id=?", [$histJson, $ctxJson, $model, $mode, $title, $sessionId, $uid]);
                }
            } else {
                try {
                    Database::getInstance()->query("INSERT INTO ai_sessions (id,user_id,title,model,mode,history,context,version) VALUES (?,?,?,?,?,?,?,1)", [$sessionId, $uid, $title, $model, $mode, $histJson, $ctxJson]);
                } catch (Throwable $e) {
                    Database::getInstance()->query("INSERT INTO ai_sessions (id,user_id,title,model,mode,history,context) VALUES (?,?,?,?,?,?,?)", [$sessionId, $uid, $title, $model, $mode, $histJson, $ctxJson]);
                }
            }
        } catch (Throwable $e) { error_log('persistSession failed: ' . $e->getMessage()); }
    }

    public static function saveAfterRun(string $sessionId, int $uid, array $history, array $context, string $model, string $mode): void {
        try {
            $histJson = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ctxJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            self::ensureSessionsTable();
            $exists = Database::getInstance()->fetchOne("SELECT id FROM ai_sessions WHERE id = ? AND user_id = ?", [$sessionId, $uid]);
            if ($exists) {
                Database::getInstance()->query("UPDATE ai_sessions SET history=?, context=?, model=?, mode=?, updated_at=NOW() WHERE id=? AND user_id=?", [$histJson, $ctxJson, $model, $mode, $sessionId, $uid]);
            } else {
                $title = '';
                foreach ($history as $m) { if (($m['role'] ?? '') === 'user' && !empty($m['content'])) { $raw = trim((string)$m['content']); $raw = strip_tags($raw); $title = mb_substr(preg_replace('/\s+/u', ' ', $raw), 0, self::TITLE_LEN); break; } }
                Database::getInstance()->query("INSERT INTO ai_sessions (id,user_id,title,model,mode,history,context) VALUES (?,?,?,?,?,?,?)", [$sessionId, $uid, $title, $model, $mode, $histJson, $ctxJson]);
            }
        } catch (Throwable $e) { error_log('staticPersistAfterRun failed: ' . $e->getMessage()); }
    }

    public static function loadPending(string $sessionId, int $uid): array {
        try {
            self::ensurePendingTable();
            $rows = Database::getInstance()->fetchAll("SELECT call_id, tool, plan, reason, created_at FROM ai_pending_approvals WHERE session_id=? AND user_id=? ORDER BY id ASC", [$sessionId, $uid]);
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) { return []; }
    }

    public static function savePending(string $sessionId, int $uid, string $tool, array $callIds, array $pendingPair, string $plan, string $reason, string $mode = 'plan'): void {
        if ($sessionId === '' || $uid <= 0 || empty($callIds)) return;
        $mode = $mode === 'build' ? 'build' : 'plan';
        try {
            self::ensurePendingTable();
            $pendingJson = json_encode($pendingPair, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($callIds as $cid) {
                if (!preg_match('/^[a-f0-9]{40}$/', $cid)) continue;
                try {
                    Database::getInstance()->query(
                        "INSERT INTO ai_pending_approvals (session_id, user_id, call_id, tool, plan, reason, mode, pending_json) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tool=VALUES(tool), plan=VALUES(plan), reason=VALUES(reason), mode=VALUES(mode), pending_json=VALUES(pending_json), created_at=NOW()",
                        [$sessionId, $uid, $cid, $tool, mb_substr($plan, 0, 2000), mb_substr($reason, 0, 500), $mode, $pendingJson]
                    );
                } catch (Throwable $e) {
                    if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'mode') !== false) {
                        Database::getInstance()->query(
                            "INSERT INTO ai_pending_approvals (session_id, user_id, call_id, tool, plan, reason, pending_json) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tool=VALUES(tool), plan=VALUES(plan), reason=VALUES(reason), pending_json=VALUES(pending_json), created_at=NOW()",
                            [$sessionId, $uid, $cid, $tool, mb_substr($plan, 0, 2000), mb_substr($reason, 0, 500), $pendingJson]
                        );
                    } else throw $e;
                }
            }
        } catch (Throwable $e) {
            error_log('savePendingApprovals failed: ' . $e->getMessage());
        }
    }

    public static function pendingMode(string $sessionId, int $uid): ?string {
        if ($sessionId === '' || $uid <= 0) return null;
        try {
            self::ensurePendingTable();
            $row = Database::getInstance()->fetchOne("SELECT mode FROM ai_pending_approvals WHERE session_id=? AND user_id=? LIMIT 1", [$sessionId, $uid]);
            if (!$row || !isset($row['mode'])) return null;
            $m = strtolower(trim((string)$row['mode']));
            return in_array($m, ['plan', 'build'], true) ? $m : null;
        } catch (Throwable $e) { return null; }
    }

    public static function filterApproved(string $sessionId, int $uid, array $approved): array {
        if (empty($approved) || $sessionId === '' || $uid <= 0) return [];
        try {
            self::ensurePendingTable();
            $placeholders = implode(',', array_fill(0, count($approved), '?'));
            $params = array_merge([$sessionId, $uid], $approved);
            $rows = Database::getInstance()->fetchAll("SELECT call_id FROM ai_pending_approvals WHERE session_id=? AND user_id=? AND call_id IN ($placeholders)", $params);
            $valid = array_column($rows, 'call_id');
            return array_values(array_intersect($approved, $valid));
        } catch (Throwable $e) { return []; }
    }

    public static function validatePending(string $sessionId, int $uid, array $pending): array {
        if (empty($pending) || $sessionId === '' || $uid <= 0) return [];
        try {
            self::ensurePendingTable();
            $row = Database::getInstance()->fetchOne("SELECT call_id FROM ai_pending_approvals WHERE session_id=? AND user_id=? LIMIT 1", [$sessionId, $uid]);
            if (!$row) return [];
            $cid = (string)($pending[0]['tool_calls'][0]['id'] ?? '');
            if ($cid !== '') {
                $exists = Database::getInstance()->fetchOne("SELECT call_id FROM ai_pending_approvals WHERE session_id=? AND user_id=? AND call_id=?", [$sessionId, $uid, $cid]);
                if (!$exists) {
                    $any = Database::getInstance()->fetchOne("SELECT call_id FROM ai_pending_approvals WHERE session_id=? AND user_id=? LIMIT 1", [$sessionId, $uid]);
                    if (!$any) return [];
                }
            }
            return $pending;
        } catch (Throwable $e) { return []; }
    }

    public static function clearPending(string $sessionId, int $uid, ?array $callIds = null): void {
        if ($sessionId === '' || $uid <= 0) return;
        try {
            self::ensurePendingTable();
            if ($callIds === null) {
                Database::getInstance()->query("DELETE FROM ai_pending_approvals WHERE session_id=? AND user_id=?", [$sessionId, $uid]);
            } elseif (!empty($callIds)) {
                $ph = implode(',', array_fill(0, count($callIds), '?'));
                $params = array_merge([$sessionId, $uid], $callIds);
                Database::getInstance()->query("DELETE FROM ai_pending_approvals WHERE session_id=? AND user_id=? AND call_id IN ($ph)", $params);
            }
        } catch (Throwable $e) {}
    }
}
