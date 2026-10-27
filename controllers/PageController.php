<?php
// path: ./controllers/PageController.php 

require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/SEO.php';
require_once BASE_PATH . '/models/FAQ.php';
require_once BASE_PATH . '/models/ContentRotation.php';
require_once BASE_PATH . '/models/Analytics.php';
require_once BASE_PATH . '/models/JsonLdGenerator.php'; 
require_once BASE_PATH . '/models/BlogSchema.php';
require_once BASE_PATH . '/models/PageContactOverride.php';
require_once BASE_PATH . '/models/RotationResolver.php';
require_once BASE_PATH . '/models/PageSeoComposer.php';

class PageController extends Controller {
    private $pageModel;
    private $seoModel;
    private $faqModel;
    private $blogSchemaModel;
    private $rotationResolver;
    private $seoComposer;

    public function __construct() {
        parent::__construct();
        $this->pageModel = new Page();
        $this->seoModel = new SEO();
        $this->faqModel = new FAQ();
        $this->blogSchemaModel = new BlogSchema();
        $this->rotationResolver = new RotationResolver(new ContentRotation(), new Analytics());
        $this->seoComposer = new PageSeoComposer($this->pageModel, new PageContactOverride());
    }

    public function show($slug = 'home', $lang = null) {
        if ($lang) {
            setLanguage($lang);
        }
        
        $currentLang = getCurrentLanguage();
        
        $page = $this->pageModel->getBySlug($slug);
        
        if (!$page) {
            showError(404);
        }
        
        // Fetch
        $seoSettings = $this->seoModel->getSettings();
        $faqs = $this->faqModel->getBySlug($slug);
        $blogSchema = $this->blogSchemaModel->get($slug);

        // Resolve rotation (manual/auto merge)
        $resolved = $this->rotationResolver->resolve($page, $slug, $currentLang);
        $page = $resolved['page'];
        $rotationUsed = $resolved['used'];
        $activeMonth = $resolved['month'];
        $rotationContent = $resolved['rotation'];

        // Compose SEO + JSON-LD + contact UI
        $composed = $this->seoComposer->compose($page, $slug, $currentLang, $seoSettings, $faqs, $_GET);
        $page = $composed['page'];
        $contactUi = $composed['contactUi'];
        $sitewideSchema = $composed['sitewideSchema'];

        trackSiteVisit($currentLang);
        trackVisit($slug, $currentLang);
    
        $botStatus = getBotServiceStatus();
    
        $templateData = [
            'page' => [
                'title' => $page["title_$currentLang"],
                'slug' => $page['slug'],
                'content' => $page["content_$currentLang"],
                'meta_title' => $page["meta_title_$currentLang"],
                'meta_description' => $page["meta_description_$currentLang"],
            ],
            'global' => [
                'phone' => $contactUi['phone'] ?? ($seoSettings['phone'] ?? ''),
                'email' => $contactUi['email'] ?? ($seoSettings['email'] ?? ''),
                'address' => $seoSettings["address_$currentLang"] ?? '',
                'working_hours' => $seoSettings["working_hours_$currentLang"] ?? '',
                'site_name' => $seoSettings["site_name_$currentLang"] ?? '',
            ],
            'seo' => $seoSettings,
            'faqs' => $faqs,
            'lang' => $currentLang,
            'date' => [
                'year' => date('Y'),
                'month' => date('n'),
                'month_name' => date('F'),
                'day' => date('j'),
            ],
            'rotation' => [
                'active' => $rotationUsed,
                'month' => $activeMonth
            ]
        ];
        
        // Media placeholders FIRST: renderTemplate() reads {{media.slot}} as an unknown variable and replaces it with '',
        // which erased every slot (even filled ones) before processMediaPlaceholders() could render the images.
        $page["content_$currentLang"] = processMediaPlaceholders($page["content_$currentLang"], $page['id']);
        $page["content_$currentLang"] = renderTemplate($page["content_$currentLang"], $templateData);
        // Merge custom_css: rotation may override base page custom_css if rotation active
        if ($rotationUsed && !empty($rotationContent['custom_css'])) {
            $page['custom_css'] = $rotationContent['custom_css'];
        }
        // Extract <style> blocks author may have put inside content_ for full control (header/footer)
        // This keeps content body clean but moves CSS to <head> via header.php
        if (!empty($page["content_$currentLang"]) && stripos($page["content_$currentLang"], '<style') !== false) {
            [$clean, $extracted] = extractAndSanitizePageStyles($page["content_$currentLang"]);
            if ($extracted !== '') {
                $page["content_$currentLang"] = $clean;
                $page['custom_css'] = trim(($page['custom_css'] ?? '') . ($page['custom_css'] ? "\n\n" : '') . $extracted);
            }
        }
        // Sanitize custom_css (never block save, just clean vectors)
        if (!empty($page['custom_css'])) {
            $page['custom_css'] = sanitizeCssBlock($page['custom_css']);
        }
        $pageCustomCss = $page['custom_css'] ?? '';
        $page["clientID"] = getGaClientId();
        $data = [
            'page' => $page,
            'pageCustomCss' => $pageCustomCss,
            'seo' => $seoSettings,
            'faqs' => $faqs,
            'blogSchema' => $blogSchema,
            'heroImageSchema' => '', // Cleared because it is now in sitewideSchema
            'sitewideSchema' => $sitewideSchema,
            'contactUi' => $contactUi,
            'lang' => $currentLang,
            'templateData' => $templateData,
            'botModalEnabled' => $botStatus['healthy'],
        ];
        
        $this->view('templates/page', $data);
    }

