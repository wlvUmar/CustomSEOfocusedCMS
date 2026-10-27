<?php
// path: ./models/FreshBlock.php
// Site-wide freshness block (table fresh_blocks). Written by scripts/freshness-run.php,
// read by views/partials/fresh-block.php. Reads never throw: a failure just hides the block.

require_once BASE_PATH . '/core/Database.php';

class FreshBlock {
    private const KEEP_PREVIOUS = 20;

    /** @return array{demand:string,season:string,updated:string}|null */
    public static function getLive(string $lang, string $key = 'site'): ?array {
        try {
            $row = Database::getInstance()->fetchOne(
                "SELECT body, created_at FROM fresh_blocks WHERE block_key = ? AND lang = ? AND status = 'live' ORDER BY id DESC LIMIT 1",
                [$key, $lang]
            );
        } catch (Throwable $e) {
            return null;
        }
        if (!$row) return null;
        $body = json_decode((string)$row['body'], true);
        if (!is_array($body)) return null;
        return [
            'demand'  => trim((string)($body['demand'] ?? '')),
            'season'  => trim((string)($body['season'] ?? '')),
            'updated' => date('d.m.Y', strtotime((string)$row['created_at'])),
        ];
    }

    /** Publish RU+UZ together so the languages never diverge. */
    public static function publish(array $bodiesByLang, array $facts, string $key = 'site'): void {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            foreach (['ru', 'uz'] as $lang) {
                $db->query("UPDATE fresh_blocks SET status = 'previous' WHERE block_key = ? AND lang = ? AND status = 'live'", [$key, $lang]);
                $db->query(
                    "INSERT INTO fresh_blocks (block_key, lang, body, data_json, status) VALUES (?, ?, ?, ?, 'live')",
                    [
                        $key, $lang,
                        json_encode($bodiesByLang[$lang], JSON_UNESCAPED_UNICODE),
                        json_encode($facts, JSON_UNESCAPED_UNICODE),
                    ]
                );
            }
            $db->query(
                "DELETE FROM fresh_blocks WHERE block_key = ? AND status = 'previous' AND id NOT IN (
                    SELECT id FROM (SELECT id FROM fresh_blocks WHERE block_key = ? AND status = 'previous' ORDER BY id DESC LIMIT " . (self::KEEP_PREVIOUS * 2) . ") t
                )",
                [$key, $key]
            );
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Make the newest 'previous' pair live again (rollback). */
    public static function rollback(string $key = 'site'): bool {
        $db = Database::getInstance();
        $done = false;
        foreach (['ru', 'uz'] as $lang) {
            $prev = $db->fetchOne("SELECT id FROM fresh_blocks WHERE block_key = ? AND lang = ? AND status = 'previous' ORDER BY id DESC LIMIT 1", [$key, $lang]);
            if (!$prev) continue;
            $db->query("UPDATE fresh_blocks SET status = 'previous' WHERE block_key = ? AND lang = ? AND status = 'live'", [$key, $lang]);
            $db->query("UPDATE fresh_blocks SET status = 'live', created_at = created_at WHERE id = ?", [$prev['id']]);
            $done = true;
        }
        return $done;
    }
}
