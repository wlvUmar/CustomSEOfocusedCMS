<?php
// path: ./models/AuditLog.php
// Append-only record of content/config changes: who, when, what, from → to.
// Never throws to the caller; failures are logged and ignored so saves keep working.

class AuditLog {
    /** Columns that are derived or bookkeeping, not real edits. */
    private const IGNORED_FIELDS = ['depth', 'created_at', 'updated_at'];
    /** Text longer than this is stored as the changed region only. */
    private const LONG_TEXT = 300;
    private const CONTEXT_CHARS = 60;
    private const REGION_MAX = 600;
    private const RETENTION_DAYS = 400;

    /** Log an UPDATE. $before/$after are full row arrays; $fields limits the comparison. */
    public static function logUpdate(string $entityType, $entityId, ?string $label, ?array $before, ?array $after, ?array $fields = null, ?string $action = null): void {
        if (!$before || !$after) return;
        $changes = self::diff($before, $after, $fields);
        if (!$changes) return;
        $action = $action ?? self::detectToggle($changes) ?? 'update';
        self::write($action, $entityType, $entityId, $label, $changes);
    }

    /** Log a CREATE. $row is the new row (or the submitted data). */
    public static function logCreate(string $entityType, $entityId, ?string $label, array $row = []): void {
        $changes = self::diff([], $row);
        self::write('create', $entityType, $entityId, $label, $changes);
    }

    /** Log a DELETE. $row is the row as it was before deletion. */
    public static function logDelete(string $entityType, $entityId, ?string $label, array $row = []): void {
        $changes = self::diff($row, []);
        self::write('delete', $entityType, $entityId, $label, $changes);
    }

    /** Field-level diff: [field => ['from' => ..., 'to' => ...]]. */
    public static function diff(array $before, array $after, ?array $fields = null): array {
        $keys = $fields ?? array_unique(array_merge(array_keys($before), array_keys($after)));
        $out = [];
        foreach ($keys as $k) {
            if (in_array($k, self::IGNORED_FIELDS, true)) continue;
            $old = self::scalar($before[$k] ?? null);
            $new = self::scalar($after[$k] ?? null);
            if ($old === $new) continue;
            $out[$k] = self::describe($old, $new);
        }
        return $out;
    }

    private static function scalar($v): ?string {
        if ($v === null) return null;
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return (string)$v;
    }

    private static function describe(?string $old, ?string $new): array {
        $oldLen = $old === null ? 0 : mb_strlen($old);
        $newLen = $new === null ? 0 : mb_strlen($new);
        if ($oldLen <= self::LONG_TEXT && $newLen <= self::LONG_TEXT) {
            return ['from' => $old, 'to' => $new];
        }
        // Long text: keep only the region that differs, plus lengths.
        $oldChars = $oldLen ? mb_str_split($old) : [];
        $newChars = $newLen ? mb_str_split($new) : [];
        $prefix = 0;
        $max = min($oldLen, $newLen);
        while ($prefix < $max && $oldChars[$prefix] === $newChars[$prefix]) $prefix++;
        $suffix = 0;
        while ($suffix < $max - $prefix
            && $oldChars[$oldLen - 1 - $suffix] === $newChars[$newLen - 1 - $suffix]) $suffix++;
        $start = max(0, $prefix - self::CONTEXT_CHARS);
        $oldRegion = implode('', array_slice($oldChars, $start, ($oldLen - $suffix) - $start + self::CONTEXT_CHARS));
        $newRegion = implode('', array_slice($newChars, $start, ($newLen - $suffix) - $start + self::CONTEXT_CHARS));
        return [
            'from' => self::clip($oldRegion),
            'to' => self::clip($newRegion),
            'from_len' => $oldLen,
            'to_len' => $newLen,
            'at' => $prefix,
            'partial' => true,
        ];
    }

    private static function clip(string $s): string {
        return mb_strlen($s) > self::REGION_MAX ? mb_substr($s, 0, self::REGION_MAX) . '…' : $s;
    }

