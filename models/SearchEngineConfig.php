<?php
// path: ./models/SearchEngineConfig.php

class SearchEngineConfig {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        return $this->db->fetchAll("SELECT * FROM search_engine_config ORDER BY FIELD(engine, 'bing', 'yandex', 'naver', 'seznam', 'yep', 'google')");
    }

    public function get(string $engine) {
        return $this->db->fetchOne("SELECT * FROM search_engine_config WHERE engine = ?", [$engine]);
    }

    public function getEnabled() {
        return $this->db->fetchAll("SELECT * FROM search_engine_config WHERE enabled = 1");
    }

    public function update($engine, $data) {
        // Dynamic update based on provided keys
        $sets = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
        
        $params[] = $engine; // For WHERE clause
        
        $sql = "UPDATE search_engine_config SET " . implode(', ', $sets) . " WHERE engine = ?";
        return $this->db->query($sql, $params);
    }
    
    public function updateApiKey($engine, $key) {
        return $this->db->query(
            "UPDATE search_engine_config SET api_key = ? WHERE engine = ?", 
            [$key, $engine]
        );
    }

    public function incrementRateLimit($engine) {
        $sql = "UPDATE search_engine_config 
                SET submissions_today = submissions_today + 1 
                WHERE engine = ?";
        return $this->db->query($sql, [$engine]);
    }

    public function resetDailyCounter($engine) {
        $sql = "UPDATE search_engine_config 
                SET submissions_today = 0, 
                    last_reset_date = CURDATE() 
                WHERE engine = ?";
        return $this->db->query($sql, [$engine]);
    }

    public function logNote($engine, $note) {
         try {
            $this->db->query(
                "UPDATE search_engine_config SET notes = CONCAT(IFNULL(notes,''), ?) WHERE engine = ?", 
                [" | " . $note, $engine]
            );
        } catch (Exception $e) {
            // ignore
        }
    }
    
    /**
     * Ensure default configuration exists for all supported engines
     */
    public function ensureDefaults() {
        $engines = ['bing', 'yandex', 'google', 'naver', 'seznam', 'yep'];
        
        foreach ($engines as $engine) {
            $exists = $this->get($engine);
            
            if (!$exists) {
                $sql = "INSERT INTO search_engine_config 
                        (engine, enabled, api_key, rate_limit_per_day, submissions_today, 
                         last_reset_date, auto_submit_on_create, auto_submit_on_update, 
                         auto_submit_on_rotation, ping_sitemap) 
                        VALUES (?, 0, NULL, 10000, 0, CURDATE(), 0, 0, 0, 0)";
                
                try {
                    $this->db->query($sql, [$engine]);
                } catch (Exception $e) {
                    error_log("Failed to create default config for $engine: " . $e->getMessage());
                }
            }
        }
    }
}
