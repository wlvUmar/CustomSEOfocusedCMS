<?php
// path: ./views/templates/page.php
require 'header.php';

// Extract appliance name for SEO enhancement (defined in header.php)
$applianceNameForSEO = $applianceName ?? '';
?>

<main>
    <div class="container">

        <?php
        // Auto-inject hero section if media exists
        $pageTitle = $page["title_$lang"] ?? $page['title_ru'] ?? $page['slug'] ?? '';
        $GLOBALS['currentPageTitle'] = $pageTitle;
        require_once BASE_PATH . '/models/PageMedia.php';
        $pageMediaModel = new PageMedia();
        $heroMedia = $pageMediaModel->getPageMedia($page['id'], 'hero');
        $hasHero = !empty($heroMedia);
        $GLOBALS['heroTitleActive'] = $hasHero;
        $GLOBALS['pageTitleRendered'] = $hasHero && !empty($pageTitle);
        
        if ($hasHero) {
            echo '<div class="auto-hero-section">';
            echo processMediaPlaceholders('{{media-section:hero}}', $page['id']);
            echo '</div>';
        } elseif (!empty($pageTitle)) {
            $GLOBALS['pageTitleRendered'] = true;
            echo '<div class="page-hero"><h1>' . e($pageTitle) . '</h1></div>';
        }
        ?>

        <?php
        $content = $page["content_$lang"];
        $content = renderTemplate($content, $templateData);
        
        // Enhance content for SEO (fix images, headings, links)
        $content = enhanceContentSEO($content, $page["title_$lang"], $applianceNameForSEO);
        
        echo $content;
        ?>

        <?php
        // Auto-inject banner section if media exists
        $bannerMedia = $pageMediaModel->getPageMedia($page['id'], 'banner');
        
        if (!empty($bannerMedia)) {
            echo '<div class="auto-banner-section">';
            echo processMediaPlaceholders('{{media-section:banner}}', $page['id']);
            echo '</div>';
        }
        ?>

        <?php
        // Auto-inject content media if media exists (renders all content section media)
        $contentMedia = $pageMediaModel->getPageMedia($page['id'], 'content');
        
        if (!empty($contentMedia)) {
            echo '<div class="auto-content-media">';
            echo processMediaPlaceholders('{{media-section:content}}', $page['id']);
            echo '</div>';
        }
        ?>

        <?php
        // Auto-inject gallery section if media exists
        $galleryMedia = $pageMediaModel->getPageMedia($page['id'], 'gallery');
        
        if (!empty($galleryMedia)) {
            echo '<div class="auto-gallery-section">';
            echo '<h2>' . ($lang === 'ru' ? 'Галерея' : 'Galereya') . '</h2>';
            echo processMediaPlaceholders('{{media-section:gallery}}', $page['id']);
            echo '</div>';
        }
        ?>
        <?php if (!empty($seo['google_review_url'])): ?>
        <section class="review-panel">
            <div class="review-panel__content">
                <h2><?= $lang === 'ru' ? 'Оставьте отзыв' : 'Sharh qoldiring' ?></h2>
                <p>
                    <?= $lang === 'ru'
                        ? 'Если вам понравился сервис, будем благодарны за отзыв.'
                        : 'Agar xizmatimiz yoqqan bo‘lsa, sharh qoldirsangiz minnatdor bo‘lamiz.'
                    ?>
                </p>
            </div>
            <a class="review-panel__button" href="<?= e($seo['google_review_url']) ?>" target="_blank" rel="noopener noreferrer">
                <?= $lang === 'ru' ? 'Оставить отзыв в Google' : 'Google-da sharh qoldirish' ?>
            </a>
        </section>
        <?php endif; ?>
        <?php
        require_once BASE_PATH . '/models/LinkWidget.php';

        $widgetModel = new LinkWidget();
        $pageLinks = $widgetModel->getLinksForPage($page['id']);

        if ($page['show_link_widget'] && !empty($pageLinks)):
        ?>
        <section class="link-widget-section">
            <h2>
                <?= e(
                    $page["widget_title_$lang"]
                    ?? ($lang === 'ru' ? 'Полезные страницы' : 'Foydali sahifalar')
                ) ?>
            </h2>

            <div class="link-widget-grid">
                <?php foreach ($pageLinks as $link): ?>
                <a
                    href="<?= BASE_URL ?>/<?= e($link['slug']) ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>"
                    class="link-widget-card"
                    data-from="<?= e($page['slug']) ?>"
                    data-to="<?= e($link['slug']) ?>"
                >
                    <div class="link-widget-icon">
                        <i data-feather="arrow-right"></i>
                    </div>

                    <div class="link-widget-content">
                        <h3><?= e($link["title_$lang"]) ?></h3>
                    </div>

                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>



    </div>
</main>

<script>
document.addEventListener('click', function (e) {
    const card = e.target.closest('.link-widget-card');
    if (!card) return;

    try {
        fetch('/track-internal-link', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                from: card.dataset.from || '',
                to: card.dataset.to || '',
                lang: '<?= $lang ?>'
            }).toString(),
            keepalive: true
        }).catch(() => {});
    } catch (e) {}
});
</script>

<?php require 'footer.php'; ?>
