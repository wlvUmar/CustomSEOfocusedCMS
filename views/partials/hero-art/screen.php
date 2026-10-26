<?php
// path: ./views/partials/hero-art/screen.php — brand ticker under the cinema title card.
$tickerBrands = ['Samsung', 'LG', 'Sony', 'Xiaomi', 'Hisense', 'TCL', 'Philips', 'Artel'];
?>
        <div class="hero__ticker" aria-hidden="true">
            <div class="hero__ticker-track">
                <?php for ($i = 0; $i < 2; $i++): foreach ($tickerBrands as $b): ?><span><?= e($b) ?></span><?php endforeach; endfor; ?>
            </div>
        </div>
