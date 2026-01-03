<?php
// UPDATED: controllers/admin/SEOController.php
// Replace the entire file

require_once BASE_PATH . '/models/SEO.php';
require_once BASE_PATH . '/models/JsonLdGenerator.php';

class SEOController extends Controller {
    private $seoModel;

    public function __construct() {
        parent::__construct();
        $this->seoModel = new SEO();
    }

    public function index() {
        $this->requireAuth();
        $settings = $this->seoModel->getSettings();
        $this->view('admin/seo/settings', ['settings' => $settings]);
    }

    public function save() {
        $this->requireAuth();
        
        $data = [
            // Basic info
            'site_name_ru' => trim($_POST['site_name_ru']),
            'site_name_uz' => trim($_POST['site_name_uz']),
            'meta_keywords_ru' => trim($_POST['meta_keywords_ru']),
            'meta_keywords_uz' => trim($_POST['meta_keywords_uz']),
            'meta_description_ru' => trim($_POST['meta_description_ru']),
            'meta_description_uz' => trim($_POST['meta_description_uz']),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email']),
            'address_ru' => trim($_POST['address_ru']),
            'address_uz' => trim($_POST['address_uz']),
            'working_hours_ru' => trim($_POST['working_hours_ru']),
            'working_hours_uz' => trim($_POST['working_hours_uz']),
            
            // Location
            'city' => trim($_POST['city'] ?? ''),
            'region' => trim($_POST['region'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'country' => trim($_POST['country'] ?? 'UZ'),
            
            // Organization
            'org_type' => trim($_POST['org_type'] ?? 'LocalBusiness'),
            'org_logo' => trim($_POST['org_logo'] ?? ''),
            'org_description_ru' => trim($_POST['org_description_ru'] ?? ''),
            'org_description_uz' => trim($_POST['org_description_uz'] ?? ''),
            'opening_hours' => trim($_POST['opening_hours'] ?? ''),
            'price_range' => trim($_POST['price_range'] ?? ''),
            
            // Social media
            'social_facebook' => trim($_POST['social_facebook'] ?? ''),
            'social_instagram' => trim($_POST['social_instagram'] ?? ''),
            'social_twitter' => trim($_POST['social_twitter'] ?? ''),
            'social_youtube' => trim($_POST['social_youtube'] ?? ''),
            
            // Service
            'service_type' => trim($_POST['service_type'] ?? ''),
            'service_name_ru' => trim($_POST['service_name_ru'] ?? ''),
            'service_name_uz' => trim($_POST['service_name_uz'] ?? ''),
            'service_desc_ru' => trim($_POST['service_desc_ru'] ?? ''),
            'service_desc_uz' => trim($_POST['service_desc_uz'] ?? ''),
            'area_served' => trim($_POST['area_served'] ?? ''),
            'service_price' => trim($_POST['service_price'] ?? '')
        ];
        
        // Generate JSON-LD schemas
        $data['organization_schema'] = $this->generateOrganizationSchema($data);
        $data['website_schema'] = $this->generateWebsiteSchema($data);
        
        if (!empty($data['service_type'])) {
            $data['service_schema'] = $this->generateServiceSchema($data);
        }
        
        $this->seoModel->updateSettings($data);
        $_SESSION['success'] = 'SEO settings updated successfully';
        
        $this->redirect('/admin/seo');
    }
    
    private function generateOrganizationSchema($data) {
        $socialMedia = array_filter([
            $data['social_facebook'],
            $data['social_instagram'],
            $data['social_twitter'],
            $data['social_youtube']
        ]);
        
        $openingHours = array_filter(explode("\n", $data['opening_hours']));
        
        return JsonLdGenerator::generateOrganization([
            'type' => $data['org_type'],
            'name' => $data['site_name_ru'],
            'url' => BASE_URL,
            'logo' => $data['org_logo'],
            'description' => $data['org_description_ru'],
            'telephone' => $data['phone'],
            'email' => $data['email'],
            'address' => $data['address_ru'],
            'city' => $data['city'],
            'region' => $data['region'],
            'postal' => $data['postal_code'],
            'country' => $data['country'],
            'opening_hours' => $openingHours,
            'price_range' => $data['price_range'],
            'social_media' => $socialMedia
        ]);
    }
    
    private function generateWebsiteSchema($data) {
        return JsonLdGenerator::generateWebsite([
            'name' => $data['site_name_ru'],
            'url' => BASE_URL,
            'description' => $data['meta_description_ru']
        ]);
    }
    
    private function generateServiceSchema($data) {
        return JsonLdGenerator::generateService([
            'service_type' => $data['service_type'],
            'name' => $data['service_name_ru'],
            'description' => $data['service_desc_ru'],
            'provider' => $data['site_name_ru'],
            'area_served' => $data['area_served'],
            'price' => $data['service_price'],
            'currency' => 'UZS'
        ]);
    }
}