<?php
// path: ./views/templates/page.php
require 'header.php';

// Extract appliance name for SEO enhancement (defined in header.php)
$applianceNameForSEO = $applianceName ?? '';
?>

<main>

    <?php
    // ─── HERO ────────────────────────────────────────────────────────────────
    $pageTitle = $page["title_$lang"] ?? $page['title_ru'] ?? $page['slug'] ?? '';
    $GLOBALS['currentPageTitle'] = $pageTitle;
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
                   class="hero__cta">
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
    </section>

    <?php
    // ─── INJECTED CONTENT ────────────────────────────────────────────────────
    // Render the CMS content; it emits .content-section, .info-grid, .info-card,
    // .process-step, .brands-list, .faq-item, etc.
    // The CSS below re-skins ALL of these so old class names still work fine.
    $content = $page["content_$lang"];
    $content = renderTemplate($content, $templateData);
    $content = enhanceContentSEO($content, $page["title_$lang"], $applianceNameForSEO);

    // Wrap the content in our layout shell so JS/CSS can target sections
    echo '<div class="content-body" id="content-body">';
    echo $content;
    echo '</div>';
    ?>

    <?php
    // ─── MEDIA: BANNER ───────────────────────────────────────────────────────
    $bannerMedia = $pageMediaModel->getPageMedia($page['id'], 'banner');
    if (!empty($bannerMedia)):
    ?>
    <div class="auto-banner-section">
        <?= processMediaPlaceholders('{{media-section:banner}}', $page['id']) ?>
    </div>
    <?php endif; ?>

    <?php
    // ─── MEDIA: CONTENT ──────────────────────────────────────────────────────
    $contentMedia = $pageMediaModel->getPageMedia($page['id'], 'content');
    if (!empty($contentMedia)):
    ?>
    <div class="auto-content-media">
        <?= processMediaPlaceholders('{{media-section:content}}', $page['id']) ?>
    </div>
    <?php endif; ?>

    <?php
    // ─── MEDIA: GALLERY ──────────────────────────────────────────────────────
    $galleryMedia = $pageMediaModel->getPageMedia($page['id'], 'gallery');
    if (!empty($galleryMedia)):
    ?>
    <div class="auto-gallery-section">
        <div class="section-label"><?= $lang === 'ru' ? 'Галерея' : 'Galereya' ?></div>
        <?= processMediaPlaceholders('{{media-section:gallery}}', $page['id']) ?>
    </div>
    <?php endif; ?>

    <?php
    // ─── GOOGLE REVIEW PANEL ─────────────────────────────────────────────────
    if (!empty($seo['google_review_url'])):
    ?>
    <section class="review-strip" data-animate="fade-up">
        <p class="review-strip__text">
            <?= $lang === 'ru'
                ? 'Если вам понравился сервис — будем рады вашему отзыву'
                : 'Agar xizmatimiz yoqqan bo\'lsa — sharhingiz biz uchun muhim' ?>
        </p>
        <a class="review-strip__btn" href="<?= e($seo['google_review_url']) ?>"
           target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            <?= $lang === 'ru' ? 'Оставить отзыв' : 'Sharh qoldirish' ?>
        </a>
    </section>
    <?php endif; ?>

    <?php
    // ─── FAQ ACCORDION ───────────────────────────────────────────────────────
    // Moved out of the dark footer (where it was a flat <details> list that
    // clashed with the rest of the page) and into the main content flow as a
    // proper branded accordion. $faqs / generateFAQSchema() logic in
    // header.php is untouched — this only changes where/how it's displayed.
    if (!empty($faqs)):
    ?>
    <section class="faq-section" data-animate="fade-up">
        <div class="section-label"><?= $lang === 'ru' ? 'Вопросы и ответы' : 'Savol-javoblar' ?></div>
        <h2 class="faq-section__title">
            <?= $lang === 'ru' ? 'Часто задаваемые вопросы' : 'Tez-tez beriladigan savollar' ?>
        </h2>

        <div class="faq-accordion">
            <?php foreach ($faqs as $i => $faq): ?>
            <div class="faq-acc-item<?= $i === 0 ? ' is-open' : '' ?>">
                <button type="button" class="faq-acc-item__q" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                    <span class="faq-acc-item__q-text"><?= e($faq["question_$lang"]) ?></span>
                    <span class="faq-acc-item__icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 7l5 5 5-5"/>
                        </svg>
                    </span>
                </button>
                <div class="faq-acc-item__a">
                    <div class="faq-acc-item__a-inner">
                        <p><?= nl2br(e($faq["answer_$lang"])) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

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
        foreach ($pageLinks as $link) {
            $targetId = $link['link_to_page_id'] ?? null;
            $cover = null;
            if ($targetId) {
                $coverMedia = $pageMediaModel->getPageMedia($targetId, 'hero');
                if (empty($coverMedia)) {
                    $coverMedia = $pageMediaModel->getPageMedia($targetId, 'banner');
                }
                if (!empty($coverMedia[0]['filename'])) {
                    $cover = absoluteUrl('/uploads/' . $coverMedia[0]['filename'], $baseUrl);
                }
            }
            $linkCovers[$targetId] = $cover;
        }
    ?>
    <section class="links-section" data-animate="stagger">
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
                ?>
                <a href="<?= BASE_URL ?>/<?= e($link['slug']) ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>"
                   class="<?= $tileClass ?>"
                   data-from="<?= e($page['slug']) ?>"
                   data-to="<?= e($link['slug']) ?>"
                   data-stagger="<?= $i ?>">
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

