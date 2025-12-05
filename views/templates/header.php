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
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%23303034'/%3E%3Ctext x='50' y='50' font-size='60' text-anchor='middle' dominant-baseline='central' fill='%23EDEBD7' font-family='Arial, sans-serif' font-weight='bold'%3E₸%3C/text%3E%3C/svg%3E">
    
    <?php if ($jsonld): ?>
    <script type="application/ld+json">
    <?= $jsonld ?>
    </script>
    <?php endif; ?>
    
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        :root {
            --primary-dark: #303034;
            --primary-light: #EDEBD7;
            --accent-dark: #3f3f44;
            --accent-light: #f5f3e5;
            --text-dark: #1a1a1c;
            --text-muted: #6b6b70;
            --success: #059669;
            --success-hover: #047857;
        }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6; 
            color: var(--text-dark);
            background: var(--primary-light);
            font-size: 16px;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 16px;
        }
        
        /* Header Styles */
        header { 
            background: var(--primary-dark);
            padding: 10px 0;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        nav { 
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        
        .logo-link {
            display: flex;
            align-items: center;
            text-decoration: none;
            flex-shrink: 0;
        }
        
        .logo {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: bold;
            color: var(--primary-dark);
            flex-shrink: 0;
        }
        
        .site-name {
            margin-left: 10px;
            color: var(--primary-light);
            font-size: 1em;
            font-weight: 600;
            display: none;
        }
        
        .nav-links {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-left: auto;
        }
        
        .nav-links a { 
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            font-size: 0.9em;
            padding: 6px 10px;
            border-radius: 6px;
            white-space: nowrap;
        }
        
        .nav-links a:hover { 
            background: var(--accent-dark);
            color: #fff;
        }
        
        .lang-switch { 
            display: flex;
            gap: 4px;
            align-items: center;
            background: var(--accent-dark);
            padding: 3px;
            border-radius: 6px;
            flex-shrink: 0;
        }
        
        .lang-switch a {
            padding: 5px 12px !important;
            border-radius: 4px;
            background: transparent;
            color: var(--primary-light);
            text-decoration: none;
            font-size: 0.8em;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .lang-switch a:hover,
        .lang-switch a.active {
            background: var(--primary-light);
            color: var(--primary-dark);
        }
        
        /* Main Content */
        main { 
            padding: 32px 0;
            min-height: 60vh;
            background: #fff;
        }
        
        main .container {
            max-width: 900px;
        }
        
        /* Content Typography */
        main h1 {
            color: var(--text-dark);
            font-size: 1.75em;
            margin-bottom: 16px;
            line-height: 1.3;
        }
        
        main h2 {
            color: var(--text-dark);
            font-size: 1.4em;
            margin: 28px 0 12px;
        }
        
        main h3 {
            color: var(--text-dark);
            font-size: 1.15em;
            margin: 20px 0 10px;
        }
        
        main p {
            margin-bottom: 16px;
            color: var(--text-muted);
            font-size: 1em;
            line-height: 1.7;
        }
        
        main ul, main ol {
            margin: 16px 0 16px 20px;
            color: var(--text-muted);
        }
        
        main li {
            margin-bottom: 10px;
            line-height: 1.7;
        }
        
        main a {
            color: var(--primary-dark);
            text-decoration: none;
            border-bottom: 1px solid var(--primary-dark);
            transition: opacity 0.2s;
        }
        
        main a:hover {
            opacity: 0.7;
        }
        
        /* Footer Styles */
        footer { 
            background: var(--primary-dark);
            color: var(--primary-light);
            padding: 32px 0 20px;
            margin-top: 40px;
        }
        
        footer .footer-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        footer h3 {
            color: #fff;
            font-size: 1.1em;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        footer p {
            margin-bottom: 10px;
            line-height: 1.6;
            color: var(--primary-light);
            font-size: 0.95em;
        }
        
        footer a {
            color: var(--accent-light);
            text-decoration: none;
            transition: opacity 0.2s;
        }
        
        footer a:hover {
            opacity: 0.8;
        }
        
        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .contact-item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            color: var(--accent-light);
            margin-top: 2px;
        }
        
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--accent-dark);
            color: rgba(237, 235, 215, 0.7);
            font-size: 0.85em;
        }
        
        /* Floating Call Button */
        .floating-call {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--success);
            color: #fff;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.4);
            text-decoration: none;
            transition: all 0.3s;
            z-index: 999;
            animation: pulse 2s infinite;
        }
        
        .floating-call:hover {
            transform: scale(1.1);
            background: var(--success-hover);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.5);
        }
        
        .floating-call:active {
            transform: scale(0.95);
        }
        
        .floating-call svg {
            width: 26px;
            height: 26px;
        }
        
        @keyframes pulse {
            0%, 100% { 
                box-shadow: 0 4px 16px rgba(5, 150, 105, 0.4);
            }
            50% { 
                box-shadow: 0 4px 24px rgba(5, 150, 105, 0.6), 0 0 0 8px rgba(5, 150, 105, 0.1);
            }
        }
        
        /* Tablet Responsive (481px - 768px) */
        @media (min-width: 481px) {
            .container {
                padding: 0 24px;
            }
            
            .site-name {
                display: inline;
            }
            
            .logo {
                width: 42px;
                height: 42px;
                font-size: 24px;
            }
            
            .nav-links {
                gap: 20px;
            }
            
            .nav-links a {
                font-size: 0.95em;
            }
            
            .lang-switch a {
                padding: 6px 14px !important;
                font-size: 0.85em;
            }
            
            main {
                padding: 40px 0;
            }
            
            main h1 {
                font-size: 2em;
            }
            
            main h2 {
                font-size: 1.6em;
            }
            
            footer .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .floating-call {
                width: 60px;
                height: 60px;
                bottom: 24px;
                right: 24px;
            }
            
            .floating-call svg {
                width: 28px;
                height: 28px;
            }
        }
        
        /* Desktop Responsive (769px+) */
        @media (min-width: 769px) {
            header {
                padding: 12px 0;
            }
            
            .logo {
                width: 46px;
                height: 46px;
                font-size: 26px;
            }
            
            .site-name {
                font-size: 1.15em;
                margin-left: 12px;
            }
            
            .nav-links {
                gap: 24px;
            }
            
            .nav-links a {
                font-size: 1em;
            }
            
            main {
                padding: 50px 0;
            }
            
            main h1 {
                font-size: 2.2em;
                margin-bottom: 20px;
            }
            
            main h2 {
                font-size: 1.7em;
                margin: 35px 0 15px;
            }
            
            main h3 {
                font-size: 1.3em;
            }
            
            main p {
                font-size: 1.05em;
                margin-bottom: 18px;
            }
            
            footer {
                padding: 40px 0 25px;
            }
            
            footer .footer-content {
                grid-template-columns: repeat(3, 1fr);
                gap: 30px;
            }
            
            .floating-call {
                width: 64px;
                height: 64px;
                bottom: 30px;
                right: 30px;
            }
            
            .floating-call svg {
                width: 30px;
                height: 30px;
            }
        }
        
        /* Extra small phones (max 360px) */
        @media (max-width: 360px) {
            .logo {
                width: 34px;
                height: 34px;
                font-size: 20px;
            }
            
            .nav-links a {
                font-size: 0.85em;
                padding: 5px 8px;
            }
            
            .lang-switch a {
                padding: 4px 10px !important;
                font-size: 0.75em;
            }
            
            main h1 {
                font-size: 1.5em;
            }
            
            .floating-call {
                width: 52px;
                height: 52px;
                bottom: 16px;
                right: 16px;
            }
        }
        
        /* Landscape mobile optimization */
        @media (max-height: 500px) and (orientation: landscape) {
            header {
                padding: 8px 0;
            }
            
            .logo {
                width: 36px;
                height: 36px;
                font-size: 20px;
            }
            
            nav {
                gap: 15px;
            }
            
            main {
                padding: 24px 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="<?= BASE_URL ?>" class="logo-link">
                    <div class="logo">₸</div>
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
    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $seo['phone']) ?>" class="floating-call" title="<?= $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq qilish' ?>" aria-label="<?= $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq qilish' ?>">
        <svg fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
        </svg>
    </a>
    <?php endif; ?>