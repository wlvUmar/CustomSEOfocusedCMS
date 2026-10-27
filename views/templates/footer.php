<?php require BASE_PATH . '/views/partials/fresh-block.php'; ?>
<footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?= e($seo["site_name_$lang"]) ?></h3>
                    <p><?= $lang === 'ru' ? 'ÐŸÐ¾ÐºÑƒÐ¿Ð°ÐµÐ¼ Ð±Ñ‹Ñ‚Ð¾Ð²ÑƒÑŽ Ñ‚ÐµÑ…Ð½Ð¸ÐºÑƒ, Ð½Ð¾Ð²ÑƒÑŽ Ð¸ Ð±/Ñƒ. Ð‘Ñ‹ÑÑ‚Ñ€Ð°Ñ Ð¾Ñ†ÐµÐ½ÐºÐ°, Ñ‡ÐµÑÑ‚Ð½Ð°Ñ Ñ†ÐµÐ½Ð°, Ð¼Ð¾Ð¼ÐµÐ½Ñ‚Ð°Ð»ÑŒÐ½Ð°Ñ Ð¾Ð¿Ð»Ð°Ñ‚Ð°.' : 'Maishiy texnikani, yangi va ishlatilganni sotib olamiz. Tez baholash, adolatli narx, oniy to\'lov.' ?></p>
                    <?php
                    require_once BASE_PATH . '/models/Page.php';
                    $footerNavPages = array_slice((new Page())->getNavPages(), 0, 10);
                    if ($footerNavPages):
                    ?>
                    <nav aria-label="<?= $lang === 'ru' ? 'Ð Ð°Ð·Ð´ÐµÐ»Ñ‹ ÑÐ°Ð¹Ñ‚Ð°' : 'Sayt bo\'limlari' ?>">
                        <p class="footer-links__title"><?= $lang === 'ru' ? 'Ð Ð°Ð·Ð´ÐµÐ»Ñ‹' : 'Bo\'limlar' ?></p>
                        <ul class="footer-links">
                            <?php foreach ($footerNavPages as $fp): ?>
                            <li><a href="<?= BASE_URL ?>/<?= e($fp['slug']) ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>"><?= e($fp["title_$lang"] ?: ($fp['title_ru'] ?? $fp['slug'])) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
                
                <div class="footer-section">
                    <h3><?= $lang === 'ru' ? 'ÐšÐ¾Ð½Ñ‚Ð°ÐºÑ‚Ñ‹' : 'Aloqa' ?></h3>
                    <?php
                    $effectivePhone = $contactUi['phone'] ?? ($seo['phone'] ?? '');
                    
                    
                    $effectiveEmail = $contactUi['email'] ?? ($seo['email'] ?? '');
                    ?>
                    <?php if ($effectivePhone): ?>
                    <div class="contact-item">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                        </svg>
                        <a href="tel:<?= preg_replace('/[^0-9+]/', '', $effectivePhone) ?>"><?= e($effectivePhone) ?></a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($effectiveEmail): ?>
                    <div class="contact-item">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                        <a href="mailto:<?= e($effectiveEmail) ?>"><?= e($effectiveEmail) ?></a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="footer-section">
                    <h3><?= $lang === 'ru' ? 'Ð˜Ð½Ñ„Ð¾Ñ€Ð¼Ð°Ñ†Ð¸Ñ' : 'Ma\'lumot' ?></h3>
                    <?php if ($seo["address_$lang"]): ?>
                    <div class="contact-item">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= e($seo["address_$lang"]) ?></span>
                    </div>
                    <?php if ($seo["working_hours_$lang"]): ?>
                    <div class="contact-item">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= e($seo["working_hours_$lang"]) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php
                    $mapEmbedUrl = trim((string)($seo['google_maps_embed_url'] ?? ''));
                    if ($mapEmbedUrl === '' && !empty($seo["address_$lang"])) {
                        $mapEmbedUrl = 'https://www.google.com/maps?q=' . rawurlencode($seo["address_$lang"]) . '&output=embed';
                    }
                    ?>
                    <?php if ($mapEmbedUrl): ?>
                    <div class="footer-map">
                        <iframe
                            src="<?= e($mapEmbedUrl) ?>"
                            title="<?= $lang === 'ru' ? 'ÐšÐ°Ñ€Ñ‚Ð° Ð¼ÐµÑÑ‚Ð¾Ð¿Ð¾Ð»Ð¾Ð¶ÐµÐ½Ð¸Ñ ÐºÐ¾Ð¼Ð¿Ð°Ð½Ð¸Ð¸' : 'Kompaniya joylashuvi xaritasi' ?>"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            aria-label="<?= $lang === 'ru' ? 'ÐšÐ°Ñ€Ñ‚Ð° Ð¼ÐµÑÑ‚Ð¾Ð¿Ð¾Ð»Ð¾Ð¶ÐµÐ½Ð¸Ñ ÐºÐ¾Ð¼Ð¿Ð°Ð½Ð¸Ð¸' : 'Kompaniya joylashuvi xaritasi' ?>"></iframe>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    

                    <div class="contact-item">
                        <a href="http://www.search.uz/eng/" title="Uzbekistan" target="_blank" rel="noopener noreferrer">
                            Search.uz: Web Directory of Uzbekistan
                        </a>
                    </div>
                </div>
            </div>

            <?php /* FAQ moved to a dedicated accordion section in page.php â€” see .faq-section */ ?>
            
            <div class="copyright">
                <p>Â© <?= date('Y') ?> <?= e($seo["site_name_$lang"]) ?>. <?= $lang === 'ru' ? 'Ð’ÑÐµ Ð¿Ñ€Ð°Ð²Ð° Ð·Ð°Ñ‰Ð¸Ñ‰ÐµÐ½Ñ‹.' : 'Barcha huquqlar himoyalangan.' ?></p>
            </div>
        </div>
    </footer>

    <script>
        window.baseUrl = "<?= rtrim(BASE_URL, '/') ?>";
        <?php if (!empty($seo['google_review_url'])): ?>
        window.googleReviewUrl = '<?= e($seo['google_review_url']) ?>';
        window.googleReviewPrompt = <?= json_encode($lang === 'ru' ? 'Ð¡Ð¿Ð°ÑÐ¸Ð±Ð¾! ÐÐµ Ð¼Ð¾Ð³Ð»Ð¸ Ð±Ñ‹ Ð²Ñ‹ Ð¾ÑÑ‚Ð°Ð²Ð¸Ñ‚ÑŒ Ð¾Ñ‚Ð·Ñ‹Ð² Ð¾ Ð½Ð°ÑˆÐµÐ¼ ÑÐµÑ€Ð²Ð¸ÑÐµ?' : 'Rahmat! Bizning xizmatimiz haqida sharh qoldirasizmi?') ?>;
        <?php endif; ?>
    </script>
    <div id="review-modal" class="review-modal" hidden aria-hidden="true">
        <div class="review-modal__backdrop" data-review-modal-close></div>
        <div class="review-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="review-modal-title">
            <h3 id="review-modal-title"><?= $lang === 'ru' ? 'ÐžÑÑ‚Ð°Ð²Ð¸Ñ‚ÑŒ Ð¾Ñ‚Ð·Ñ‹Ð²?' : 'Sharh qoldirasizmi?' ?></h3>
            <p id="review-modal-message" data-review-message>
                <?= $lang === 'ru' ? 'Ð¡Ð¿Ð°ÑÐ¸Ð±Ð¾! ÐÐµ Ð¼Ð¾Ð³Ð»Ð¸ Ð±Ñ‹ Ð²Ñ‹ Ð¾ÑÑ‚Ð°Ð²Ð¸Ñ‚ÑŒ Ð¾Ñ‚Ð·Ñ‹Ð² Ð¾ Ð½Ð°ÑˆÐµÐ¼ ÑÐµÑ€Ð²Ð¸ÑÐµ?' : 'Rahmat! Bizning xizmatimiz haqida sharh qoldirasizmi?' ?>
            </p>
            <div class="review-modal__actions">
                <button type="button" class="review-modal__btn review-modal__btn--secondary" data-review-modal-close>
                    x
                </button>
                <?php if (!empty($seo['google_review_url'])): ?>
                <a href="<?= e($seo['google_review_url']) ?>" class="review-modal__btn review-modal__btn--review" target="_blank" rel="noopener noreferrer" data-review-open data-cta="review_modal" data-cta-manual>
                    <?= $lang === 'ru' ? 'ÐžÑÑ‚Ð°Ð²Ð¸Ñ‚ÑŒ Ð¾Ñ‚Ð·Ñ‹Ð²' : 'Sharh qoldirish' ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (defined('GTM_ID')): ?>
    <script>
        (function() {
            if (typeof window.__loadGTM !== 'function') return;
            var triggered = false;
            var load = function() {
                if (triggered) return;
                triggered = true;
                window.__loadGTM();
            }; 
            var events = ['pointerdown', 'keydown', 'touchstart', 'scroll', 'wheel'];
            events.forEach(function(evt) {
                window.addEventListener(evt, load, { once: true, passive: true });
            });
        })();
    </script>
    <?php endif; ?>
    <script src="<?= BASE_URL ?>/js/param-persistence.js" defer></script>
    <script src="<?= BASE_URL ?>/js/link-tracking.js?v=<?= assetVersion('/js/link-tracking.js') ?>" defer></script>
    <script src="<?= BASE_URL ?>/js/hold-morph.js" defer></script>
    <style>
    .review-modal[hidden] { display: none !important; }
    .review-modal {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .review-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
    }
    .review-modal__dialog {
        position: relative;
        z-index: 1;
        width: min(100%, 520px);
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    }
    .review-modal__dialog h3 {
        margin: 0 0 8px;
        font-size: 20px;
    }
    .review-modal__dialog p {
        margin: 0 0 18px;
        color: #475569;
        line-height: 1.6;
    }
    .review-modal__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }
    .review-modal__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 14px;
        border-radius: 10px;
        text-decoration: none;
        border: 1px solid transparent;
        cursor: pointer;
        font: inherit;
    }
    .review-modal__btn--secondary {
            position: absolute;
            background: none;
            color: #000000;
            top: 0;
            right: 6px;
    }
    .review-modal__btn--primary {
        background: #2563eb;
        color: #fff;
    }
    .review-modal__btn--review {
        background: #3f3f44;
        color: #fff;
    }
    body.review-modal-open {
        overflow: hidden;
    }
    </style>
</body>
</html>