<?php
class SEO {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getSettings() {
        $sql = "SELECT * FROM seo_settings ORDER BY id DESC LIMIT 1";
        return $this->db->fetchOne($sql);
    }

    public function updateSettings($data) {
        $sql = "UPDATE seo_settings SET 
                site_name_ru = ?, site_name_uz = ?,
                meta_keywords_ru = ?, meta_keywords_uz = ?,
                meta_description_ru = ?, meta_description_uz = ?,
                phone = ?, email = ?,
                address_ru = ?, address_uz = ?,
                working_hours_ru = ?, working_hours_uz = ?
                WHERE id = 1";
        
        return $this->db->query($sql, [
            $data['site_name_ru'],
            $data['site_name_uz'],
            $data['meta_keywords_ru'],
            $data['meta_keywords_uz'],
            $data['meta_description_ru'],
            $data['meta_description_uz'],
            $data['phone'],
            $data['email'],
            $data['address_ru'],
            $data['address_uz'],
            $data['working_hours_ru'],
            $data['working_hours_uz']
        ]);
    }
}