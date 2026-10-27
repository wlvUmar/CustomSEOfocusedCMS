<?php
// path: ./views/templates/page.php
require 'header.php';

// Extract appliance name for SEO enhancement (defined in header.php)
$applianceNameForSEO = $applianceName ?? '';
?>

<main>

    <?php require BASE_PATH . '/views/partials/hero.php'; ?>

    <?php
    // ─── INJECTED CONTENT ────────────────────────────────────────────────────
    // Render the CMS content; it emits .content-section, .info-grid, .info-card,
    // .process-step, .brands-list, .faq-item, etc.
    // The CSS below re-skins ALL of these so old class names still work fine.
     $content = $page["content_$lang"] ?? $page['content_ru'] ?? $page['content_uz'] ?? '';
    $content = renderTemplate($content, $templateData);
    // Extract any <style> author put in content_ for full header/footer overrides
    $extractedFromContent = '';
    if (stripos($content, '<style') !== false) {
        [$content, $extractedFromContent] = extractAndSanitizePageStyles($content);
        if ($extractedFromContent !== '') {
            $GLOBALS['pageExtractedCss'] = trim(($GLOBALS['pageExtractedCss'] ?? '') . ($GLOBALS['pageExtractedCss'] ? "\n\n" : '') . $extractedFromContent);
            // If header already rendered, inject via JS fallback (shouldn't happen, header reads GLOBALS)
        }
    }
    $content = sanitizeFrontendHtml($content);
    $content = enhanceContentSEO($content, $page["title_$lang"] ?? $page['title_ru'] ?? '', $applianceNameForSEO);
    $mediaBySection = [
        'banner' => $pageMediaModel->getPageMedia($page['id'], 'banner'),
        'content' => $pageMediaModel->getPageMedia($page['id'], 'content'),
        'gallery' => $pageMediaModel->getPageMedia($page['id'], 'gallery'),
    ];

    // Wrap the content in our layout shell so JS/CSS can target sections
    $contentHtml = injectMediaByStructure($content, $mediaBySection, $lang);

    // Inline CTA after the first content block ("past the hero", not sticky).
    // Falls back to the top of the body when the content has no <section>.
    ob_start();
    $ctaPlace = 'mid';
    require BASE_PATH . '/views/partials/cta-block.php';
    $midCta = ob_get_clean();
    $splitAt = stripos($contentHtml, '</section>');
    if ($splitAt !== false) {
        $splitAt += strlen('</section>');
        $contentHtml = substr($contentHtml, 0, $splitAt) . $midCta . substr($contentHtml, $splitAt);
    } else {
        $contentHtml = $midCta . $contentHtml;
    }

    echo '<div class="content-body" id="content-body">';
    echo $contentHtml;
    echo '</div>';
    ?>

    <?php require BASE_PATH . '/views/partials/bot-card.php'; ?>

    <?php require BASE_PATH . '/views/partials/review-strip.php'; ?>

    <?php require BASE_PATH . '/views/partials/links-carousel.php'; ?>

    <?php require BASE_PATH . '/views/partials/faq-accordion.php'; ?>

    <?php $ctaPlace = 'end'; require BASE_PATH . '/views/partials/cta-block.php'; ?>

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
    prevBtn?.addEventListener('click', () => track.scrollBy({ left: -tileStep() * 2 }));
    nextBtn?.addEventListener('click', () => track.scrollBy({ left: tileStep() * 2 }));
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

    // Generic content sections, info-cards, process-steps, brand items — deferred to avoid layout thrash (remaining-bugs #10)
    const autoItems = document.querySelectorAll(
        '.content-section, .info-card, .process-step, .faq-item, .condition-item'
    );
    const autoObs = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) { e.target.classList.add('is-visible'); autoObs.unobserve(e.target); }
        });
    // threshold 0 + bottom margin: a single very tall section (whole page body) can never reach a 12% ratio on phones
    }, { threshold: 0, rootMargin: '0px 0px -8% 0px' });
    const scheduleAuto = (cb) => (window.requestIdleCallback ? requestIdleCallback(cb, {timeout: 300}) : requestAnimationFrame(() => setTimeout(cb, 0)));
    scheduleAuto(() => {
        autoItems.forEach((el, i) => {
            // Batch style writes before observe to reduce recalc
            el.style.transitionDelay = (i % 6) * 70 + 'ms';
        });
        autoItems.forEach(el => {
            // a wrapper taller than ~1.5 screens must never wait for a reveal (text would stay at opacity 0)
            if (el.offsetHeight > window.innerHeight * 1.5) el.classList.add('is-visible');
            else autoObs.observe(el);
        });
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

    // Brands + link tiles — reuse fadeObs to avoid extra observers (remaining-bugs #10: 7 -> 5 observers)
    const brandsList = document.querySelector('.brands-list');
    if (brandsList) fadeObs.observe(brandsList);
    document.querySelectorAll('.links-tile').forEach(t => fadeObs.observe(t));
})();

/* ── FAQ accordion: composited grid-rows (no max-height jank) ── */
(function() {
    const items = document.querySelectorAll('.faq-acc-item');
    if (!items.length) return;
    items.forEach(item => {
        const btn = item.querySelector('.faq-acc-item__q');
        if (!btn) return;
        btn.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');
            item.classList.toggle('is-open', !isOpen);
            btn.setAttribute('aria-expanded', String(!isOpen));
        });
    });
})();


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
</script>

<?php require 'footer.php'; ?>