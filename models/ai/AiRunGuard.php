<?php
// path: ./models/ai/AiRunGuard.php
// Per-session concurrency + cancellation guard. DB is the source of truth.

class AiRunGuard {

    public static function ensureColumns(): void {
        try {
            $db = Database::getInstance();
            $db->query("ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS run_state ENUM('idle','running','queued') NOT NULL DEFAULT 'idle' AFTER mode");
            $db->query("ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS run_token CHAR(36) NULL AFTER run_state");
            $db->query("ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS cancel_requested TINYINT(1) NOT NULL DEFAULT 0 AFTER run_token");
            $db->query("ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS version INT NOT NULL DEFAULT 1 AFTER cancel_requested");
            $db->query("ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS summary MEDIUMTEXT NULL AFTER context");
            $db->query("ALTER TABLE ai_sessions ADD COLUMN IF NOT EXISTS summary_updated_at DATETIME NULL AFTER summary");
            $db->query("CREATE TABLE IF NOT EXISTS ai_run_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id CHAR(36) NOT NULL,
                user_id INT NOT NULL,
                message TEXT NOT NULL,
                history JSON NULL,
                model VARCHAR(80) NOT NULL DEFAULT 'opencode-go/muse-spark-1.2-contributor',
                mode ENUM('plan','build') NOT NULL DEFAULT 'plan',
                status ENUM('queued','claimed','done','cancelled') NOT NULL DEFAULT 'queued',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_session_status (session_id, status),
                INDEX idx_user_created (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            error_log('AiRunGuard ensureColumns: ' . $e->getMessage());
        }
    }

    public static function tryClaim(string $sessionId, int $uid, string $runToken): array {
        self::ensureColumns();
        $db = Database::getInstance();
        try {
            $db->query("SELECT id FROM ai_sessions WHERE id=? AND user_id=? FOR UPDATE", [$sessionId, $uid]);
        } catch (Throwable $e) {}
        // Use transaction for atomic check
        try {
            $db->beginTransaction();
            $row = $db->fetchOne("SELECT run_state FROM ai_sessions WHERE id=? AND user_id=? FOR UPDATE", [$sessionId, $uid]);
            if (!$row) {
                $db->query("INSERT INTO ai_sessions (id,user_id,run_state,run_token,version) VALUES (?,?, 'running', ?, 1) ON DUPLICATE KEY UPDATE run_state='running', run_token=?, cancel_requested=0", [$sessionId, $uid, $runToken, $runToken]);
                $db->commit();
                return ['claimed' => true];
            }
            $state = $row['run_state'] ?? 'idle';
            if ($state === 'running') {
                $db->rollBack();
                return ['claimed' => false, 'reason' => 'running'];
            }
            $db->query("UPDATE ai_sessions SET run_state='running', run_token=?, cancel_requested=0, version=version+1 WHERE id=? AND user_id=?", [$runToken, $sessionId, $uid]);
            $db->commit();
            return ['claimed' => true];
        } catch (Throwable $e) {
            try { if ($db->inTransaction()) $db->rollBack(); } catch (Throwable $ignored) {}
            // Fallback: optimistic update if FOR UPDATE not supported
            try {
                $affected = $db->query("UPDATE ai_sessions SET run_state='running', run_token=?, cancel_requested=0 WHERE id=? AND user_id=? AND run_state='idle'", [$runToken, $sessionId, $uid])->rowCount();
                if ($affected > 0) return ['claimed' => true];
                return ['claimed' => false, 'reason' => 'running'];
            } catch (Throwable $e2) {
                return ['claimed' => false, 'reason' => 'error'];
            }
        }
    }

    public static function release(string $sessionId, int $uid, string $runToken): void {
        try {
            $db = Database::getInstance();
            $db->query("UPDATE ai_sessions SET run_state='idle', cancel_requested=0 WHERE id=? AND user_id=? AND run_token=?", [$sessionId, $uid, $runToken]);
        } catch (Throwable $e) {}
    }

    public static function requestCancel(string $sessionId, int $uid): bool {
        try {
            $db = Database::getInstance();
            $res = $db->query("UPDATE ai_sessions SET cancel_requested=1 WHERE id=? AND user_id=? AND run_state='running'", [$sessionId, $uid]);
            return $res->rowCount() > 0;
        } catch (Throwable $e) { return false; }
    }

    public static function isCancelled(string $sessionId, int $uid, string $runToken = ''): bool {
        try {
            $db = Database::getInstance();
            if ($runToken !== '') {
                $row = $db->fetchOne("SELECT cancel_requested FROM ai_sessions WHERE id=? AND user_id=? AND run_token=?", [$sessionId, $uid, $runToken]);
            } else {
                $row = $db->fetchOne("SELECT cancel_requested FROM ai_sessions WHERE id=? AND user_id=?", [$sessionId, $uid]);
            }
            return !empty($row['cancel_requested']);
        } catch (Throwable $e) { return false; }
    }

    public static function enqueue(string $sessionId, int $uid, string $message, array $history, string $model, string $mode): int {
        self::ensureColumns();
        try {
            $db = Database::getInstance();
            $db->query("UPDATE ai_sessions SET run_state='queued' WHERE id=? AND user_id=? AND run_state='running'", [$sessionId, $uid]);
            $db->query("INSERT INTO ai_run_queue (session_id,user_id,message,history,model,mode,status) VALUES (?,?,?,?,?,?, 'queued')", [$sessionId,$uid,$message,json_encode($history, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$model,$mode]);
            return (int)$db->lastInsertId();
        } catch (Throwable $e) { return 0; }
    }

    public static function dequeueNext(string $sessionId, int $uid): ?array {
        try {
            $db = Database::getInstance();
            $row = $db->fetchOne("SELECT * FROM ai_run_queue WHERE session_id=? AND user_id=? AND status='queued' ORDER BY id ASC LIMIT 1", [$sessionId,$uid]);
            return $row ?: null;
        } catch (Throwable $e) { return null; }
    }

    public static function markClaimed(int $queueId): void {
        try { Database::getInstance()->query("UPDATE ai_run_queue SET status='claimed' WHERE id=?", [$queueId]); } catch (Throwable $e) {}
    }

    public static function markDone(int $queueId, string $status = 'done'): void {
        try { Database::getInstance()->query("UPDATE ai_run_queue SET status=? WHERE id=?", [$status, $queueId]); } catch (Throwable $e) {}
    }

    public static function queuedCount(string $sessionId, int $uid): int {
        try {
            $row = Database::getInstance()->fetchOne("SELECT COUNT(*) as c FROM ai_run_queue WHERE session_id=? AND user_id=? AND status='queued'", [$sessionId,$uid]);
            return (int)($row['c'] ?? 0);
        } catch (Throwable $e) { return 0; }
    }
}
