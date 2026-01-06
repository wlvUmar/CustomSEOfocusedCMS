<?php
// path: ./views/templates/header.php
$lang = $lang ?? getCurrentLanguage();

// Meta tags
$metaTitle = $page["meta_title_$lang"] ?? $page["title_$lang"] ?? $seo["site_name_$lang"];
$metaKeywords = $page["meta_keywords_$lang"] ?? $seo["meta_keywords_$lang"] ?? '';
$metaDescription = $page["meta_description_$lang"] ?? $seo["meta_description_$lang"] ?? '';

// Open Graph tags
$ogTitle = $page["og_title_$lang"] ?? $metaTitle;
$ogDescription = $page["og_description_$lang"] ?? $metaDescription;
$ogImage = $page['og_image'] ?? (BASE_URL . '/css/logo.png');

// Canonical URL
$canonicalUrl = $page['canonical_url'] ?? (BASE_URL . '/' . e($page['slug']) . ($lang !== DEFAULT_LANGUAGE ? '/' . $lang : ''));

// Build template data used for meta/template replacements
$templateData = [
    'page' => $page,
    'global' => [
        'phone' => $seo['phone'] ?? '',
        'site_name' => $seo["site_name_$lang"] ?? ''
    ],
    'seo' => $seo,
    'lang' => $lang
];

// Process placeholders in all meta content
$metaTitle = renderTemplate($metaTitle, $templateData);
$metaKeywords = renderTemplate($metaKeywords, $templateData);
$metaDescription = renderTemplate($metaDescription, $templateData);
$ogTitle = renderTemplate($ogTitle, $templateData);
$ogDescription = renderTemplate($ogDescription, $templateData);

// Generate FAQ Schema if FAQs exist
$faqSchema = '';
if (!empty($faqs)) {
    $faqSchema = generateFAQSchema($faqs, $lang);
}

// Generate dynamic Service schema for this page
$pageServiceSchema = '';
if (!empty($page["title_$lang"])) {
    $pageServiceSchema = JsonLdGenerator::generateService([
        'service_type' => $seo['service_type'] ?? 'Service',
        'name' => replacePlaceholders($page["title_$lang"], $page, $seo),
        'description' => replacePlaceholders($page["meta_description_$lang"] ?? '', $page, $seo),
        'provider' => [
            '@id' => BASE_URL . '#organization'
        ],
        'area_served' => $seo['area_served'] ?? '',
        'service_phone' => $seo['phone'] ?? ''
    ]);
}
// Check if user is logged in as admin
$isAdmin = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($metaTitle) ?></title>
    
    <!-- Basic Meta Tags -->
    <?php if ($metaKeywords): ?>
    <meta name="keywords" content="<?= e($metaKeywords) ?>">
    <?php endif; ?>
    
    <?php if ($metaDescription): ?>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= e($seo["site_name_$lang"]) ?>">
    
    <!-- Canonical & Alternate URLs -->
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <link rel="alternate" hreflang="ru" href="<?= BASE_URL ?>/<?= e($page['slug']) ?>">
    <link rel="alternate" hreflang="uz" href="<?= BASE_URL ?>/<?= e($page['slug']) ?>/uz">
    <link rel="alternate" hreflang="x-default" href="<?= BASE_URL ?>/<?= e($page['slug']) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDescription) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:locale" content="<?= $lang === 'ru' ? 'ru_RU' : 'uz_UZ' ?>">
    <meta property="og:site_name" content="<?= e($seo["site_name_$lang"]) ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= $canonicalUrl ?>">
    <meta name="twitter:title" content="<?= e($ogTitle) ?>">
    <meta name="twitter:description" content="<?= e($ogDescription) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">
    
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/css/favicon.ico">
    
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/pages.css">
    
    <?php
    // Global Organization/LocalBusiness Schema (appears on all pages)
    if (!empty($seo['organization_schema'])) {
        $orgSchema = json_decode($seo['organization_schema'], true);
        if (is_array($orgSchema)) {
            // Always enforce @id if not present
            if (empty($orgSchema['@id'])) {
                $orgSchema['@id'] = BASE_URL . '#organization';
            }
            echo '<script type="application/ld+json">' . "\n";
            echo json_encode($orgSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
            echo '</script>' . "\n";
        }
    } elseif (!empty($seo['site_name_ru']) || !empty($seo['site_name_uz'])) {
        // Fallback: generate organization schema from individual fields if full schema absent
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
        echo '<script type="application/ld+json">' . "\n";
        echo JsonLdGenerator::generateOrganization($orgData) . "\n";
        echo '</script>' . "\n";
    }

    // Website Schema (homepage only)
    if ($page['slug'] === 'home' && !empty($seo['website_schema'])) {
        echo '<script type="application/ld+json">' . "\n";
        echo $seo['website_schema'] . "\n";
        echo '</script>' . "\n";
    }

    // Dynamic Page-Specific Service Schema (using page title)
    if (!empty($pageServiceSchema)) {
        echo '<script type="application/ld+json">' . "\n";
        echo $pageServiceSchema . "\n";
        echo '</script>' . "\n";
    }

    // FAQ Schema (if FAQs exist for this page)
    if ($faqSchema) {
        echo '<script type="application/ld+json">' . "\n";
        echo $faqSchema . "\n";
        echo '</script>' . "\n";
    }

    // Breadcrumb Schema (non-homepage pages)
    if ($page['slug'] !== 'home') {
        $breadcrumbs = [
            ['name' => $seo["site_name_$lang"], 'url' => '/'],
            ['name' => $page["title_$lang"], 'url' => '/' . $page['slug'] . ($lang !== DEFAULT_LANGUAGE ? '/' . $lang : '')]
        ];
        
        echo '<script type="application/ld+json">' . "\n";
        echo JsonLdGenerator::generateBreadcrumbs($breadcrumbs, BASE_URL) . "\n";
        echo '</script>' . "\n";
    }
    ?>
    
    <?php if ($isAdmin): ?>
    <!-- Admin Toolbar Styles -->
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
    <!-- Admin Toolbar -->
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
    <!-- Floating Call Button -->
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
    
    <script>
    function trackClick(slug, lang) {
        fetch('<?= BASE_URL ?>/track-click', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'slug=' + slug + '&lang=' + lang
        });
    }
    </script>