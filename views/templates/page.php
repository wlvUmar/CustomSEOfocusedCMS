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

        <!-- Bot Banner CTA -->
        <div class="bot-banner">
            <div class="bot-banner__icon">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M22.05 1.577c-.393-.016-.784.08-1.117.235-.484.186-4.92 1.902-9.41 3.64-2.26.873-4.518 1.746-6.256 2.415-1.737.67-3.045 1.168-3.114 1.192-.46.16-1.082.362-1.61.984-.133.155-.267.354-.335.628s-.038.622.095.895c.265.547.714.773 1.244.976 1.76.564 3.58 1.102 5.087 1.608.556 1.96 1.09 3.927 1.618 5.89.174.394.553.54.944.544l-.002.02s.307.03.606-.042c.3-.07.677-.244 1.02-.565.377-.354 1.4-1.36 1.98-1.928l4.37 3.226.035.02s.484.34 1.192.388c.354.024.82-.044 1.22-.337.403-.294.67-.767.795-1.307.374-1.63 2.853-13.427 3.276-15.38l-.012.046c.296-1.1.187-2.108-.496-2.705-.342-.297-.736-.427-1.13-.444zm-.118 1.874c.027.025.025.025.002.027-.007-.002.08.118-.09.755l-.007.024-.005.022c-.432 1.997-2.936 13.9-3.27 15.356-.046.196-.065.182-.054.17-.1-.015-.285-.094-.3-.1l-7.48-5.525c2.562-2.467 5.182-4.7 7.827-7.08.468-.235.39-.96-.17-.972-.594.14-1.095.567-1.64.84-3.132 1.858-6.332 3.492-9.43 5.406-1.59-.553-3.177-1.012-4.643-1.467 1.272-.51 2.283-.886 3.278-1.27 1.738-.67 3.996-1.54 6.256-2.415 4.522-1.748 9.07-3.51 9.465-3.662l.032-.013.03-.013c.11-.05.173-.055.202-.057 0 0-.01-.033-.002-.026zM10.02 16.016l1.234.912c-.532.52-1.035 1.01-1.398 1.36z"/>
                </svg>
            </div>
            <div class="bot-banner__body">
                <span class="bot-banner__badge">✨ <?= $lang === 'ru' ? 'Новинка' : 'Yangi' ?></span>
                <p class="bot-banner__title">
                    <?= $lang === 'ru' ? 'Узнайте цену за 30 секунд' : '30 sekundda narxni bilish' ?>
                    <span style="display:inline-block;margin-left:8px;padding:2px 8px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:700;vertical-align:middle;">Beta</span>
                </p>
                <p class="bot-banner__desc"><?= $lang === 'ru' ? 'Отправьте фото техники в наш Telegram-бот — оценка мгновенно, без звонков.' : 'Texnika rasmini Telegram-botga yuboring — baholash oniy, qo\'ng\'iroqsiz.' ?></p>
            </div>
            <a href="<?= TELEGRAM_BOT_URL ?>" target="_blank" rel="noopener noreferrer" class="bot-banner__cta" data-track-click="1">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M22.05 1.577c-.393-.016-.784.08-1.117.235-.484.186-4.92 1.902-9.41 3.64-2.26.873-4.518 1.746-6.256 2.415-1.737.67-3.045 1.168-3.114 1.192-.46.16-1.082.362-1.61.984-.133.155-.267.354-.335.628s-.038.622.095.895c.265.547.714.773 1.244.976 1.76.564 3.58 1.102 5.087 1.608.556 1.96 1.09 3.927 1.618 5.89.174.394.553.54.944.544l-.002.02s.307.03.606-.042c.3-.07.677-.244 1.02-.565.377-.354 1.4-1.36 1.98-1.928l4.37 3.226.035.02s.484.34 1.192.388c.354.024.82-.044 1.22-.337.403-.294.67-.767.795-1.307.374-1.63 2.853-13.427 3.276-15.38l-.012.046c.296-1.1.187-2.108-.496-2.705-.342-.297-.736-.427-1.13-.444zm-.118 1.874c.027.025.025.025.002.027-.007-.002.08.118-.09.755l-.007.024-.005.022c-.432 1.997-2.936 13.9-3.27 15.356-.046.196-.065.182-.054.17-.1-.015-.285-.094-.3-.1l-7.48-5.525c2.562-2.467 5.182-4.7 7.827-7.08.468-.235.39-.96-.17-.972-.594.14-1.095.567-1.64.84-3.132 1.858-6.332 3.492-9.43 5.406-1.59-.553-3.177-1.012-4.643-1.467 1.272-.51 2.283-.886 3.278-1.27 1.738-.67 3.996-1.54 6.256-2.415 4.522-1.748 9.07-3.51 9.465-3.662l.032-.013.03-.013c.11-.05.173-.055.202-.057 0 0-.01-.033-.002-.026zM10.02 16.016l1.234.912c-.532.52-1.035 1.01-1.398 1.36z"/>
                </svg>
                <?= $lang === 'ru' ? 'Попробовать' : 'Sinab ko\'rish' ?>
            </a>
        </div>

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
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="13 6 19 12 13 18"></polyline>
                        </svg>
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
