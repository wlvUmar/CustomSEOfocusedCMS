<?php
// UPDATED: models/SEO.php
// Replace the entire file

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
                working_hours_ru = ?, working_hours_uz = ?,
                city = ?, region = ?, postal_code = ?, country = ?,
                org_type = ?, org_name_ru = ?, org_name_uz = ?, org_logo = ?,
                org_description_ru = ?, org_description_uz = ?,
                opening_hours = ?, price_range = ?,
                social_facebook = ?, social_instagram = ?,
                social_twitter = ?, social_youtube = ?,
                service_type = ?, area_served = ?,
                org_latitude = ?, org_longitude = ?,
                organization_schema = ?, website_schema = ?
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
            $data['working_hours_uz'],
            $data['city'] ?? '',
            $data['region'] ?? '',
            $data['postal_code'] ?? '',
            $data['country'] ?? 'UZ',
            $data['org_type'] ?? 'LocalBusiness',
            $data['org_name_ru'] ?? '',
            $data['org_name_uz'] ?? '',
            $data['org_logo'] ?? '',
            $data['org_description_ru'] ?? '',
            $data['org_description_uz'] ?? '',
            $data['opening_hours'] ?? '',
            $data['price_range'] ?? '',
            $data['social_facebook'] ?? '',
            $data['social_instagram'] ?? '',
            $data['social_twitter'] ?? '',
            $data['social_youtube'] ?? '',
            $data['service_type'] ?? 'Service',
            $data['area_served'] ?? '',
            $data['org_latitude'] ?? '',
            $data['org_longitude'] ?? '',
            $data['organization_schema'] ?? '',
            $data['website_schema'] ?? ''
        ]);
    }
}