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

$canonicalUrl = $page['canonical_url'] ?? (BASE_URL . '/' . e($page['slug']) . ($lang !== DEFAULT_LANGUAGE ? '/' . $lang : ''));

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
    
    $productImages[] = !empty($page['og_image']) ? $page['og_image'] : ($seo['org_logo'] ?? (BASE_URL . '/css/logo.png'));
    
    $pageModel = new Page();
    $attachedMedia = $pageModel->getMedia($page['id']);
    foreach ($attachedMedia as $m) {
        $productImages[] = UPLOAD_URL . $m['filename'];
    }
}

$pageServiceSchema = '';
if (!empty($page["title_$lang"])) {
    $serviceType = $seo['service_type'] ?? 'Service';
    if (!empty($applianceName) && $serviceType === 'Service') {
        $serviceType = $lang === 'ru' ? "Покупка и выкуп $applianceName" : "Sotib olish va sotib olish $applianceName";
    }
    
    $serviceData = [
        'service_type' => $serviceType,
        'name' => replacePlaceholders($page["title_$lang"], $page, $seo),
        'description' => replacePlaceholders($page["meta_description_$lang"] ?? '', $page, $seo),
        'provider' => [
            '@id' => BASE_URL . '#organization'
        ],
        'area_served' => $seo['area_served'] ?? '',
        'service_phone' => $seo['phone'] ?? '',
        'social_media' => array_filter([
            $seo['social_facebook'] ?? '',
            $seo['social_instagram'] ?? '',
            $seo['social_twitter'] ?? '',
            $seo['social_youtube'] ?? ''
        ])
    ];
    
    if (!empty($productImages)) {
        $serviceData['image'] = $productImages;
    }
    
    $pageServiceSchema = JsonLdGenerator::generateService($serviceData);
}
$isAdmin = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
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
    if (!empty($seo['organization_schema'])) {
        $orgSchema = json_decode($seo['organization_schema'], true);
        if (is_array($orgSchema)) {
            if (!empty($orgSchema['description']) && (strpos($orgSchema['description'], "\r\n") !== false || strpos($orgSchema['description'], "\n") !== false)) {
                $orgSchema['description'] = preg_replace('/\s{2,}/', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $orgSchema['description']));
            }
            if (empty($orgSchema['@id'])) {
                $orgSchema['@id'] = BASE_URL . '#organization';
            }
            $orgSchemaJson = json_encode($orgSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } elseif (!empty($seo['site_name_ru']) || !empty($seo['site_name_uz'])) {
        $orgData = [
            'id' => BASE_URL . '#organization',
            'type' => $seo['org_type'] ?? 'LocalBusiness',
            'name' => $seo['org_name_ru'] ?? $seo['site_name_ru'] ?? $seo['site_name_uz'] ?? '',
            'url' => BASE_URL,
            'logo' => $seo['org_logo'] ?? (BASE_URL . '/css/logo.png'),
            'image' => $seo['org_logo'] ?? (BASE_URL . '/css/logo.png'),
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
    }
    if (!empty($orgSchemaJson)) $allSchemas[] = $orgSchemaJson;

    if ($page['slug'] === 'home' && !empty($seo['website_schema'])) {
        $allSchemas[] = $seo['website_schema'];
    }

    if (!empty($pageServiceSchema)) {
        $allSchemas[] = $pageServiceSchema;
    }

    if (!empty($faqSchema)) {
        $allSchemas[] = $faqSchema;
    }

    if ($page['slug'] !== 'home') {
        $pageModel = new Page();
        $breadcrumbPages = $pageModel->getBreadcrumbs($page['id']);
        
        $breadcrumbs = [
            ['name' => $seo["site_name_$lang"], 'url' => '/']
        ];
        
        foreach ($breadcrumbPages as $breadcrumbPage) {
            $breadcrumbs[] = [
                'name' => replacePlaceholders($breadcrumbPage["title_$lang"], $breadcrumbPage, $seo),
                'url' => '/' . $breadcrumbPage['slug'] . ($lang !== DEFAULT_LANGUAGE ? '/' . $lang : '')
            ];
        }
        
        $breadcrumbSchema = JsonLdGenerator::generateBreadcrumbs($breadcrumbs, BASE_URL, $canonicalUrl);
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
                <a href="<?= BASE_URL ?>" class="logo-link">
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
    
    <?php if (!empty($seo['google_review_url'])): ?>
    <a href="<?= e($seo['google_review_url']) ?>" 
       class="floating-review" 
       target="_blank"
       rel="noopener noreferrer"
       title="<?= $lang === 'ru' ? 'Оставить отзыв' : 'Sharh qoldirish' ?>" 
       aria-label="<?= $lang === 'ru' ? 'Оставить отзыв в Google' : 'Google-da sharh qoldirish' ?>">
        <svg fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
    </a>
    <?php endif; ?>
    
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