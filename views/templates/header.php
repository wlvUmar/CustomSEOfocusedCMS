<?php
require_once BASE_PATH . '/models/Page.php';
require_once BASE_PATH . '/models/JsonLdGenerator.php';

$lang = $lang ?? getCurrentLanguage();

$metaTitle = $page["meta_title_$lang"] ?? $page["title_$lang"] ?? $seo["site_name_$lang"];
$metaKeywords = $page["meta_keywords_$lang"] ?? $seo["meta_keywords_$lang"] ?? '';
$metaDescription = $page["meta_description_$lang"] ?? $seo["meta_description_$lang"] ?? '';

$ogTitle = $page["og_title_$lang"] ?? $metaTitle;
$ogDescription = $page["og_description_$lang"] ?? $metaDescription;
$ogImage = $page['og_image'] ?? (BASE_URL . '/css/logo.png');

$baseUrl = BASE_URL;
if (strpos($baseUrl, '://') === false) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . rtrim($host, '/');
}
$baseUrl = rtrim($baseUrl, '/');

$canonicalUrl = $page['canonical_url'] ?? ($baseUrl . '/' . $page['slug'] . ($lang !== DEFAULT_LANGUAGE ? '/' . $lang : ''));

$templateData = [
    'page' => $page,
    'global' => [
        'phone' => $seo['phone'] ?? '',
        'site_name' => $seo["site_name_$lang"] ?? ''
    ],
    'seo' => $seo,
    'lang' => $lang
];

$metaTitle = renderTemplate($metaTitle, $templateData);
$metaKeywords = renderTemplate($metaKeywords, $templateData);
$metaDescription = renderTemplate($metaDescription, $templateData);
$ogTitle = renderTemplate($ogTitle, $templateData);
$ogDescription = renderTemplate($ogDescription, $templateData);

$faqSchema = '';
if (!empty($faqs)) {
    $faqSchema = generateFAQSchema($faqs, $lang, $canonicalUrl);
}

$applianceName = '';
$productImages = []; 
if (!empty($page["title_$lang"])) {
    $titleProcessed = replacePlaceholders($page["title_$lang"], $page, $seo);
    if (preg_match('/(?:продать|скупка|выкуп)\s+([а-яёa-z\s]+?)(?:\s+быстро|$)/ui', $titleProcessed, $matches)) {
        $applianceName = trim($matches[1]);
    }
    
    $productImages[] = !empty($page['og_image']) ? $page['og_image'] : ($seo['org_logo'] ?? ($baseUrl . '/css/logo.png'));
    
    $pageModel = new Page();
    $attachedMedia = $pageModel->getMedia($page['id']);
    foreach ($attachedMedia as $m) {
        $productImages[] = $baseUrl . '/uploads/' . $m['filename'];
    }
}

$pageServiceSchema = '';
$isAdmin = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <?php if (defined('GTM_ID')): ?>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?= GTM_ID ?>');</script>
    <!-- End Google Tag Manager -->
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($metaTitle) ?></title>
    
    <?php if ($metaKeywords): ?>
    <meta name="keywords" content="<?= e($metaKeywords) ?>">
    <?php endif; ?>
    
    <?php if ($metaDescription): ?>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= e($seo["site_name_$lang"]) ?>">
    
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <link rel="alternate" hreflang="ru" href="<?= BASE_URL ?>/<?= e($page['slug']) ?>">
    <link rel="alternate" hreflang="uz" href="<?= BASE_URL ?>/<?= e($page['slug']) ?>/uz">
    <link rel="alternate" hreflang="x-default" href="<?= BASE_URL ?>/<?= e($page['slug']) ?>">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDescription) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:locale" content="<?= $lang === 'ru' ? 'ru_RU' : 'uz_UZ' ?>">
    <meta property="og:site_name" content="<?= e($seo["site_name_$lang"]) ?>">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= $canonicalUrl ?>">
    <meta name="twitter:title" content="<?= e($ogTitle) ?>">
    <meta name="twitter:description" content="<?= e($ogDescription) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">
    
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/css/favicon.ico">
    
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/pages.css">
    
    <?php
    $allSchemas = [];
    
