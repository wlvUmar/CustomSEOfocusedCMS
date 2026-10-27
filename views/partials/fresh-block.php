<?php
// path: ./views/partials/fresh-block.php
// Site-wide freshness block, written by scripts/freshness-run.php (table fresh_blocks).
// Renders nothing when there is no live row. Styling: .c-fresh* classes (owner maintains CSS).
require_once BASE_PATH . '/models/FreshBlock.php';
$freshBlock = FreshBlock::getLive($lang);
if ($freshBlock && ($freshBlock['demand'] !== '' || $freshBlock['season'] !== '')):
?>
<aside class="c-fresh" aria-label="<?= $lang === 'ru' ? 'Обновления' : 'Yangilanishlar' ?>">
    <div class="container">
        <p class="c-fresh__date">
            <?= $lang === 'ru' ? 'Обновлено' : 'Yangilangan' ?>:
            <time datetime="<?= e(date('Y-m-d', strtotime($freshBlock['updated']))) ?>"><?= e($freshBlock['updated']) ?></time>
        </p>
        <?php if ($freshBlock['demand'] !== ''): ?>
        <p class="c-fresh__line"><?= e($freshBlock['demand']) ?></p>
        <?php endif; ?>
        <?php if ($freshBlock['season'] !== ''): ?>
        <p class="c-fresh__line"><?= e($freshBlock['season']) ?></p>
        <?php endif; ?>
    </div>
</aside>
<?php endif; ?>