    private static function detectToggle(array $changes): ?string {
        if (count($changes) === 1 && isset($changes['is_published'])) {
            return ($changes['is_published']['to'] ?? '0') === '1' ? 'publish' : 'unpublish';
        }
        return null;
    }

    private static function detectSource(): string {
        if (PHP_SAPI === 'cli') return 'system';
        $aiClasses = ['PageTools', 'AiToolRegistry', 'AiStudioController', 'RotationTools', 'SiteTools', 'MemoryTools', 'AnalyticsTools', 'AnalyticsQueryTools', 'PageSectionEditor', 'PageRevisionTools'];
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25) as $frame) {
            if (in_array($frame['class'] ?? '', $aiClasses, true)) return 'ai';
        }
        return 'admin';
    }

    private static function summarize(string $action, string $entityType, ?string $label, array $changes): string {
        $name = $label !== null && $label !== '' ? "$entityType “$label”" : $entityType;
        if ($action === 'create') return "Created $name";
        if ($action === 'delete') return "Deleted $name";
        if ($action === 'publish') return "Published $name";
        if ($action === 'unpublish') return "Unpublished $name";
        $fields = array_keys($changes);
        $shown = implode(', ', array_slice($fields, 0, 6));
        $more = count($fields) > 6 ? ' +' . (count($fields) - 6) . ' more' : '';
        return "Updated $name: $shown$more";
    }

    private static function write(string $action, string $entityType, $entityId, ?string $label, array $changes): void {
        try {
            $db = Database::getInstance();
            $json = $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : null;
            $uri = $_SERVER['REQUEST_URI'] ?? null;
            $db->query(
                "INSERT INTO audit_log (user_id, username, source, action, entity_type, entity_id, entity_label, summary, changes, ip, request_uri)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['username'] ?? null,
                    self::detectSource(),
                    $action,
                    $entityType,
                    $entityId !== null ? (int)$entityId : null,
                    $label !== null ? mb_substr($label, 0, 255) : null,
                    mb_substr(self::summarize($action, $entityType, $label, $changes), 0, 500),
                    $json,
                    PHP_SAPI === 'cli' ? null : (function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? null)),
                    $uri !== null ? mb_substr($uri, 0, 255) : null,
                ]
            );
            if (random_int(1, 200) === 1) self::prune();
        } catch (Throwable $e) {
            error_log('[AuditLog] write failed for ' . $entityType . ' ' . $entityId . ': ' . $e->getMessage());
        }
    }

    public static function prune(int $days = self::RETENTION_DAYS): void {
        Database::getInstance()->query(
            "DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL " . max(30, $days) . " DAY)"
        );
    }

    // ── Reading (admin viewer) ─────────────────────────────

    public static function search(array $f, int $limit = 50, int $offset = 0): array {
        [$where, $params] = self::buildWhere($f);
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        return Database::getInstance()->fetchAll(
            "SELECT * FROM audit_log $where ORDER BY id DESC LIMIT $limit OFFSET $offset",
            $params
        );
    }

    public static function count(array $f): int {
        [$where, $params] = self::buildWhere($f);
        return (int)(Database::getInstance()->fetchOne("SELECT COUNT(*) AS c FROM audit_log $where", $params)['c'] ?? 0);
    }

    private static function buildWhere(array $f): array {
        $w = [];
        $p = [];
        foreach (['entity_type', 'action', 'source'] as $k) {
            if (!empty($f[$k])) { $w[] = "$k = ?"; $p[] = $f[$k]; }
        }
        if (!empty($f['entity_id'])) { $w[] = 'entity_id = ?'; $p[] = (int)$f['entity_id']; }
        if (!empty($f['from'])) { $w[] = 'created_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to'])) { $w[] = 'created_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
        if (!empty($f['q'])) {
            $w[] = '(entity_label LIKE ? OR summary LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            $p[] = $like; $p[] = $like;
        }
        return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
    }
}