$orgSchemaJson = '';
$orgSchemaData = null;
if (!empty($seo['organization_schema'])) {
    $orgSchema = json_decode($seo['organization_schema'], true);
    if (is_array($orgSchema)) {
            if (!empty($orgSchema['description']) && (strpos($orgSchema['description'], "\r\n") !== false || strpos($orgSchema['description'], "\n") !== false)) {
                $orgSchema['description'] = preg_replace('/\s{2,}/', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $orgSchema['description']));
            }
        if (empty($orgSchema['@id'])) {
            $orgSchema['@id'] = $baseUrl . '#organization';
        }
        $orgSchemaData = $orgSchema;
        $orgSchemaJson = json_encode($orgSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
} elseif (!empty($seo['site_name_ru']) || !empty($seo['site_name_uz'])) {
        $orgData = [
            'id' => $baseUrl . '#organization',
            'type' => $seo['org_type'] ?? 'LocalBusiness',
            'name' => $seo['org_name_ru'] ?? $seo['site_name_ru'] ?? $seo['site_name_uz'] ?? '',
            'url' => $baseUrl,
            'logo' => $seo['org_logo'] ?? ($baseUrl . '/css/logo.png'),
            'image' => $seo['org_logo'] ?? ($baseUrl . '/css/logo.png'),
            'description' => $seo['org_description_ru'] ?? $seo['org_description_uz'] ?? '',
            'telephone' => $seo['phone'] ?? '',
            'email' => $seo['email'] ?? '',
            'address' => $seo['address_ru'] ?? $seo['address_uz'] ?? '',
            'city' => $seo['city'] ?? 'Tashkent',
            'region' => $seo['region'] ?? 'Tashkent',
            'postal' => $seo['postal_code'] ?? '',
            'country' => $seo['country'] ?? 'UZ',
            'latitude' => $seo['org_latitude'] ?? null,
            'longitude' => $seo['org_longitude'] ?? null,
            'opening_hours' => !empty($seo['opening_hours']) ? explode("\n", $seo['opening_hours']) : [],
            'price_range' => $seo['price_range'] ?? '',
            'social_media' => array_filter([$seo['social_facebook'] ?? '', $seo['social_instagram'] ?? '', $seo['social_twitter'] ?? '', $seo['social_youtube'] ?? ''])
        ];
        $orgSchemaJson = JsonLdGenerator::generateOrganization($orgData);
        $orgSchemaData = json_decode($orgSchemaJson, true);
    }
    if (!empty($orgSchemaJson)) $allSchemas[] = $orgSchemaJson;

    $serviceSocial = array_filter([
        $seo['social_facebook'] ?? '',
        $seo['social_instagram'] ?? '',
        $seo['social_twitter'] ?? '',
        $seo['social_youtube'] ?? ''
    ]);
    if (!empty($orgSchemaData['sameAs']) && is_array($orgSchemaData['sameAs'])) {
        $serviceSocial = array_merge($serviceSocial, $orgSchemaData['sameAs']);
    }
    $serviceSocial = array_values(array_unique(array_filter($serviceSocial)));

    $pageDepth = isset($page['depth']) ? (int)$page['depth'] : 0;
    if (!empty($page["title_$lang"]) && $pageDepth < 2) {
        $serviceType = $seo['service_type'] ?? 'Service';
        if (!empty($applianceName) && $serviceType === 'Service') {
            $serviceType = $lang === 'ru' ? "Покупка и выкуп $applianceName" : "Sotib olish va sotib olish $applianceName";
        }
        
        $serviceData = [
            'service_type' => $serviceType,
            'name' => replacePlaceholders($page["title_$lang"], $page, $seo),
            'description' => replacePlaceholders($page["meta_description_$lang"] ?? '', $page, $seo),
            'provider' => [
                '@id' => $baseUrl . '#organization'
            ],
            'area_served' => $seo['area_served'] ?? '',
            'service_phone' => $seo['phone'] ?? '',
            'social_media' => $serviceSocial
        ];
        
        if (!empty($productImages)) {
            $serviceData['image'] = $productImages;
        }
        
        $pageServiceSchema = JsonLdGenerator::generateService($serviceData);
    }

    if (in_array($page['slug'], ['home', 'main'], true) && !empty($seo['website_schema'])) {
        $websiteSchema = json_decode($seo['website_schema'], true);
        if (is_array($websiteSchema) && empty($websiteSchema['url'])) {
            $websiteSchema['url'] = $baseUrl;
            $allSchemas[] = json_encode($websiteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $allSchemas[] = $seo['website_schema'];
        }
    }

if (!empty($pageServiceSchema)) {
    $allSchemas[] = $pageServiceSchema;
}

    if (!empty($faqSchema)) {
        $allSchemas[] = $faqSchema;
    }

    // Add hero image schema if provided by controller
    if (!empty($heroImageSchema)) {
        $allSchemas[] = $heroImageSchema;
    }

    if (!in_array($page['slug'], ['home', 'main'], true)) {
        $pageModel = new Page();
        $breadcrumbPages = $pageModel->getBreadcrumbs($page['id']);
        
        $breadcrumbs = [
            ['name' => $seo["site_name_$lang"], 'url' => '/']
        ];
        
        foreach ($breadcrumbPages as $breadcrumbPage) {
            if (in_array($breadcrumbPage['slug'], ['home', 'main'], true)) {
                continue;
            }
            $breadcrumbs[] = [
                'name' => replacePlaceholders($breadcrumbPage["title_$lang"], $breadcrumbPage, $seo),
                'url' => '/' . $breadcrumbPage['slug'] . ($lang !== DEFAULT_LANGUAGE ? '/' . $lang : '')
            ];
        }
        
        $breadcrumbSchema = JsonLdGenerator::generateBreadcrumbs($breadcrumbs, $baseUrl, $canonicalUrl);
        if (!empty($breadcrumbSchema)) $allSchemas[] = $breadcrumbSchema;
    }
    
    if (!empty($blogSchema)) {
        if (is_array($blogSchema)) {
             $allSchemas[] = json_encode($blogSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } else {
             $allSchemas[] = $blogSchema;
        }
    }
    
    if (!empty($allSchemas)) {
        $mergedSchema = JsonLdGenerator::mergeSchemas($allSchemas);
        if (!empty($mergedSchema)) {
            echo '<script type="application/ld+json">' . "\n";
            echo $mergedSchema . "\n";
            echo '</script>' . "\n";
        }
    }
    ?>
    
    <?php if ($isAdmin): ?>
    <style>
    .admin-toolbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
        padding: 8px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 9999;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        font-size: 13px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .admin-toolbar-left,
    .admin-toolbar-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .admin-toolbar a,
    .admin-toolbar button {
        color: white;
        text-decoration: none;
        padding: 6px 12px;
        background: rgba(255,255,255,0.15);
        border-radius: 4px;
        border: 1px solid rgba(255,255,255,0.2);
        font-size: 13px;
        font-weight: 500;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        white-space: nowrap;
    }
    .admin-toolbar a:hover,
    .admin-toolbar button:hover {
        background: rgba(255,255,255,0.25);
        transform: translateY(-1px);
    }
    .admin-toolbar-badge {
        background: rgba(255,255,255,0.3);
        padding: 4px 8px;
        border-radius: 3px;
        font-weight: 600;
        font-size: 12px;
    }
    body.admin-mode {
        padding-top: 50px;
    }
    @media (max-width: 768px) {
        .admin-toolbar {
            padding: 6px 10px;
            font-size: 12px;
        }
        .admin-toolbar a,
        .admin-toolbar button {
            padding: 5px 10px;
            font-size: 12px;
        }
        body.admin-mode {
            padding-top: 55px;
        }
    }
    </style>
    <?php endif; ?>
</head>
<body<?= $isAdmin ? ' class="admin-mode"' : '' ?>>
    <?php if (defined('GTM_ID')): ?>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= GTM_ID ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <?php endif; ?>
    
    <?php if ($isAdmin): ?>
    <div class="admin-toolbar">
        <div class="admin-toolbar-left">
            <span class="admin-toolbar-badge">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20" style="vertical-align: middle;">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                ADMIN
            </span>
            <span style="opacity: 0.9; font-size: 12px;">
                <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
            </span>
        </div>
        
        <div class="admin-toolbar-right">
            <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
            
            <?php if ($page['enable_rotation']): ?>
            <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Rotate
            </a>
            <?php endif; ?>
            
            <a href="<?= BASE_URL ?>/admin/analytics/page/<?= e($page['slug']) ?>">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Stats
            </a>
            
            <a href="<?= BASE_URL ?>/admin/dashboard">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>
            
            <button onclick="if(confirm('Logout?')) window.location='<?= BASE_URL ?>/admin/logout'" style="background: rgba(255,255,255,0.2);">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Logout
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <header>
        <div class="container">
            <nav>
                <a href="<?= $baseUrl ?>/" class="logo-link">
                    <img src="<?= BASE_URL ?>/css/logo.png" class="logo" alt="<?= e($seo["site_name_$lang"]) ?>">
                    <span class="site-name"><?= e($seo["site_name_$lang"]) ?></span>
                </a>
                
                <div class="nav-links">
                    <?php
                    $pageModel = new Page();
                    $allPages = $pageModel->getAll();
                    foreach ($allPages as $navPage):
                        if ($navPage['slug'] === 'about' || $navPage['slug'] === 'o-nas'):
                    ?>
                    <a href="<?= BASE_URL ?>/<?= e($navPage['slug']) ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>">
                        <?= e($navPage["title_$lang"]) ?>
                    </a>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                    
                    <div class="lang-switch">
                        <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>" <?= $lang === 'ru' ? 'class="active"' : '' ?>>RU</a>
                        <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>/uz" <?= $lang === 'uz' ? 'class="active"' : '' ?>>UZ</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    
    <?php if ($seo['phone']): ?>
    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $seo['phone']) ?>" 
       class="floating-call" 
       title="<?= $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq qilish' ?>" 
       aria-label="<?= $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq qilish' ?>"
       onclick="trackClick('<?= e($page['slug']) ?>', '<?= $lang ?>')">
        <svg fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
        </svg>
    </a>
    <?php endif; ?>

    <a href="https://t.me/azimjumayev"
       class="floating-telegram"
       target="_blank"
       rel="noopener noreferrer"
       title="<?= $lang === 'ru' ? 'Написать в Telegram' : 'Telegramda yozish' ?>"
       aria-label="<?= $lang === 'ru' ? 'Написать в Telegram' : 'Telegramda yozish' ?>">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M22.05 1.577c-.393-.016-.784.08-1.117.235-.484.186-4.92 1.902-9.41 3.64-2.26.873-4.518 1.746-6.256 2.415-1.737.67-3.045 1.168-3.114 1.192-.46.16-1.082.362-1.61.984-.133.155-.267.354-.335.628s-.038.622.095.895c.265.547.714.773 1.244.976 1.76.564 3.58 1.102 5.087 1.608.556 1.96 1.09 3.927 1.618 5.89.174.394.553.54.944.544l-.002.02s.307.03.606-.042c.3-.07.677-.244 1.02-.565.377-.354 1.4-1.36 1.98-1.928l4.37 3.226.035.02s.484.34 1.192.388c.354.024.82-.044 1.22-.337.403-.294.67-.767.795-1.307.374-1.63 2.853-13.427 3.276-15.38l-.012.046c.296-1.1.187-2.108-.496-2.705-.342-.297-.736-.427-1.13-.444zm-.118 1.874c.027.025.025.025.002.027-.007-.002.08.118-.09.755l-.007.024-.005.022c-.432 1.997-2.936 13.9-3.27 15.356-.046.196-.065.182-.054.17-.1-.015-.285-.094-.3-.1l-7.48-5.525c2.562-2.467 5.182-4.7 7.827-7.08.468-.235.39-.96-.17-.972-.594.14-1.095.567-1.64.84-3.132 1.858-6.332 3.492-9.43 5.406-1.59-.553-3.177-1.012-4.643-1.467 1.272-.51 2.283-.886 3.278-1.27 1.738-.67 3.996-1.54 6.256-2.415 4.522-1.748 9.07-3.51 9.465-3.662l.032-.013.03-.013c.11-.05.173-.055.202-.057 0 0-.01-.033-.002-.026zM10.02 16.016l1.234.912c-.532.52-1.035 1.01-1.398 1.36z"/>
        </svg>
    </a>
    
    
    <script>
    function trackClick(slug, lang) {
        fetch('<?= BASE_URL ?>/track-click', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'slug=' + slug + '&lang=' + lang
        });
        
        <?php if (!empty($seo['google_review_url'])): ?>
        setTimeout(function() {
            if (confirm('<?= $lang === 'ru' ? 'Спасибо! Не могли бы вы оставить отзыв о нашем сервисе?' : 'Rahmat! Bizning xizmatimiz haqida sharh qoldirasizmi?' ?>')) {
                window.open('<?= e($seo['google_review_url']) ?>', '_blank');
            }
        }, 3000);
        <?php endif; ?>
    }
    </script>
