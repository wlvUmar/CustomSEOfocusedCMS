<?php 
$lang = $lang ?? getCurrentLanguage();
$seo = $seo ?? [];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PREVIEW: <?= e($page["title_$lang"]) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/pages.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/preview.css">
</head>
<body class="preview-mode">
    
    <!-- Preview Banner -->
    <div class="preview-banner">
        <div class="preview-banner-left">
            <span class="preview-badge">
                <i data-feather="alert-circle"></i>
                PREVIEW MODE
            </span>

            <div class="preview-info">
                <strong>Month:</strong> <?= date('F', mktime(0, 0, 0, $previewMonth, 1)) ?>
                <?php if ($hasRotation): ?>
                    <span class="rotation-badge rotation-active">✓ Rotation Active</span>
                <?php else: ?>
                    <span class="rotation-badge base-content">Base Content</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="preview-controls">
            <select id="month-selector" onchange="changeMonth()" aria-label="Select month">
                <?php
                $months = [
                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                ];
                foreach ($months as $num => $name):
                ?>
                <option value="<?= $num ?>" <?= $num == $previewMonth ? 'selected' : '' ?>>
                    <?= $name ?><?= $num == date('n') ? ' (Now)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select id="lang-selector" onchange="changeLang()" aria-label="Select language">
                <option value="ru" <?= $lang === 'ru' ? 'selected' : '' ?>>Русский</option>
                <option value="uz" <?= $lang === 'uz' ? 'selected' : '' ?>>O'zbekcha</option>
            </select>
            
            <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>" title="Edit rotations">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
            
            <button onclick="window.close()" class="close-btn" title="Close preview">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Close
            </button>
        </div>
    </div>
    
    <!-- Regular Page Header -->
    <header>
        <div class="container">
            <nav>
                <a href="<?= BASE_URL ?>" class="logo-link">
                    <img src="<?= BASE_URL ?>/css/logo.png" class="logo" alt="<?= e($seo["site_name_$lang"]) ?>">
                    <span class="site-name"><?= e($seo["site_name_$lang"]) ?></span>
                </a>
                
                <div class="nav-links">
                    <div class="lang-switch">
                        <a href="#" class="<?= $lang === 'ru' ? 'active' : '' ?>">RU</a>
                        <a href="#" class="<?= $lang === 'uz' ? 'active' : '' ?>">UZ</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    
    <!-- Page Content -->
    <main>
        <div class="container">
            <?= $page["content_$lang"] ?>
            
            <?php if (!empty($faqs)): ?>
            <section class="faq-section">
                <h2><?= $lang === 'ru' ? 'Часто задаваемые вопросы' : 'Ko\'p beriladigan savollar' ?></h2>
                <div class="faq-list">
                    <?php foreach ($faqs as $faq): ?>
                    <div class="faq-item">
                        <h3><?= e($faq["question_$lang"]) ?></h3>
                        <p><?= nl2br(e($faq["answer_$lang"])) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?= e($seo["site_name_$lang"]) ?></h3>
                    <p><?= $lang === 'ru' ? 'Покупаем бытовую технику' : 'Maishiy texnikani sotib olamiz' ?></p>
                </div>
            </div>
            <div class="copyright">
                <p>© <?= date('Y') ?> <?= e($seo["site_name_$lang"]) ?></p>
            </div>
        </div>
    </footer>
    
    <script>
        window.previewPageId = <?= $page['id'] ?>;
        window.baseUrl = '<?= BASE_URL ?>';
    </script>
    <script src="<?= BASE_URL ?>/js/admin/preview.js"></script>
    
    <script src="https://unpkg.com/feather-icons"></script>
    <script>feather.replace();</script>
</body>
</html>
