<?php
// path: ./views/partials/bot-card.php
// Small standalone "new feature" card for the Telegram sell-by-photo bot. Inline, not a popup.
// Rendered only when the bot is healthy ($botModalEnabled). Copy mirrors the bot popup. Styled by .cta-inline* tokens.
if (empty($botModalEnabled) || !defined('TELEGRAM_BOT_URL')) return;
$botCardUrl = TELEGRAM_BOT_URL . '?start=' . urlencode($page['clientID'] ?? '');
?>
<section class="cta-inline cta-inline--bot" id="bot-card" aria-labelledby="bot-card-title">
    <div class="cta-inline__text">
        <p class="cta-inline__title" id="bot-card-title">
            <span class="cta-inline__badge"><?= $lang === 'ru' ? 'Новое' : 'Yangi' ?></span>
            <?= $lang === 'ru' ? 'Продайте технику онлайн' : 'Texnikani onlayn soting' ?>
        </p>
        <p class="cta-inline__sub">
            <?= $lang === 'ru'
                ? 'Отправьте фото боту — если товар нам подходит, мы предложим цену покупки.'
                : 'Botga rasm yuboring — agar tovar bizga mos kelsa, xarid narximizni taklif qilamiz.' ?>
        </p>
    </div>
    <div class="cta-inline__actions">
        <a href="<?= e($botCardUrl) ?>" class="cta-inline__btn" target="_blank" rel="noopener noreferrer"
           data-track-click="1" data-cta="bot_card" onclick="if(window.markBotModalSeen)markBotModalSeen()">
            <?= $lang === 'ru' ? 'Открыть Telegram-бот' : 'Telegram-botni ochish' ?>
        </a>
    </div>
</section>
