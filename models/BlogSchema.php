<?php

class BlogSchema {
    private $dataFile;

    public function __construct() {
        $this->dataFile = BASE_PATH . '/data/blog_schemas.json';
    }

    public function getAll() {
        if (!file_exists($this->dataFile)) {
            return [];
        }
        $json = file_get_contents($this->dataFile);
        return json_decode($json, true) ?? [];
    }

    public function get($slug) {
        $schemas = $this->getAll();
        return $schemas[$slug] ?? null;
    }

    public function save($slug, $data) {
        $schemas = $this->getAll();
        
        // If string (valid JSON), decode it first to ensure validity and normalization
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
            $data = $decoded;
        }

        $schemas[$slug] = $data;
        return $this->saveAll($schemas);
    }
    
    public function delete($slug) {
        $schemas = $this->getAll();
        if (isset($schemas[$slug])) {
            unset($schemas[$slug]);
            return $this->saveAll($schemas);
        }
        return true;
    }

    public function bulkImport($data) {
        $current = $this->getAll();
        
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $slug => $schema) {
            $current[$slug] = $schema;
        }
        
        return $this->saveAll($current);
    }

    private function saveAll($data) {
        return file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
}
