<?php
// path: ./views/partials/links-carousel.php
// Relocated verbatim from views/templates/page.php — no logic changes.
?>
    <?php
    // ─── LINK WIDGETS → image carousel ──────────────────────────────────────
    require_once BASE_PATH . '/models/LinkWidget.php';
    $widgetModel = new LinkWidget();
    $pageLinks = $widgetModel->getLinksForPage($page['id']);

    if ($page['show_link_widget'] && !empty($pageLinks)):

        // Resolve a cover image per linked page: hero -> banner -> none.
        // Reuses the same PageMedia model already instantiated above; one
        // small query per tile (link widgets are a handful of items, not a
        // big list, so this stays cheap).
        $linkCovers = [];
        $linkHires = [];
        foreach ($pageLinks as $link) {
            $targetId = $link['link_to_page_id'] ?? null;
            $cover = null;
            $hires = null;
            if ($targetId) {
                $coverMedia = $pageMediaModel->getPageMedia($targetId, 'hero');
                if (empty($coverMedia)) {
                    $coverMedia = $pageMediaModel->getPageMedia($targetId, 'banner');
                }
                if (!empty($coverMedia[0]['filename'])) {
                    $fname = $coverMedia[0]['filename'];
                    $cover = absoluteUrl('/uploads/' . $fname, $baseUrl);
                    // hi-res variant for morph clone upgrade — same pipeline as hero srcset.
                    // Picks the largest derived variant (1366 > 1024 > 768 > 640) so the
                    // clone can swap mid-flight to a sharper source. Falls back to
                    // original if no derived exists; JS will then keep the thumbnail
                    // (soft at hero scale — honest pixel-data ceiling).
                    $sources = buildResponsiveImageSources($fname, getResponsiveImageWidths());
                    if ($sources && (!empty($sources['fallback']) || !empty($sources['webp']))) {
                        $srcset = $sources['fallback'] ?? $sources['webp'];
                        // also check webp for potentially larger variant
                        if (!empty($sources['webp'])) $srcset = $sources['webp'];
                        $parts = explode(',', $srcset);
                        $last = trim(end($parts));
                        $url = trim(explode(' ', $last)[0]);
                        if ($url !== '') $hires = absoluteUrl($url, $baseUrl);
                    }
                    if (empty($hires)) {
                        $name = pathinfo($fname, PATHINFO_FILENAME);
                        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                        foreach ([1366, 1024, 768, 640] as $w) {
                            $candJpg = BASE_PATH . '/public/uploads/derived/' . $name . '_w' . $w . '.jpg';
                            $candWebp = BASE_PATH . '/public/uploads/derived/' . $name . '_w' . $w . '.webp';
                            if (file_exists($candJpg)) { $hires = absoluteUrl('/uploads/derived/' . $name . '_w' . $w . '.jpg', $baseUrl); break; }
                            if (file_exists($candWebp)) { $hires = absoluteUrl('/uploads/derived/' . $name . '_w' . $w . '.webp', $baseUrl); break; }
                        }
                    }
                    // ensure hires is distinct from cover so JS swap actually triggers
                    if ($hires === $cover) $hires = null;
                }
            }
            $linkCovers[$targetId] = $cover;
            $linkHires[$targetId] = $hires;
        }
    ?>
    <section class="links-section" data-animate="stagger">
        <link rel="stylesheet" href="<?= BASE_URL ?>/css/hold-morph.css?v=<?= (is_file(PUBLIC_PATH . '/css/hold-morph.css') ? filemtime(PUBLIC_PATH . '/css/hold-morph.css') : (is_file(BASE_PATH . '/public/css/hold-morph.css') ? filemtime(BASE_PATH . '/public/css/hold-morph.css') : time())) ?>">
        <div class="links-section__head">
            <div class="section-label"><?= $lang === 'ru' ? 'Разделы' : 'Bo\'limlar' ?></div>
            <h2 class="links-section__title">
                <?= e(
                    $page["widget_title_$lang"]
                    ?? ($lang === 'ru' ? 'Полезные страницы' : 'Foydali sahifalar')
                ) ?>
            </h2>
        </div>

        <div class="links-carousel">
            <button type="button" class="links-nav links-nav--prev" aria-label="<?= $lang === 'ru' ? 'Назад' : 'Orqaga' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L8.414 11l4.293 4.293a1 1 0 01-1.414 1.414l-5-5a1 1 0 010-1.414l5-5a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            </button>
            <button type="button" class="links-nav links-nav--next" aria-label="<?= $lang === 'ru' ? 'Вперёд' : 'Oldinga' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L11.586 9 7.293 4.707a1 1 0 011.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            </button>

            <div class="links-track" id="links-track">
                <?php foreach ($pageLinks as $i => $link):
                    $targetId = $link['link_to_page_id'] ?? null;
                    $cover = $linkCovers[$targetId] ?? null;
                    $tileClass = 'links-tile' . ($cover ? '' : ' links-tile--no-image');
                    $titleText = $link["title_$lang"] ?? '';
                    $initial = $titleText !== '' ? mb_strtoupper(mb_substr($titleText, 0, 1)) : '';
                    $hiresForTile = $linkHires[$targetId] ?? null;
                ?>
                <a href="<?= BASE_URL ?>/<?= e($link['slug']) ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>"
                   class="<?= $tileClass ?>"
                   data-from="<?= e($page['slug']) ?>"
                   data-to="<?= e($link['slug']) ?>"
                   data-stagger="<?= $i ?>"
                   <?= $cover ? 'data-cover="' . e($cover) . '"' : '' ?>
                   <?= $hiresForTile ? 'data-cover-hires="' . e($hiresForTile) . '"' : '' ?>>
                    <?php if ($cover): ?>
                    <img class="links-tile__img" src="<?= e($cover) ?>" alt="" loading="lazy" decoding="async">
                    <?php else: ?>
                    <span class="links-tile__monogram" aria-hidden="true"><?= e($initial) ?></span>
                    <?php endif; ?>
                    <span class="links-tile__scrim" aria-hidden="true"></span>
                    <span class="links-tile__content">
                        <span class="links-tile__text"><?= e($titleText) ?></span>
                        <span class="links-tile__arrow" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
