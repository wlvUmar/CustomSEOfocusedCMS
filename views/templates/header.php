<?php
$lang = $lang ?? getCurrentLanguage();
$metaTitle = $page["meta_title_$lang"] ?? $page["title_$lang"] ?? $seo["site_name_$lang"];
$metaKeywords = $page["meta_keywords_$lang"] ?? $seo["meta_keywords_$lang"] ?? '';
$metaDescription = $page["meta_description_$lang"] ?? $seo["meta_description_$lang"] ?? '';

// Process placeholders
$metaTitle = replacePlaceholders($metaTitle, $page, $seo);
$metaKeywords = replacePlaceholders($metaKeywords, $page, $seo);
$metaDescription = replacePlaceholders($metaDescription, $page, $seo);

// Process JSON-LD
$jsonld = $page["jsonld_$lang"] ?? '';
if ($jsonld) {
    $jsonld = replacePlaceholders($jsonld, $page, $seo);
}
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
    <link rel="canonical" href="<?= BASE_URL ?>/<?= e($page['slug']) ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>">
    
    <?php if ($jsonld): ?>
    <script type="application/ld+json">
    <?= $jsonld ?>
    </script>
    <?php endif; ?>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        header { background: #f8f9fa; padding: 20px 0; border-bottom: 1px solid #dee2e6; }
        nav { display: flex; gap: 20px; align-items: center; }
        nav a { color: #333; text-decoration: none; }
        nav a:hover { color: #007bff; }
        .lang-switch { margin-left: auto; }
        main { padding: 40px 0; min-height: 60vh; }
        footer { background: #343a40; color: #fff; padding: 30px 0; margin-top: 40px; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="<?= BASE_URL ?>"><strong><?= e($seo["site_name_$lang"]) ?></strong></a>
                <?php
                $pageModel = new Page();
                $allPages = $pageModel->getAll();
                foreach ($allPages as $navPage):
                    if ($navPage['slug'] !== 'home'):
                ?>
                <a href="<?= BASE_URL ?>/<?= e($navPage['slug']) ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>">
                    <?= e($navPage["title_$lang"]) ?>
                </a>
                <?php endif; endforeach; ?>
                
                <div class="lang-switch">
                    <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>">RU</a> |
                    <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>/uz">UZ</a>
                </div>
            </nav>
        </div>
    </header>