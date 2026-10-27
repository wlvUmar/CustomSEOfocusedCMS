<?php
// path: ./views/partials/cta-block.php
// Inline contact CTA (call + chat; the bot link only on the closing block). Not sticky.
// Expects $ctaPlace = 'mid' (after the first content block) | 'end' (after the FAQ).
// Copy reuses claims already on the site (same-day payment, Tashkent). Styled by .cta-inline* (theme tokens).
$ctaPlace = ($ctaPlace ?? 'mid') === 'end' ? 'end' : 'mid';
$ctaPhone = preg_replace('/[^0-9+]/', '', $contactUi['phone'] ?? ($seo['phone'] ?? ''));
$ctaChat = (($floatingCta['type'] ?? 'none') !== 'none' && !empty($floatingCta['url'])) ? $floatingCta : null;
$ctaBotUrl = (!empty($botModalEnabled) && defined('TELEGRAM_BOT_URL'))
    ? TELEGRAM_BOT_URL . '?start=' . urlencode($page['clientID'] ?? '')
    : '';
if ($ctaPhone === '' && !$ctaChat) return;

if ($ctaPlace === 'mid') {
    $ctaTitle = $lang === 'ru' ? 'Хотите узнать цену сейчас?' : 'Narxni hoziroq bilmoqchimisiz?';
    $ctaSub = $lang === 'ru' ? 'Позвоните или напишите — оценим технику в день обращения.' : 'Qo\'ng\'iroq qiling yoki yozing — texnikani murojaat kuni baholaymiz.';
} else {
    $ctaTitle = $lang === 'ru' ? 'Остались вопросы?' : 'Savollaringiz qoldimi?';
    $ctaSub = $lang === 'ru' ? 'Свяжитесь с нами — ответим и оценим технику в день обращения.' : 'Biz bilan bog\'laning — javob beramiz va texnikani murojaat kuni baholaymiz.';
}
?>
<section class="cta-inline cta-inline--<?= $ctaPlace ?>" aria-label="<?= e($ctaTitle) ?>">
    <div class="cta-inline__text">
        <p class="cta-inline__title"><?= e($ctaTitle) ?></p>
        <p class="cta-inline__sub"><?= e($ctaSub) ?></p>
    </div>
    <div class="cta-inline__actions">
        <?php if ($ctaPhone !== ''): ?>
        <a href="tel:<?= e($ctaPhone) ?>" class="cta-inline__btn" data-cta="<?= $ctaPlace ?>_call">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/></svg>
            <?= $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq' ?>
        </a>
        <?php endif; ?>
        <?php if ($ctaChat): ?>
        <a href="<?= e($ctaChat['url']) ?>" class="cta-inline__btn cta-inline__btn--ghost" target="_blank" rel="noopener noreferrer"
           data-track-click="1" data-cta="<?= $ctaPlace ?>_chat">
            <?= e($ctaChat['label'] ?? ($lang === 'ru' ? 'Написать' : 'Yozish')) ?>
        </a>
        <?php endif; ?>
        <?php if ($ctaPlace === 'end' && $ctaBotUrl !== ''): ?>
        <a href="<?= e($ctaBotUrl) ?>" class="cta-inline__btn cta-inline__btn--ghost" target="_blank" rel="noopener noreferrer"
           data-track-click="1" data-cta="end_bot" onclick="if(window.markBotModalSeen)markBotModalSeen()">
            <?= $lang === 'ru' ? 'Отправить фото боту' : 'Botga rasm yuborish' ?>
        </a>
        <?php endif; ?>
    </div>
</section>
