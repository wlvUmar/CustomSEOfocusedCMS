<?php
// path: ./models/ContentRotationCsv.php

class ContentRotationCsv {
    private $rotation;

    public function __construct($rotationModel = null) {
        if ($rotationModel !== null) {
            $this->rotation = $rotationModel;
            return;
        }
        require_once BASE_PATH . '/models/ContentRotation.php';
        $this->rotation = new ContentRotation();
    }

    public function parseFile($tmpPath, $ext) {
        if ($ext === 'csv') {
            return $this->parseCsv($tmpPath);
        }
        if ($ext === 'json') {
            return $this->parseJsonFile($tmpPath);
        }
        throw new Exception('Only CSV and JSON files are supported');
    }

    public function parseCsv($filepath) {
        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            throw new Exception('Cannot read uploaded file');
        }
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            return [];
        }
        $headers = array_map('trim', $headers);
        $data = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }
            $data[] = array_combine($headers, $row);
        }
        fclose($handle);
        return $data;
    }

    public function parseJsonFile($tmpPath) {
        $content = file_get_contents($tmpPath);
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format');
        }
        return is_array($data) ? $data : [];
    }

    public function importRows($data) {
        $created = 0;
        $errors = [];
        foreach ($data as $index => $row) {
            if (empty($row['page_id']) || empty($row['active_month'])) {
                $errors[] = 'Row ' . ($index + 1) . ': Missing page_id or active_month';
                continue;
            }
            if ($this->rotation->monthHasContent($row['page_id'], $row['active_month'])) {
                $errors[] = 'Row ' . ($index + 1) . ': Month ' . $row['active_month'] . ' already has content';
                continue;
            }
            if ($this->rotation->create($this->normalizeRow($row))) {
                $created++;
            } else {
                $errors[] = 'Row ' . ($index + 1) . ': Failed to create rotation';
            }
        }
        return ['created' => $created, 'errors' => $errors];
    }

    public function normalizeRow($row) {
        return [
            'page_id' => (int)$row['page_id'],
            'active_month' => (int)$row['active_month'],
            'title_ru' => $row['title_ru'] ?? null,
            'title_uz' => $row['title_uz'] ?? null,
            'description_ru' => $row['description_ru'] ?? null,
            'description_uz' => $row['description_uz'] ?? null,
            'content_ru' => $row['content_ru'] ?? '',
            'content_uz' => $row['content_uz'] ?? '',
            'is_active' => isset($row['is_active']) ? (int)$row['is_active'] : 1,
            'meta_title_ru' => $row['meta_title_ru'] ?? null,
            'meta_title_uz' => $row['meta_title_uz'] ?? null,
            'meta_description_ru' => $row['meta_description_ru'] ?? null,
            'meta_description_uz' => $row['meta_description_uz'] ?? null,
            'meta_keywords_ru' => $row['meta_keywords_ru'] ?? null,
            'meta_keywords_uz' => $row['meta_keywords_uz'] ?? null,
            'og_title_ru' => $row['og_title_ru'] ?? null,
            'og_title_uz' => $row['og_title_uz'] ?? null,
            'og_description_ru' => $row['og_description_ru'] ?? null,
            'og_description_uz' => $row['og_description_uz'] ?? null,
            'og_image' => $row['og_image'] ?? null,
            'jsonld_ru' => $row['jsonld_ru'] ?? null,
            'jsonld_uz' => $row['jsonld_uz'] ?? null
        ];
    }
}
