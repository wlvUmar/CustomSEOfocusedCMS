<?php
// path: ./views/partials/hero-art/flame.php — stove top with four glowing burners (decorative)
$burners = [[132, 112], [308, 112], [112, 184], [328, 184]];
$knobs   = [78, 166, 274, 362];
?>
        <div class="hero__art" aria-hidden="true">
            <svg viewBox="0 0 440 340" focusable="false">
                <ellipse cx="220" cy="326" rx="190" ry="9" fill="#000" opacity=".55"/>
                <path d="M52 40L388 40L430 238L10 238Z" fill="#14100e" stroke="#4a3c32" stroke-width="2"/>
                <path d="M52 40L388 40L392 56L48 56Z" fill="#2a221d"/>
                <g fill="none" stroke-linecap="round">
                    <?php foreach ($burners as [$x, $y]): ?>
                    <g>
                        <ellipse class="fl-burn" cx="<?= $x ?>" cy="<?= $y ?>" rx="62" ry="24" fill="#d63a14" opacity=".35" stroke="none"/>
                        <ellipse cx="<?= $x ?>" cy="<?= $y ?>" rx="44" ry="16" stroke="#4a3c32" stroke-width="4" fill="#0c0908"/>
                        <ellipse class="fl-burn" cx="<?= $x ?>" cy="<?= $y ?>" rx="30" ry="11" stroke="#ff8a3d" stroke-width="3" stroke-dasharray="3 5"/>
                        <ellipse cx="<?= $x ?>" cy="<?= $y ?>" rx="14" ry="5" fill="#2a221d" stroke="#6b5a4c" stroke-width="2"/>
                    </g>
                    <?php endforeach; ?>
                </g>
                <rect x="10" y="238" width="420" height="72" rx="8" fill="#1c1815" stroke="#4a3c32" stroke-width="2"/>
                <?php foreach ($knobs as $i => $x): ?>
                <g>
                    <circle cx="<?= $x ?>" cy="274" r="21" fill="#0c0908" stroke="#ff8a3d" stroke-width="3"/>
                    <path d="M<?= $x ?> 274V258" stroke="#ff8a3d" stroke-width="4" stroke-linecap="round" transform="rotate(<?= ($i * 17) % 70 - 30 ?> <?= $x ?> 274)"/>
                </g>
                <?php endforeach; ?>
            </svg>
        </div>
