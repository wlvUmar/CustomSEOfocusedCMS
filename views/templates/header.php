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

// Process placeholders in all meta content
$metaTitle = renderTemplate($metaTitle, $templateData);
$metaKeywords = renderTemplate($metaKeywords, $templateData);
$metaDescription = renderTemplate($metaDescription, $templateData);
$ogTitle = renderTemplate($ogTitle, $templateData);
$ogDescription = renderTemplate($ogDescription, $templateData);

// Process JSON-LD
$jsonld = $page["jsonld_$lang"] ?? '';
if ($jsonld) {
    $jsonld = renderTemplate($jsonld, $templateData);
}

// Generate FAQ Schema if FAQs exist
$faqSchema = '';
if (!empty($faqs)) {
    $faqSchema = generateFAQSchema($faqs, $lang);
}
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
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/css/favicon.ico">
    
    <!-- Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/pages.css">
    
    <!-- Custom JSON-LD Schema -->
    <?php if ($jsonld): ?>
    <script type="application/ld+json">
    <?= $jsonld ?>
    </script>
    <?php endif; ?>
    
    <!-- FAQ Schema -->
    <?php if ($faqSchema): ?>
    <script type="application/ld+json">
    <?= $faqSchema ?>
    </script>
    <?php endif; ?>
    
    <!-- Organization Schema -->
    <?php if ($seo['phone'] || $seo['email']): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "<?= e($seo["site_name_$lang"]) ?>",
        <?php if ($seo['phone']): ?>"telephone": "<?= e($seo['phone']) ?>",<?php endif; ?>
        <?php if ($seo['email']): ?>"email": "<?= e($seo['email']) ?>",<?php endif; ?>
        <?php if ($seo["address_$lang"]): ?>"address": {
            "@type": "PostalAddress",
            "streetAddress": "<?= e($seo["address_$lang"]) ?>",
            "addressLocality": "Tashkent",
            "addressCountry": "UZ"
        },<?php endif; ?>
        "url": "<?= BASE_URL ?>",
        "priceRange": "$$"
    }
    </script>
    <?php endif; ?>
</head>
<body>
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