    public function trackClick() {
        // FIX bfcache: tracking XHR must not poison main document cache. Send no-store only here.
        if (!headers_sent()) header('Cache-Control: no-store, no-cache, must-revalidate');
        $slug = trim((string)($_POST['slug'] ?? ''));
        $lang = normalizeTrackingLanguage($_POST['lang'] ?? getCurrentLanguage());
        $utmSource = trim((string)($_POST['utm_source'] ?? ''));

        if (!trackingRateLimit('click', 200, 300)) {
            $this->json(['success' => false, 'message' => 'Rate limit'], 429);
        }

        if (!$slug || !isValidAnalyticsSlug($slug)) {
            $this->json(['success' => false, 'message' => 'Invalid slug'], 400);
        }

        trackClick($slug, $lang, $utmSource);
        $this->json(['success' => true]);
    }

    public function trackInternalLink() {
        if (!headers_sent()) header('Cache-Control: no-store, no-cache, must-revalidate');
        $fromSlug = trim((string)($_POST['from'] ?? ''));
        $toSlug = trim((string)($_POST['to'] ?? ''));
        $lang = normalizeTrackingLanguage($_POST['lang'] ?? getCurrentLanguage());

        if (!trackingRateLimit('internal_link', 500, 300)) {
            $this->json(['success' => false, 'message' => 'Rate limit'], 429);
        }

        if (!$fromSlug || !$toSlug) {
            $this->json(['success' => false, 'message' => 'Missing params'], 400);
        }
        if (!isValidAnalyticsInternalLinkSlug($fromSlug) || !isValidAnalyticsInternalLinkSlug($toSlug)) {
            $this->json(['success' => false, 'message' => 'Invalid params'], 400);
        }

        trackInternalLink($fromSlug, $toSlug, $lang);
        $this->json(['success' => true]);
    }

    public function trackPhoneCall() {
        if (!headers_sent()) header('Cache-Control: no-store, no-cache, must-revalidate');
        $slug = trim((string)($_POST['slug'] ?? ''));
        $lang = normalizeTrackingLanguage($_POST['lang'] ?? getCurrentLanguage());
        $utmSource = trim((string)($_POST['utm_source'] ?? ''));

        if (!trackingRateLimit('phone_call', 50, 300)) {
            $this->json(['success' => false, 'message' => 'Rate limit'], 429);
        }

        if (!$slug || !isValidAnalyticsSlug($slug)) {
            $this->json(['success' => false, 'message' => 'Invalid slug'], 400);
        }

        trackPhoneCall($slug, $lang, $utmSource);
        $this->json(['success' => true]);
    }

    /** Per-CTA view/click (additive; /track-click and /track-phone-call keep feeding the legacy totals). */
    public function trackCta() {
        if (!headers_sent()) header('Cache-Control: no-store, no-cache, must-revalidate');
        $slug = trim((string)($_POST['slug'] ?? ''));
        $lang = normalizeTrackingLanguage($_POST['lang'] ?? getCurrentLanguage());
        $utmSource = trim((string)($_POST['utm_source'] ?? ''));
        $cta = trim((string)($_POST['cta'] ?? ''));
        $event = trim((string)($_POST['event'] ?? ''));

        if (!trackingRateLimit('cta', 400, 300)) {
            $this->json(['success' => false, 'message' => 'Rate limit'], 429);
        }

        if (!$slug || !isValidAnalyticsSlug($slug)) {
            $this->json(['success' => false, 'message' => 'Invalid slug'], 400);
        }
        if (!CtaStats::isValid($cta, $event)) {
            $this->json(['success' => false, 'message' => 'Invalid cta'], 400);
        }

        trackCta($slug, $lang, $cta, $event, $utmSource);
        $this->json(['success' => true]);
    }
}
