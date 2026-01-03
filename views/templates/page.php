<?php
// path: ./views/templates/page.php
require 'header.php';
?>

<main>
    <div class="container">

        <?php
        $content = $page["content_$lang"];
        $content = renderTemplate($content, $templateData);
        echo $content;
        ?>

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

                    <div class="link-widget-arrow">
                        <i data-feather="chevron-right"></i>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($faqs)): ?>
        <section class="faq-section">
            <h2>
                <?= $lang === 'ru'
                    ? 'Часто задаваемые вопросы'
                    : 'Ko\'p beriladigan savollar'
                ?>
            </h2>

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

<script>
document.addEventListener('click', function (e) {
    const card = e.target.closest('.link-widget-card');
    if (!card) return;

    fetch('<?= BASE_URL ?>/track-internal-link', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `from=${card.dataset.from}&to=${card.dataset.to}&lang=<?= $lang ?>`,
        keepalive: true
    }).catch(() => {});
});
</script>

<?php require 'footer.php'; ?>
