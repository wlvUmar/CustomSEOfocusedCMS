<?php
// path: ./views/templates/article.php

$lang = $lang ?? getCurrentLanguage();
$metaTitle = $article["meta_title_$lang"] ?? $article["title_$lang"];
$metaDescription = $article["meta_description_$lang"] ?? $article["excerpt_$lang"] ?? '';
$ogTitle = $article["og_title_$lang"] ?? $metaTitle;
$ogDescription = $article["og_description_$lang"] ?? $metaDescription;
$baseUrl = siteBaseUrl();
$canonicalUrl = canonicalUrlForArticle($article['id'], $lang);
$ogImage = absoluteUrl(
    $article['og_image']
        ?? (!empty($article['image']) ? ('/uploads/' . $article['image']) : '/css/logo.png'),
    $baseUrl
);

$isAdmin = isset($_SESSION['user_id']) && !isBot();
$brandAuthor = $authorName ?? ($seo["org_name_$lang"] ?? $seo["site_name_$lang"] ?? ($seo["site_name_ru"] ?? ''));
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($metaTitle) ?></title>
    
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="author" content="<?= e($brandAuthor) ?>">
    
    <meta name="robots" content="index, follow">
    
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <link rel="alternate" hreflang="ru" href="<?= canonicalUrlForArticle($article['id'], 'ru') ?>">
    <link rel="alternate" hreflang="uz" href="<?= canonicalUrlForArticle($article['id'], 'uz') ?>">
    <link rel="alternate" hreflang="x-default" href="<?= canonicalUrlForArticle($article['id'], 'ru') ?>">
    
    <!-- OpenGraph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDescription) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:locale" content="<?= $lang === 'ru' ? 'ru_RU' : 'uz_UZ' ?>">
    <meta property="og:site_name" content="<?= e($seo["site_name_$lang"]) ?>">
    <meta property="article:published_time" content="<?= $datePublished ?>">
    <meta property="article:modified_time" content="<?= $dateModified ?>">
    <meta property="article:author" content="<?= e($brandAuthor) ?>">
    <?php if (!empty($article["category_$lang"])): ?>
    <meta property="article:section" content="<?= e($article["category_$lang"]) ?>">
    <?php endif; ?>
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?= $canonicalUrl ?>">
    <meta name="twitter:title" content="<?= e($ogTitle) ?>">
    <meta name="twitter:description" content="<?= e($ogDescription) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">
    
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/css/favicon.ico">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/pages.min.css?v=<?= @filemtime(BASE_PATH . '/public/css/pages.min.css') ?: time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/components.min.css?v=<?= @filemtime(BASE_PATH . '/public/css/components.min.css') ?: time() ?>">
    

    <!-- Sitewide JSON-LD Schemas (Organization + WebSite) -->
    <?php if (!empty($sitewideSchema)): ?>
    <script type="application/ld+json">
    <?= json_encode($sitewideSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT) ?>
    </script>
    <?php endif; ?>
    
    <!-- Article-specific JSON-LD Schema -->
    <?php
    if (!empty($article["jsonld_$lang"])):
        $rawJsonLd = trim((string)$article["jsonld_$lang"]);
        $decoded = json_decode($rawJsonLd, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $rawJsonLd = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT);
        } else {
            // Invalid JSON — do not emit to avoid breaking page; log instead
            error_log('[article jsonld] invalid JSON for article ' . ($article['id'] ?? '?'));
            $rawJsonLd = '';
        }
        if ($rawJsonLd !== ''):
    ?>
    <script type="application/ld+json">
<?= $rawJsonLd ?>
    </script>
    <?php endif; endif; ?>
    
    <?php require BASE_PATH . '/views/partials/article-admin-style.php'; ?>
</head>
<?php $articleSlugClass = 'page-article-' . (int)($article['id'] ?? 0); $articleLangClass='lang-'.($lang ?? 'ru'); $articleBodyClasses=trim($articleSlugClass.' '.$articleLangClass.($isAdmin ? ' admin-mode' : '')); ?>
<body class="<?= e($articleBodyClasses) ?>">
    
    <?php require BASE_PATH . '/views/partials/article-admin-bar.php'; ?>
    
    <?php
    $navLangRu = canonicalUrlForArticle($article['id'], 'ru');
    $navLangUz = canonicalUrlForArticle($article['id'], 'uz');
    require BASE_PATH . '/views/partials/site-nav.php';
    unset($navLangRu, $navLangUz);
    ?>
    
    <main class="article-page">
        <div class="container article-container">
            <article class="unified-article-card">
                <?php require BASE_PATH . '/views/partials/article-hero.php'; ?>

            <?php require BASE_PATH . '/views/partials/article-related.php'; ?>
        </div>
    </main>
        
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= e($seo["site_name_$lang"]) ?>. <?= $lang === 'ru' ? 'Все права защищены' : 'Barcha huquqlar himoyalangan' ?>.</p>
        </div>
    </footer>
    
    <script>
        window.baseUrl = "<?= rtrim(BASE_URL, '/') ?>";
        <?php if (!empty($seo['google_review_url'])): ?>
        window.googleReviewUrl = '<?= e($seo['google_review_url']) ?>';
        window.googleReviewPrompt = <?= json_encode($lang === 'ru' ? 'Спасибо! Не могли бы вы оставить отзыв о нашем сервисе?' : 'Rahmat! Bizning xizmatimiz haqida sharh qoldirasizmi?') ?>;
        <?php endif; ?>
    </script>
    <script src="<?= BASE_URL ?>/js/param-persistence.js" defer></script>
    <script src="<?= BASE_URL ?>/js/link-tracking.js" defer></script>
    
    <?php require BASE_PATH . '/views/partials/article-styles.php'; ?>
    
    </body>
    </html>
        