</main>

<script>
/* ── link tracking ── */
document.addEventListener('click', function(e) {
    const card = e.target.closest('.links-tile');
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
    } catch(e) {}
});

/* ── links carousel: prev/next buttons scroll by one tile width ── */
(function() {
    const track = document.getElementById('links-track');
    if (!track) return;
    const carousel = track.closest('.links-carousel');
    const prevBtn = carousel?.querySelector('.links-nav--prev');
    const nextBtn = carousel?.querySelector('.links-nav--next');
    function tileStep() {
        const tile = track.querySelector('.links-tile');
        if (!tile) return 260;
        const gap = parseFloat(getComputedStyle(track).gap || '14') || 14;
        return tile.getBoundingClientRect().width + gap;
    }
    prevBtn?.addEventListener('click', () => track.scrollBy({ left: -tileStep() * 2, behavior: 'smooth' }));
    nextBtn?.addEventListener('click', () => track.scrollBy({ left: tileStep() * 2, behavior: 'smooth' }));
})();

/* ── Intersection Observer for scroll animations ── */
(function() {
    if (!('IntersectionObserver' in window)) return;

    // Single-element fade-up
    const fadeTargets = document.querySelectorAll('[data-animate="fade-up"]');
    const fadeObs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) { e.target.classList.add('is-visible'); fadeObs.unobserve(e.target); }
        });
    }, { threshold: 0.15 });
    fadeTargets.forEach(t => fadeObs.observe(t));

    // Hero card
    const heroCard = document.querySelector('[data-animate="hero-card"]');
    if (heroCard) setTimeout(() => heroCard.classList.add('is-visible'), 150);

    // Staggered children
    const staggerSections = document.querySelectorAll('[data-animate="stagger"]');
    const staggerObs = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const items = entry.target.querySelectorAll('[data-stagger]');
                items.forEach(item => {
                    const delay = parseInt(item.dataset.stagger || 0, 10) * 80;
                    setTimeout(() => item.classList.add('is-visible'), delay);
                });
                staggerObs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    staggerSections.forEach(s => staggerObs.observe(s));

    // Generic content sections, info-cards, process-steps, brand items
    const autoItems = document.querySelectorAll(
        '.content-section, .info-card, .process-step, .faq-item, .condition-item'
    );
    const autoObs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) { e.target.classList.add('is-visible'); autoObs.unobserve(e.target); }
        });
    }, { threshold: 0.12 });
    autoItems.forEach((el, i) => {
        el.style.transitionDelay = (i % 6) * 70 + 'ms';
        autoObs.observe(el);
    });

    // Stagger info-cards within a grid
    document.querySelectorAll('.info-grid').forEach(grid => {
        const cards = grid.querySelectorAll('.info-card');
        const gridObs = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    cards.forEach((card, i) => {
                        setTimeout(() => card.classList.add('is-visible'), i * 100);
                    });
                    gridObs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        gridObs.observe(grid);
    });

    // Process steps timeline connectors
    document.querySelectorAll('.process-step').forEach((step, i) => {
        step.setAttribute('data-step-index', i);
    });

    const processSection = document.querySelector('.content-section:has(.process-step)');
    if (processSection) {
        const lineObs = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('timeline-active');
                    lineObs.unobserve(e.target);
                }
            });
        }, { threshold: 0.2 });
        lineObs.observe(processSection);
    }

    // Brands carousel snap indicator
    const brandsList = document.querySelector('.brands-list');
    if (brandsList) {
        const brandObs = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-visible');
                    brandObs.unobserve(e.target);
                }
            });
        }, { threshold: 0.1 });
        brandObs.observe(brandsList);
    }

    // Link tiles
    const linkTiles = document.querySelectorAll('.links-tile');
    const linkObs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) { e.target.classList.add('is-visible'); linkObs.unobserve(e.target); }
        });
    }, { threshold: 0.15 });
    linkTiles.forEach(t => linkObs.observe(t));
})();

