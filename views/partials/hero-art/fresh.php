<?php
// path: ./views/partials/hero-art/fresh.php — "get a price" quote card.
// The fields are illustration only; the button is a real link to the page's main contact CTA.
$ru = ($lang ?? 'ru') === 'ru';
$quoteHref = (!empty($floatingCta['url']) && ($floatingCta['type'] ?? 'none') !== 'none')
    ? $floatingCta['url']
    : 'tel:' . preg_replace('/[^0-9+]/', '', $contactUi['phone'] ?? ($seo['phone'] ?? ''));
$quoteExternal = str_starts_with($quoteHref, 'http');
?>
        <div class="hero__art">
            <div class="quote">
                <div class="quote__t"><?= $ru ? 'Узнайте цену за 10 минут' : '10 daqiqada narxni bilib oling' ?></div>
                <div class="quote__s"><?= $ru ? 'Отправьте фото — назовём стоимость' : 'Rasm yuboring — narxini aytamiz' ?></div>
                <div class="quote__f" aria-hidden="true"><b><?= $ru ? 'Фото' : 'Rasm' ?></b> <?= $ru ? 'техники или шильдика' : 'texnika yoki shildik' ?></div>
                <div class="quote__f" aria-hidden="true"><?= $ru ? 'Модель:' : 'Model:' ?> <b>Samsung RB37…</b></div>
                <a class="quote__b" href="<?= e($quoteHref) ?>"<?= $quoteExternal ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= $ru ? 'Узнать цену' : 'Narxni bilish' ?></a>
                <div class="quote__m"><span><?= $ru ? 'Ответ за ~10 минут' : 'Javob ~10 daqiqada' ?></span><span><?= $ru ? 'Без обязательств' : 'Majburiyatsiz' ?></span></div>
            </div>
        </div>
