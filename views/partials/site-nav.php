<?php
// path: ./views/partials/site-nav.php
// Relocated verbatim from views/templates/header.php — no logic changes.
?>
    <header>
        <div class="container">
            <nav>
                <a href="<?= $baseUrl ?>/" class="logo-link">
                    <span class="logo-wrap">
                    <picture>
                        <source
                            type="image/webp"
                            srcset="<?= BASE_URL ?>/css/logo-48.webp 48w, <?= BASE_URL ?>/css/logo-96.webp 96w"
                            sizes="48px"
                        >
                        <img
                            src="<?= BASE_URL ?>/css/logo-48.png"
                            srcset="<?= BASE_URL ?>/css/logo-48.png 48w, <?= BASE_URL ?>/css/logo-96.png 96w"
                            sizes="48px"
                            width="48"
                            height="48"
                            class="logo"
                            alt="<?= e($seo["site_name_$lang"]) ?>"
                            loading="eager"
                            decoding="async"
                        >
                    </picture>
                    <span class="logo-mark" aria-hidden="true"></span>
                    </span>
                    <span class="site-name"><?= e($seo["site_name_$lang"]) ?></span>
                </a>

                <div class="nav-links">
                    <?php
                    require_once BASE_PATH . '/models/Page.php';
                    $navLangRu = $navLangRu ?? canonicalUrlForPage($page['slug'] ?? '', 'ru');
                    $navLangUz = $navLangUz ?? canonicalUrlForPage($page['slug'] ?? '', 'uz');
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
                        <a href="<?= $navLangRu ?>" <?= $lang === 'ru' ? 'class="active"' : '' ?>>RU</a>
                        <a href="<?= $navLangUz ?>" <?= $lang === 'uz' ? 'class="active"' : '' ?>>UZ</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>
