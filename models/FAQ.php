<?php
// path: ./models/FAQ.php

class FAQ {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getBySlug($slug) {
        $sql = "SELECT * FROM faqs WHERE page_slug = ? AND is_active = 1 ORDER BY sort_order ASC";
        return $this->db->fetchAll($sql, [$slug]);
    }

    public function getAll() {
        $sql = "SELECT * FROM faqs ORDER BY page_slug, sort_order ASC";
        return $this->db->fetchAll($sql);
    }

    public function getById($id) {
        $sql = "SELECT * FROM faqs WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function create($data) {
        // 06-05: guard orphan + UZ fallback — bilingual parity
        $slug = trim((string)($data['page_slug'] ?? ''));
        if ($slug === '') throw new InvalidArgumentException('page_slug is required');
        $pageExists = $this->db->fetchOne("SELECT id FROM pages WHERE slug = ? LIMIT 1", [$slug]);
        if (!$pageExists) throw new InvalidArgumentException('page_slug "' . $slug . '" does not exist — FAQ would be orphan');
        // UZ fallback to RU if empty (prevents blank in UZ rendering page.php:223)
        $qUz = trim((string)($data['question_uz'] ?? ''));
        $aUz = trim((string)($data['answer_uz'] ?? ''));
        if ($qUz === '') $data['question_uz'] = $data['question_ru'] ?? '';
        if ($aUz === '') $data['answer_uz'] = $data['answer_ru'] ?? '';
        $sql = "INSERT INTO faqs (page_slug, question_ru, question_uz, answer_ru, answer_uz, sort_order, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $data['page_slug'],
            $data['question_ru'],
            $data['question_uz'],
            $data['answer_ru'],
            $data['answer_uz'],
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        // 06-05: same guards on update
        $slug = trim((string)($data['page_slug'] ?? ''));
        if ($slug !== '') {
            $pageExists = $this->db->fetchOne("SELECT id FROM pages WHERE slug = ? LIMIT 1", [$slug]);
            if (!$pageExists) throw new InvalidArgumentException('page_slug "' . $slug . '" does not exist');
        }
        $qUz = trim((string)($data['question_uz'] ?? ''));
        $aUz = trim((string)($data['answer_uz'] ?? ''));
        if ($qUz === '' && isset($data['question_ru'])) $data['question_uz'] = $data['question_ru'];
        if ($aUz === '' && isset($data['answer_ru'])) $data['answer_uz'] = $data['answer_ru'];
        $sql = "UPDATE faqs SET page_slug = ?, question_ru = ?, question_uz = ?, 
                answer_ru = ?, answer_uz = ?, sort_order = ?, is_active = ? 
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $data['page_slug'],
            $data['question_ru'],
            $data['question_uz'],
            $data['answer_ru'],
            $data['answer_uz'],
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1,
            $id
        ]);
    }

    public function delete($id) {
        $sql = "DELETE FROM faqs WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }

    public function getUniqueSlugs() {
        $sql = "SELECT DISTINCT page_slug FROM faqs ORDER BY page_slug";
        return $this->db->fetchAll($sql);
    }
}