<?php
// path: ./views/partials/review-strip.php
// Relocated verbatim from views/templates/page.php — no logic changes.
?>
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
