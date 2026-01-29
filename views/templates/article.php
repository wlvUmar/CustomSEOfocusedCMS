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
$brandAuthor = $seo["org_name_$lang"] ?? $seo["site_name_$lang"] ?? ($seo["site_name_ru"] ?? ''); // brand-authored articles
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
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/pages.css">
    
    <!-- Sitewide JSON-LD Schemas (Organization + WebSite) -->
    <?php if (!empty($sitewideSchema)): ?>
    <script type="application/ld+json">
    <?= json_encode($sitewideSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <?php endif; ?>
    
    <!-- Article-specific JSON-LD Schema -->
    <?php if (!empty($article["jsonld_$lang"])): ?>
    <script type="application/ld+json">
<?= $article["jsonld_$lang"] ?>
    </script>
    <?php endif; ?>
    
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
    }
    .admin-toolbar a {
        color: white;
        text-decoration: none;
        padding: 6px 12px;
        background: rgba(255,255,255,0.15);
        border-radius: 4px;
        margin-left: 10px;
    }
    body.admin-mode {
        padding-top: 50px;
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
        <span>ADMIN - Article View</span>
        <div>
            <a href="<?= BASE_URL ?>/admin/articles/edit/<?= $article['id'] ?>">Edit Article</a>
            <a href="<?= BASE_URL ?>/admin/articles">All Articles</a>
            <a href="<?= BASE_URL ?>/admin/dashboard">Dashboard</a>
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
                    // Fetch pages for navigation to match main site header
                    require_once BASE_PATH . '/models/Page.php';
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
                        <a href="<?= canonicalUrlForArticle($article['id'], 'ru') ?>" <?= $lang === 'ru' ? 'class="active"' : '' ?>>RU</a>
                        <a href="<?= canonicalUrlForArticle($article['id'], 'uz') ?>" <?= $lang === 'uz' ? 'class="active"' : '' ?>>UZ</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    
    <main class="article-page">
        <div class="container article-container">
            <article class="unified-article-card">
                <!-- Hero Header -->
                <header class="article-card-header">
                    <div class="header-content">
                        
                        <h1><?= e($article["title_$lang"]) ?></h1>
                        
                        <div class="article-meta">
                            <span>
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?= date('d.m.Y', strtotime($article['created_at'])) ?>
                            </span>
                            <span>
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <?= e($article['author']) ?>
                            </span>
                        </div>
                    </div>
                </header>

                <!-- Featured Image -->
                <?php if (!empty($article['image'])): ?>
                    <div class="article-card-image">
                        <img src="<?= BASE_URL ?>/uploads/<?= e($article['image']) ?>" 
                             alt="<?= e($article["title_$lang"]) ?>">
                    </div>
                <?php endif; ?>

                <div class="article-card-body">
                    <?= $article["content_$lang"] ?>
                </div>
            </article>

            <!-- Bottom Links Section -->
            <div class="article-bottom-links">
                <!-- Related Page CTA Banner -->
                <?php if (!empty($relatedPage)): ?>
                <a href="<?= BASE_URL ?>/<?= $relatedPage['slug'] ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>" class="related-page-banner">
                    <div class="rpb-content">
                        <span class="rpb-label"><?= $lang === 'ru' ? 'Связанная услуга:' : 'Bog\'liq xizmat:' ?></span>
                        <span class="rpb-title"><?= e($relatedPage["title_$lang"]) ?></span>
                    </div>
                    <span class="rpb-arrow">
                        <?= $lang === 'ru' ? 'Подробнее' : 'Batafsil' ?> &rarr;
                    </span>
                </a>
                <?php endif; ?>

                <!-- Internal Links Suggestions -->
                <?php if (!empty($internalLinks)): ?>
                    <section class="internal-links-section">
                        <h2><?= $lang === 'ru' ? 'Читайте также' : 'O\'qishni tavsiya qilamiz' ?></h2>
                        <div class="internal-links-grid">
                            <?php foreach ($internalLinks as $link): ?>
                                <a href="<?= BASE_URL ?>/<?= $link['slug'] ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>" class="internal-link-card">
                                    <span class="link-icon">📄</span>
                                    <span class="link-title"><?= e($link['title']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
                
                <?php if (!empty($relatedArticles)): ?>
                    <section class="related-articles-section">
                        <h2><?= $lang === 'ru' ? 'Похожие статьи' : 'O\'xshash maqolalar' ?></h2>
                        <div class="related-articles-grid">
                            <?php foreach ($relatedArticles as $related): ?>
                                <a href="<?= BASE_URL ?>/articles/<?= $related['id'] ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>" 
                                   class="related-article-card">
                                    <?php if (!empty($related['image'])): ?>
                                        <img src="<?= BASE_URL ?>/uploads/<?= e($related['image']) ?>" 
                                             alt="<?= e($related["title_$lang"]) ?>"
                                             loading="lazy">
                                    <?php endif; ?>
                                    <div class="related-article-content">
                                        <h3><?= e($related["title_$lang"]) ?></h3>
                                        <?php if (!empty($related["excerpt_$lang"])): ?>
                                            <p><?= e($related["excerpt_$lang"]) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php if ($seo['phone']): ?>
    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $seo['phone']) ?>" 
       class="floating-call" 
       title="<?= $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq qilish' ?>"
       onclick="trackPhoneCall('article-<?= $article['id'] ?>', '<?= $lang ?>')">
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
    
    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= e($seo["site_name_$lang"]) ?>. <?= $lang === 'ru' ? 'Все права защищены' : 'Barcha huquqlar himoyalangan' ?>.</p>
        </div>
    </footer>
    
    <script>
    function postTracking(endpoint, params) {
        try {
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params).toString(),
                keepalive: true
            }).catch(() => {});
        } catch (e) {}
    }

    function maybeAskForReview() {
        <?php if (!empty($seo['google_review_url'])): ?>
        setTimeout(function() {
            if (confirm('<?= $lang === 'ru' ? 'Спасибо! Не могли бы вы оставить отзыв о нашем сервисе?' : 'Rahmat! Bizning xizmatimiz haqida sharh qoldirasizmi?' ?>')) {
                window.open('<?= e($seo['google_review_url']) ?>', '_blank');
            }
        }, 3000);
        <?php endif; ?>
    }

    function trackClick(slug, lang) {
        postTracking('/track-click', { slug, lang });
        maybeAskForReview();
    }

    function trackPhoneCall(slug, lang) {
        postTracking('/track-phone-call', { slug, lang });
        maybeAskForReview();
    }

    </script>
    
    <style>
    /* Article Page Specific Styles */
    main.article-page {
        padding-top: 3rem !important;
        padding-bottom: 5rem !important;
        position: relative;
        z-index: 1; /* Low z-index base */
    }
    
    .article-container {
        max-width: 1200px; /* Wider */
        padding: 0 15px;
    }

    .unified-article-card {
        background: white;
        border-radius: 20px; /* Slightly less rounded */
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.06);
        margin-bottom: 3rem;
        position: relative;
        z-index: 1; /* Ensure it stays below sticky header (assuming header is > 1) */
        /* Removed transform transition to avoid stacking context issues */
    }
    
    /* Hero Header */
    .article-card-header {
        background-color: #E6E4D0;
        padding: 40px 40px 30px; /* Thinner vertical padding */
        text-align: center;
        border-bottom: 1px solid rgba(0,0,0,0.03);
    }
    
    .header-content {
        max-width: 900px; /* Allow wider text */
        margin: 0 auto;
    }
    
    /* Removed article-badge style */
    
    .article-card-header h1 {
        font-size: 2.5rem; /* Slightly smaller */
        color: #1a1a1c;
        margin: 0 0 1rem;
        line-height: 1.1;
        letter-spacing: -0.5px;
        font-weight: 800;
    }
    
    .article-meta {
        display: flex;
        justify-content: center;
        gap: 24px;
        color: #555;
        font-size: 0.95rem;
        font-weight: 500;
    }
    
    .article-meta span {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    /* Feature Image */
    .article-card-image {
        height: 400px; /* Reduced specific height */
        background: #f0f0f0;
    }
    
    .article-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    
    /* Content Body */
    .article-card-body {
        padding: 50px 60px; /* Reduced vertical padding */
        font-size: 1.15rem;
        line-height: 1.9;
        color: #2c2c2e;
    }

    /* Related Page Banner (CTA) */
    .related-page-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #303034; /* Dark Accent */
        color: #EDEBD7;
        padding: 20px 30px;
        border-radius: 8px;
        margin-bottom: 30px;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(48, 48, 52, 0.2);
        transition: transform 0.2s, box-shadow 0.2s;
        border-left: 6px solid #f5f3e5;
    }

    .related-page-banner:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(48, 48, 52, 0.3);
    }

    .rpb-content {
        display: flex;
        flex-direction: column;
    }

    .rpb-label {
        font-size: 0.8rem;
        text-transform: uppercase;
        opacity: 0.8;
        letter-spacing: 1px;
    }

    .rpb-title {
        font-size: 1.2rem;
        font-weight: bold;
        color: #fff;
    }

    .rpb-arrow {
        font-size: 1.2rem;
        font-weight: bold;
        background: rgba(255,255,255,0.1);
        padding: 8px 16px;
        border-radius: 20px;
        transition: background 0.2s;
    }

    .related-page-banner:hover .rpb-arrow {
        background: rgba(255,255,255,0.2);
    }
    
    /* Typography inside article */
    .article-card-body p {
        margin-bottom: 1.5rem;
    }

    .article-card-body h2 {
        margin-top: 2rem;
        margin-bottom: 1rem;
        font-size: 1.5rem;
        color: #111;
    }
    
    .article-card-body p {
        margin-bottom: 1.5rem;
    }
    
    .article-card-body ul {
        margin-bottom: 1.5rem;
        padding-left: 1.5rem;
    }
    
    .article-card-body a {
        color: #2b6a62;
        text-decoration: underline;
    }

    /* Breadcrumbs override */
    .breadcrumbs {
        margin-bottom: 1rem;
        color: #666;
    }
    .breadcrumbs a {
        color: #444;
        text-decoration: none;
        font-weight: 500;
    }
    .breadcrumbs a:hover { text-decoration: underline; }
    .breadcrumbs .separator { margin: 0 8px; color: #999; }
    .breadcrumbs .current { color: #888; }
    
    /* Responsive */
    @media (max-width: 768px) {
        .article-hero-section {
            padding: 30px 0 80px;
        }
        .article-content-wrapper {
            padding: 24px;
        }
        .article-featured-image {
            margin: -24px -24px 24px -24px;
        }
        .hero-content h1 {
            font-size: 1.8rem;
        }
        .related-page-banner {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        .rpb-arrow {
            align-self: flex-end;
        }
    }

    /* Internal Links Grid */
    .internal-links-section {
        margin-top: 3rem;
        padding-top: 2rem;
        border-top: 1px solid #eee;
    }
    .internal-links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }
    .internal-link-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px;
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 6px;
        text-decoration: none;
        color: #333;
        transition: all 0.2s;
    }
    .internal-link-card:hover {
        background: #fff;
        border-color: #ccc;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    /* Related Articles */
    .related-articles-section {
        margin-top: 3rem;
    }
    .related-articles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .related-article-card {
        display: block;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }
    .related-article-card:hover {
        transform: translateY(-5px);
    }
    .related-article-card img {
        width: 100%;
        height: 160px;
        object-fit: cover;
    }
    .related-article-content {
        padding: 15px;
    }
    .related-article-content h3 {
        font-size: 1.1rem;
        margin: 0 0 10px;
        color: #111;
    }
    .related-article-content p {
        font-size: 0.9rem;
        color: #666;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }</style>
</body>
</html>
