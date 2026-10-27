<?php
// path: ./views/partials/hero.php
// Relocated verbatim from views/templates/page.php — no logic changes.
?>
    <?php
    // ─── HERO ────────────────────────────────────────────────────────────────
    $pageTitle = $page["title_$lang"] ?? $page['title_ru'] ?? $page['slug'] ?? '';
    require_once BASE_PATH . '/models/PageMedia.php';
    $pageMediaModel = new PageMedia();
    $heroMedia = $pageMediaModel->getPageMedia($page['id'], 'hero');
    $hasHero = !empty($heroMedia);
    $GLOBALS['heroTitleActive'] = $hasHero;
    $GLOBALS['pageTitleRendered'] = $hasHero && !empty($pageTitle);
    ?>

    <section class="hero" id="hero">
        <?php if ($hasHero): ?>
        <div class="hero__bg auto-hero-section" id="hero-bg">
            <?php echo processMediaPlaceholders('{{media-section:hero}}', $page['id']); ?>
        </div>
        <?php else: ?>
        <div class="hero__bg hero__bg--gradient"></div>
        <?php endif; ?>

        <div class="hero__overlay"></div>

        <div class="hero__content" data-animate="hero-card">
            <?php if (!empty($pageTitle)): ?>
            <h1 class="hero__title"><?= e($pageTitle) ?></h1>
            <?php $GLOBALS['pageTitleRendered'] = true; ?>
            <?php endif; ?>
            <p class="hero__sub">
                <?= $lang === 'ru'
                    ? 'Честная цена · Выезд по Ташкенту · Оплата в день обращения'
                    : 'Adolatli narx · Toshkent bo\'ylab chiqish · Murojaat kuni to\'lov' ?>
            </p>
            <div class="hero__actions">
                <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contactUi['phone'] ?? ($seo['phone'] ?? '')) ?>"
                   class="hero__cta" data-cta="hero_call">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                    </svg>
                    <?= $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq' ?>
                </a>

                <?php if (($floatingCta['type'] ?? 'none') !== 'none' && !empty($floatingCta['url'])): ?>
                <?php
                $class = match ($floatingCta['type'] ?? 'telegram') {
                    'instagram' => 'hero__cta hero__cta--instagram',
                    'custom' => 'hero__cta hero__cta--custom',
                    default => 'hero__cta hero__cta--telegram',
                }; ?>

                <a href="<?= e($floatingCta['url']) ?>"
                   class="<?= $class ?>"
                   data-cta="hero_chat"
                   target="_blank"
                   rel="noopener noreferrer">

                    <?php if (($floatingCta['type'] ?? 'telegram') === 'instagram'): ?>
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2zm0 1.8A3.95 3.95 0 0 0 3.8 7.75v8.5a3.95 3.95 0 0 0 3.95 3.95h8.5a3.95 3.95 0 0 0 3.95-3.95v-8.5a3.95 3.95 0 0 0-3.95-3.95h-8.5zm8.95 1.35a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.8A3.2 3.2 0 1 0 12 15.2 3.2 3.2 0 0 0 12 8.8z"/>
                        </svg>
                    <?php elseif (($floatingCta['type'] ?? 'telegram') === 'custom'): ?>
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M10.59 13.41a1 1 0 0 0 1.41 1.41l5.3-5.29v3.47a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1h-7a1 1 0 1 0 0 2h3.47l-5.18 5.18zM5 6a1 1 0 0 1 1-1h2.5a1 1 0 1 1 0 2H7v10h10v-1.5a1 1 0 1 1 2 0V18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V6z"/>
                        </svg>
                    <?php else: ?>
                        <!-- Telegram -->
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M22.05 1.577c-.393-.016-.784.08-1.117.235-.484.186-4.92 1.902-9.41 3.64-2.26.873-4.518 1.746-6.256 2.415-1.737.67-3.045 1.168-3.114 1.192-.46.16-1.082.362-1.61.984-.133.155-.267.354-.335.628s-.038.622.095.895c.265.547.714.773 1.244.976 1.76.564 3.58 1.102 5.087 1.608.556 1.96 1.09 3.927 1.618 5.89.174.394.553.54.944.544l-.002.02s.307.03.606-.042c.3-.07.677-.244 1.02-.565.377-.354 1.4-1.36 1.98-1.928l4.37 3.226.035.02s.484.34 1.192.388c.354.024.82-.044 1.22-.337.403-.294.67-.767.795-1.307.374-1.63 2.853-13.427 3.276-15.38z"/>
                        </svg>
                    <?php endif; ?>

                    <?= e($floatingCta['label'] ?? ($lang === 'ru' ? 'Связаться' : 'Bog‘lanish')) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // Per-page theme art (views/partials/hero-art/{theme}.php). Illustrations only appear on pages
        // without a hero photo; the 'screen' theme always shows its brand ticker.
        $heroTheme = PageTheme::normalize($page['theme'] ?? PageTheme::BASE);
        $heroArt = PageTheme::heroArtPartial($heroTheme);
        if ($heroArt && (!$hasHero || $heroTheme === 'screen')) {
            require $heroArt;
        }
        ?>
    </section>