/* ── FAQ accordion: smooth max-height toggle, multiple items can be open ── */
(function() {
    const items = document.querySelectorAll('.faq-acc-item');
    if (!items.length) return;

    items.forEach(item => {
        const btn = item.querySelector('.faq-acc-item__q');
        const panel = item.querySelector('.faq-acc-item__a');
        if (!btn || !panel) return;

        if (item.classList.contains('is-open')) {
            panel.style.maxHeight = panel.scrollHeight + 'px';
        }

        btn.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');
            if (isOpen) {
                panel.style.maxHeight = panel.scrollHeight + 'px';
                requestAnimationFrame(() => { panel.style.maxHeight = '0px'; });
                item.classList.remove('is-open');
                btn.setAttribute('aria-expanded', 'false');
            } else {
                item.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
                panel.style.maxHeight = panel.scrollHeight + 'px';
            }
        });

        panel.addEventListener('transitionend', () => {
            if (item.classList.contains('is-open')) {
                panel.style.maxHeight = 'none';
            }
        });
    });

    window.addEventListener('resize', () => {
        items.forEach(item => {
            if (!item.classList.contains('is-open')) return;
            const panel = item.querySelector('.faq-acc-item__a');
            if (panel) panel.style.maxHeight = panel.scrollHeight + 'px';
        });
    });
})();


/* ── Info-card carousel: always-on horizontal auto-scroll, seamless loop ──
   Wraps .info-grid in a track with a cloned copy placed right after it, then
   scrolls continuously; once scrollLeft passes the original copy's width it
   snaps back by that same amount, so the loop point is invisible. Runs at
   all viewport sizes now (previously mobile-only). */
function initInfoCardCarousels(){
  document.querySelectorAll('.info-grid').forEach(grid => {
    if (grid.dataset.carouselInit) return;
    const cards = Array.from(grid.children).filter(el => el.classList.contains('info-card'));
    if (cards.length < 3) return;
    grid.dataset.carouselInit = '1';

    const wrap = document.createElement('div');
    wrap.className = 'v-carousel';
    grid.parentNode.insertBefore(wrap, grid);

    const track = document.createElement('div');
    track.className = 'v-carousel__track';
    grid.classList.add('v-carousel__list');
    track.appendChild(grid);

    const clone = grid.cloneNode(true);
    clone.classList.add('v-carousel__list--clone');
    clone.removeAttribute('data-carousel-init');
    track.appendChild(clone);
    wrap.appendChild(track);

    let paused = false, resumeTimer = null;
    const pause = () => { paused = true; clearTimeout(resumeTimer); };
    const scheduleResume = () => { clearTimeout(resumeTimer); resumeTimer = setTimeout(() => paused = false, 1800); };

    ['pointerdown','touchstart'].forEach(ev => wrap.addEventListener(ev, pause, {passive:true}));
    ['pointerup','touchend'].forEach(ev => wrap.addEventListener(ev, scheduleResume, {passive:true}));
    wrap.addEventListener('mouseenter', pause);
    wrap.addEventListener('mouseleave', scheduleResume);

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return; // manual swipe only

    requestAnimationFrame(() => {
      // Real seam-to-seam distance: where the clone starts minus where the
      // original starts. This already accounts for the grid's own padding,
      // so there's no leftover gap when we wrap scrollLeft back to 0.
      const loopWidth = clone.offsetLeft - grid.offsetLeft;
      let last = null;
      function step(ts){
        if (last === null) last = ts;
        const dt = ts - last;
        last = ts;
        if (!paused) {
          wrap.scrollLeft += dt * 0.04;
          if (wrap.scrollLeft >= loopWidth) wrap.scrollLeft -= loopWidth;
        }
        requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    });
  });
}
window.addEventListener('DOMContentLoaded', initInfoCardCarousels);
document.addEventListener('DOMContentLoaded', initInfoCardCarousels);

/* ── Brands marquee: the CMS renders brand names as one block of plain
   text (e.g. "Samsung Apple LG Sony..."), so this splits it into individual
   chips and duplicates the set once, then a CSS animation scrolls the track
   by exactly -50% on a loop — a standard, seamless logo-strip marquee. ── */
function initBrandsMarquee(){
  document.querySelectorAll('.brands-list').forEach(list => {
    if (list.dataset.marqueeInit) return;
    const raw = list.textContent.trim();
    if (!raw) return;
    const names = raw.split(/\s+/).filter(Boolean);
    if (names.length < 2) return;
    list.dataset.marqueeInit = '1';

    list.textContent = '';
    list.classList.add('brands-list--marquee');

    function buildGroup(){
      const group = document.createElement('div');
      group.className = 'brands-group';
      names.forEach(name => {
        const chip = document.createElement('span');
        chip.className = 'brands-chip';
        chip.textContent = name;
        group.appendChild(chip);
      });
      return group;
    }

    const track = document.createElement('div');
    track.className = 'brands-track';
    track.appendChild(buildGroup());
    track.appendChild(buildGroup()); // duplicate set, so -50% loops seamlessly
    list.appendChild(track);
  });
}
window.addEventListener('DOMContentLoaded', initBrandsMarquee);
document.addEventListener('DOMContentLoaded', initBrandsMarquee);
</script>

<?php require 'footer.php'; ?>