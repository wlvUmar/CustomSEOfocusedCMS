<?php
// path: ./models/Page.php

class Page {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getBySlug($slug) {
        $sql = "SELECT * FROM pages WHERE slug = ? AND is_published = 1";
        return $this->db->fetchOne($sql, [$slug]);
    }

    public function getAll($includeUnpublished = false) {
        $sql = "SELECT * FROM pages";
        if (!$includeUnpublished) {
            $sql .= " WHERE is_published = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        return $this->db->fetchAll($sql);
    }

    public function getById($id) {
        $sql = "SELECT * FROM pages WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function create($data) {
        $sql = "INSERT INTO pages (
                    slug, title_ru, title_uz, content_ru, content_uz, 
                    meta_title_ru, meta_title_uz, meta_keywords_ru, meta_keywords_uz, 
                    meta_description_ru, meta_description_uz, 
                    og_title_ru, og_title_uz, og_description_ru, og_description_uz, og_image,
                    canonical_url, jsonld_ru, jsonld_uz, 
                    is_published, enable_rotation, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $data['slug'],
            $data['title_ru'],
            $data['title_uz'],
            $data['content_ru'],
            $data['content_uz'],
            $data['meta_title_ru'] ?? null,
            $data['meta_title_uz'] ?? null,
            $data['meta_keywords_ru'] ?? null,
            $data['meta_keywords_uz'] ?? null,
            $data['meta_description_ru'] ?? null,
            $data['meta_description_uz'] ?? null,
            $data['og_title_ru'] ?? null,
            $data['og_title_uz'] ?? null,
            $data['og_description_ru'] ?? null,
            $data['og_description_uz'] ?? null,
            $data['og_image'] ?? null,
            $data['canonical_url'] ?? null,
            $data['jsonld_ru'] ?? null,
            $data['jsonld_uz'] ?? null,
            $data['is_published'] ?? 1,
            $data['enable_rotation'] ?? 0,
            $data['sort_order'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE pages SET 
                    slug = ?, title_ru = ?, title_uz = ?, 
                    content_ru = ?, content_uz = ?, 
                    meta_title_ru = ?, meta_title_uz = ?, 
                    meta_keywords_ru = ?, meta_keywords_uz = ?, 
                    meta_description_ru = ?, meta_description_uz = ?, 
                    og_title_ru = ?, og_title_uz = ?, 
                    og_description_ru = ?, og_description_uz = ?, 
                    og_image = ?, canonical_url = ?,
                    jsonld_ru = ?, jsonld_uz = ?, 
                    is_published = ?, enable_rotation = ?, sort_order = ? 
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['slug'],
            $data['title_ru'],
            $data['title_uz'],
            $data['content_ru'],
            $data['content_uz'],
            $data['meta_title_ru'] ?? null,
            $data['meta_title_uz'] ?? null,
            $data['meta_keywords_ru'] ?? null,
            $data['meta_keywords_uz'] ?? null,
            $data['meta_description_ru'] ?? null,
            $data['meta_description_uz'] ?? null,
            $data['og_title_ru'] ?? null,
            $data['og_title_uz'] ?? null,
            $data['og_description_ru'] ?? null,
            $data['og_description_uz'] ?? null,
            $data['og_image'] ?? null,
            $data['canonical_url'] ?? null,
            $data['jsonld_ru'] ?? null,
            $data['jsonld_uz'] ?? null,
            $data['is_published'] ?? 1,
            $data['enable_rotation'] ?? 0,
            $data['sort_order'] ?? 0,
            $id
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM pages WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }

    /**
     * Get all media attached to this page
     */
    public function getMedia($id) {
        $sql = "SELECT pm.*, m.filename, m.original_name, m.file_size, m.mime_type
                FROM page_media pm
                JOIN media m ON pm.media_id = m.id
                WHERE pm.page_id = ?
                ORDER BY pm.section ASC, pm.position ASC, pm.id ASC";
        return $this->db->fetchAll($sql, [$id]);
    }
}
