<?php
// path: ./views/partials/fresh-block.php
// Site-wide freshness block, written by scripts/freshness-run.php (table fresh_blocks).
// Reuses .review-strip so base CSS and every page theme style it — no dedicated CSS.
// Renders nothing when there is no live row.
require_once BASE_PATH . '/models/FreshBlock.php';
$freshBlock = FreshBlock::getLive($lang);
if ($freshBlock && ($freshBlock['demand'] !== '' || $freshBlock['season'] !== '')):
?>
<section class="review-strip" data-animate="fade-up" aria-label="<?= $lang === 'ru' ? 'Обновления' : 'Yangilanishlar' ?>">
    <p class="review-strip__text">
        <strong><?= $lang === 'ru' ? 'Обновлено' : 'Yangilangan' ?>:
            <time datetime="<?= e(date('Y-m-d', strtotime($freshBlock['updated']))) ?>"><?= e($freshBlock['updated']) ?></time></strong>
        <?php if ($freshBlock['demand'] !== ''): ?><br><?= e($freshBlock['demand']) ?><?php endif; ?>
        <?php if ($freshBlock['season'] !== ''): ?><br><?= e($freshBlock['season']) ?><?php endif; ?>
    </p>
</section>
<?php endif; ?>
