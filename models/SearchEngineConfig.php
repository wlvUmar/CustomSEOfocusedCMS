<?php
// path: ./models/SearchEngineConfig.php

class SearchEngineConfig {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        return $this->db->fetchAll("SELECT * FROM search_engine_config ORDER BY FIELD(engine, 'bing', 'yandex', 'google')");
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

    /**
     * Ensure default configuration exists for all supported engines
     * Auto-inserts missing engines with default settings
     */
    public function ensureDefaults() {
        // Only these 3 engines are supported by the database enum
        $engines = ['bing', 'yandex', 'google'];
        
        // First, clean up any empty string entries (bad data)
        try {
            $this->db->query("DELETE FROM search_engine_config WHERE engine = '' OR engine IS NULL");
        } catch (Exception $e) {
            error_log("SearchEngineConfig::ensureDefaults - Cleanup failed: " . $e->getMessage());
        }
        
        foreach ($engines as $engine) {
            // Check if entry exists
            $existing = $this->db->fetchOne("SELECT id FROM search_engine_config WHERE engine = ?", [$engine]);
            
            if (!$existing) {
                // Only insert if doesn't exist
                $sql = "INSERT INTO search_engine_config 
                        (engine, enabled, api_key, rate_limit_per_day, submissions_today, 
                         last_reset_date, auto_submit_on_create, auto_submit_on_update, 
                         auto_submit_on_rotation, ping_sitemap) 
                        VALUES (?, 0, NULL, 10000, 0, CURDATE(), 0, 0, 0, 0)";
                
                try {
                    $this->db->query($sql, [$engine]);
                    error_log("SearchEngineConfig::ensureDefaults - Auto-inserted: {$engine}");
                } catch (Exception $e) {
                    error_log("SearchEngineConfig::ensureDefaults - Failed to insert {$engine}: " . $e->getMessage());
                }
            }
        }
    }
}
