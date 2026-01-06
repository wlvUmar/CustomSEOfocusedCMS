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
            'org_name_ru' => trim($_POST['org_name_ru'] ?? ''),
            'org_name_uz' => trim($_POST['org_name_uz'] ?? ''),
            'org_logo' => trim($_POST['org_logo'] ?? ''),
            // Clean descriptions: remove line breaks at input time
            'org_description_ru' => $this->cleanDescription($_POST['org_description_ru'] ?? ''),
            'org_description_uz' => $this->cleanDescription($_POST['org_description_uz'] ?? ''),
            'opening_hours' => trim($_POST['opening_hours'] ?? ''),
            'price_range' => trim($_POST['price_range'] ?? ''),
            
            // Social media
            'social_facebook' => trim($_POST['social_facebook'] ?? ''),
            'social_instagram' => trim($_POST['social_instagram'] ?? ''),
            'social_twitter' => trim($_POST['social_twitter'] ?? ''),
            'social_youtube' => trim($_POST['social_youtube'] ?? ''),
            
            // Service (global settings for auto-generation)
            'service_type' => trim($_POST['service_type'] ?? 'Service'),
            'area_served' => trim($_POST['area_served'] ?? ''),
            // Organization extras
            'org_latitude' => trim($_POST['org_latitude'] ?? ''),
            'org_longitude' => trim($_POST['org_longitude'] ?? ''),
            'org_schema_custom' => isset($_POST['org_schema_custom']) ? 1 : 0,
            'organization_schema_raw' => trim($_POST['organization_schema_raw'] ?? '')
        ];
        
        // Generate JSON-LD schemas
        $data['organization_schema'] = $this->generateOrganizationSchema($data);
        $data['website_schema'] = $this->generateWebsiteSchema($data);
        
        // No longer generating static service_schema - it's now dynamic per page
        
        $this->seoModel->updateSettings($data);
        $_SESSION['success'] = 'SEO settings updated successfully';
        
        $this->redirect('/admin/seo');
    }
    
    private function cleanDescription($text) {
        // Remove line breaks and normalize whitespace
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);
        return trim($text);
    }
    
    private function generateOrganizationSchema($data) {
        // If admin provided a custom JSON-LD and enabled the toggle, validate and use it as-is (but enforce @id)
        if (!empty($data['org_schema_custom']) && !empty($data['organization_schema_raw'])) {
            $decoded = json_decode($data['organization_schema_raw'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $decoded['@id'] = BASE_URL . '#organization';
                return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            }
            // If invalid JSON was provided, fall back to auto-generation so site remains valid
        }
        
        $socialMedia = array_filter([
            $data['social_facebook'],
            $data['social_instagram'],
            $data['social_twitter'],
            $data['social_youtube']
        ]);
        
        $openingHours = array_filter(explode("\n", $data['opening_hours']));
        
        return JsonLdGenerator::generateOrganization([
            'id' => BASE_URL . '#organization',
            'type' => $data['org_type'],
            'name' => !empty($data['org_name_ru']) ? $data['org_name_ru'] : $data['site_name_ru'],
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
            'social_media' => $socialMedia,
            'latitude' => $data['org_latitude'] ?? null,
            'longitude' => $data['org_longitude'] ?? null
        ]);
    }
    
    private function generateWebsiteSchema($data) {
        return JsonLdGenerator::generateWebsite([
            'name' => $data['site_name_ru'],
            'url' => BASE_URL,
            'description' => $data['meta_description_ru']
        ]);
    }
}