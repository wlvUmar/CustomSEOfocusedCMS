<?php
class Database {
    private static $instance = null;
    private $pdo;
    private $lastActivity = 0;
    private const CONN_LOST_CODES = [2006, 2013];

    private function __construct() {
        try {
            $this->connect();
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            // Don't leak details to client — let caller handle via exception
            throw new RuntimeException("Database unavailable", 0, $e);
        }
    }

    private function connect() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $tz = date('P');
        if ($tz) {
            $this->pdo->exec("SET time_zone = '$tz'");
        }
        try {
            // Keep idle connections alive through long requests (e.g. AI model
            // calls); skipped silently where the host restricts session vars.
            $this->pdo->exec('SET SESSION wait_timeout = 86400');
        } catch (PDOException $e) {
            // not fatal
        }
        $this->lastActivity = microtime(true);
    }

    private function isConnectionLoss(PDOException $e): bool {
        $errno = (int)($e->errorInfo[1] ?? 0);
        if (in_array($errno, self::CONN_LOST_CODES, true)) {
            return true;
        }
        $msg = strtolower($e->getMessage());
        return strpos($msg, 'server has gone away') !== false
            || strpos($msg, 'lost connection') !== false;
    }

    /**
     * Ping when the connection has been idle for a while (long model calls in
     * AI Studio, slow upstream requests, etc.) so MySQL doesn't kill it.
     */
    private function ensureAlive() {
        if (microtime(true) - $this->lastActivity < 30) {
            return;
        }
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            if ($this->isConnectionLoss($e)) {
                $this->connect();
            } else {
                throw $e;
            }
        }
        $this->lastActivity = microtime(true);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $this->ensureAlive();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ($this->isConnectionLoss($e)) {
                $this->connect();
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                throw $e;
            }
        }
        $this->lastActivity = microtime(true);
        return $stmt;
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}