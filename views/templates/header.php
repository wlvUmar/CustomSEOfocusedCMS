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
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6; 
            color: #2c3e50;
            background: #f8f9fa;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 20px; 
        }
        
        /* Header Styles */
        header { 
            background: #fff;
            padding: 15px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        nav { 
            display: flex; 
            gap: 30px; 
            align-items: center;
            flex-wrap: wrap;
        }
        
        nav a.logo { 
            color: #2563eb;
            text-decoration: none;
            font-size: 1.4em;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        nav a:not(.logo) { 
            color: #4b5563;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
            font-size: 0.95em;
        }
        
        nav a:not(.logo):hover { 
            color: #2563eb;
        }
        
        .lang-switch { 
            margin-left: auto;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .lang-switch a {
            padding: 6px 12px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #64748b;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .lang-switch a:hover {
            background: #2563eb;
            color: #fff;
        }
        
        /* Call to Action Banner */
        .cta-banner {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            padding: 25px 0;
            text-align: center;
        }
        
        .cta-content h2 {
            font-size: 1.5em;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .cta-content p {
            font-size: 1.05em;
            opacity: 0.95;
            margin-bottom: 20px;
        }
        
        .cta-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-call {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            color: #2563eb;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.15em;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-call:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        .btn-call svg {
            width: 20px;
            height: 20px;
        }
        
        /* Main Content */
        main { 
            padding: 50px 0;
            min-height: 60vh;
            background: #fff;
        }
        
        main .container {
            max-width: 900px;
        }
        
        /* Content Typography */
        main h1 {
            color: #1e293b;
            font-size: 2.2em;
            margin-bottom: 20px;
            line-height: 1.3;
        }
        
        main h2 {
            color: #334155;
            font-size: 1.7em;
            margin: 35px 0 15px;
        }
        
        main h3 {
            color: #475569;
            font-size: 1.3em;
            margin: 25px 0 12px;
        }
        
        main p {
            margin-bottom: 18px;
            color: #475569;
            font-size: 1.05em;
        }
        
        main ul, main ol {
            margin: 20px 0 20px 25px;
            color: #475569;
        }
        
        main li {
            margin-bottom: 10px;
            line-height: 1.7;
        }
        
        main a {
            color: #2563eb;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s;
        }
        
        main a:hover {
            border-bottom-color: #2563eb;
        }
        
        /* Footer Styles */
        footer { 
            background: #1e293b;
            color: #e2e8f0;
            padding: 40px 0 25px;
            margin-top: 60px;
        }
        
        footer .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        footer h3 {
            color: #fff;
            font-size: 1.2em;
            margin-bottom: 15px;
        }
        
        footer p {
            margin-bottom: 12px;
            line-height: 1.7;
            color: #cbd5e1;
        }
        
        footer a {
            color: #60a5fa;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        footer a:hover {
            color: #93c5fd;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .contact-item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            color: #60a5fa;
        }
        
        .copyright {
            text-align: center;
            padding-top: 25px;
            border-top: 1px solid #334155;
            color: #94a3b8;
            font-size: 0.9em;
        }
        
        /* Floating Call Button */
        .floating-call {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #10b981;
            color: #fff;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
            text-decoration: none;
            transition: all 0.3s;
            z-index: 999;
            animation: pulse 2s infinite;
        }
        
        .floating-call:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(16, 185, 129, 0.5);
        }
        
        .floating-call svg {
            width: 28px;
            height: 28px;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4); }
            50% { box-shadow: 0 4px 30px rgba(16, 185, 129, 0.6); }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            nav {
                gap: 15px;
            }
            
            nav a.logo {
                font-size: 1.2em;
            }
            
            nav a:not(.logo) {
                font-size: 0.9em;
            }
            
            .cta-content h2 {
                font-size: 1.3em;
            }
            
            .btn-call {
                padding: 12px 24px;
                font-size: 1em;
            }
            
            main h1 {
                font-size: 1.8em;
            }
            
            .floating-call {
                width: 55px;
                height: 55px;
                bottom: 20px;
                right: 20px;
            }
            
            footer .footer-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="<?= BASE_URL ?>" class="logo"><?= e($seo["site_name_$lang"]) ?></a>
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
                    <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>">RU</a>
                    <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>/uz">UZ</a>
                </div>
            </nav>
        </div>
    </header>
    
    <?php if ($seo['phone']): ?>
    <div class="cta-banner">
        <div class="container">
            <div class="cta-content">
                <h2><?= $lang === 'ru' ? 'Выкупим вашу технику по выгодной цене!' : 'Texnikangizni qulay narxda sotib olamiz!' ?></h2>
                <p><?= $lang === 'ru' ? 'Быстрая оценка и моментальная оплата' : 'Tez baholash va oniy to\'lov' ?></p>
                <div class="cta-buttons">
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $seo['phone']) ?>" class="btn-call">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                        </svg>
                        <?= e($seo['phone']) ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Floating Call Button -->
    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $seo['phone']) ?>" class="floating-call" title="<?= $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq qilish' ?>">
        <svg fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
        </svg>
    </a>
    <?php endif; ?>