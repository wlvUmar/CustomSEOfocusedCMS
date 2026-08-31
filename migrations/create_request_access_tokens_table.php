<?php
/**
 * Migration: Create request_access_tokens table
 * Stores secure tokens for admin access to request details without login
 * Tokens expire after 3 days
 */

// Migrate via migrate.php expects return array of SQL strings (see migrate.php:86)
// This file is now compatible with both direct `php migrations/...` and `php migrate.php` runner.
$sql = "
CREATE TABLE IF NOT EXISTS `request_access_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `used_count` INT DEFAULT 0,
    FOREIGN KEY (`request_id`) REFERENCES `product_requests`(`id`) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_request_id (request_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// When run directly (`php migrations/create_...php`) execute immediately for BC
if (basename(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? '') === basename(__FILE__)) {
    require_once BASE_PATH . '/core/Database.php';
    $pdo = Database::getInstance()->getConnection();
    try {
        $pdo->exec($sql);
        echo "✅ Migration completed: request_access_tokens table created\n";
    } catch (PDOException $e) {
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

return [$sql];
