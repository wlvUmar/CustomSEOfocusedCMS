<?php 
// path: ./views/templates/preview.php
// IMPLEMENTATION: Place in views/templates/preview.php

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
    <style>
        .preview-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            font-size: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .preview-banner-left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .preview-badge {
            background: rgba(255,255,255,0.3);
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .preview-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .preview-controls {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .preview-controls select,
        .preview-controls a,
        .preview-controls button {
            padding: 6px 12px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .preview-controls select:hover,
        .preview-controls a:hover,
        .preview-controls button:hover {
            background: rgba(255,255,255,0.3);
        }
        body.preview-mode {
            padding-top: 120px;
        }
        body.preview-mode header {
            top: 60px;
        }
        @media (max-width: 768px) {
            .preview-banner {
                font-size: 12px;
                padding: 10px 15px;
            }
            .preview-controls select,
            .preview-controls a,
            .preview-controls button {
                padding: 5px 10px;
                font-size: 12px;
            }
            body.preview-mode {
                padding-top: 140px;
            }
        }
    </style>
</head>
<body class="preview-mode">
    
    <!-- Preview Banner -->
    <div class="preview-banner">
        <div class="preview-banner-left">
            <span class="preview-badge">⚠️ PREVIEW MODE</span>
            <div class="preview-info">
                <strong>Month:</strong> <?= date('F', mktime(0, 0, 0, $previewMonth, 1)) ?>
                <?php if ($hasRotation): ?>
                    <span style="background: rgba(255,255,255,0.3); padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">
                        ✓ Rotation Active
                    </span>
                <?php else: ?>
                    <span style="background: rgba(0,0,0,0.3); padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                        Base Content
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="preview-controls">
            <select id="month-selector" onchange="changeMonth()">
                <?php
                $months = [
                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                ];
                foreach ($months as $num => $name):
                ?>
                <option value="<?= $num ?>" <?= $num == $previewMonth ? 'selected' : '' ?>>
                    <?= $name ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select id="lang-selector" onchange="changeLang()">
                <option value="ru" <?= $lang === 'ru' ? 'selected' : '' ?>>Русский</option>
                <option value="uz" <?= $lang === 'uz' ? 'selected' : '' ?>>O'zbekcha</option>
            </select>
            
            <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>">
                Edit Rotations
            </a>
            
            <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>">
                Edit Page
            </a>
            
            <button onclick="window.close()" style="background: rgba(220, 38, 38, 0.3);">
                Close Preview
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
            <?php
            echo $page["content_$lang"];
            ?>
            
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
        const pageId = <?= $page['id'] ?>;
        
        function changeMonth() {
            const month = document.getElementById('month-selector').value;
            const lang = document.getElementById('lang-selector').value;
            window.location.href = `<?= BASE_URL ?>/admin/preview/${pageId}?month=${month}&lang=${lang}`;
        }
        
        function changeLang() {
            const month = document.getElementById('month-selector').value;
            const lang = document.getElementById('lang-selector').value;
            window.location.href = `<?= BASE_URL ?>/admin/preview/${pageId}?month=${month}&lang=${lang}`;
        }
    </script>
    
    <script src="https://unpkg.com/feather-icons"></script>
    <script>feather.replace();</script>
</body>
</html>