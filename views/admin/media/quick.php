<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="mq" id="mq">
    <div class="page-header">
        <h1><i data-feather="zap"></i> Ð‘Ñ‹ÑÑ‚Ñ€Ð¾Ðµ Ð´Ð¾Ð±Ð°Ð²Ð»ÐµÐ½Ð¸Ðµ Ñ„Ð¾Ñ‚Ð¾</h1>
        <div class="page-actions">
            <a href="<?= BASE_URL ?>/admin/media" class="btn btn-secondary">â† ÐœÐµÐ´Ð¸Ð°Ñ‚ÐµÐºÐ°</a>
        </div>
    </div>

    <p class="mq-hint">
        ÐŸÐµÑ€ÐµÑ‚Ð°Ñ‰Ð¸Ñ‚Ðµ Ñ„Ð¾Ñ‚Ð¾ ÑÑŽÐ´Ð° (Ð¸Ð»Ð¸ Ð²ÑÑ‚Ð°Ð²ÑŒÑ‚Ðµ Ð¸Ð· Ð±ÑƒÑ„ÐµÑ€Ð°: Ctrl+V). Ð¤Ð°Ð¹Ð» Ñ Ð¸Ð¼ÐµÐ½ÐµÐ¼ <code>slug-1.jpg</code> ÑÐ°Ð¼ Ð¿Ñ€Ð¸Ð²ÑÐ¶ÐµÑ‚ÑÑ Ðº ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ðµ <code>slug</code>;
        ÑÑƒÑ„Ñ„Ð¸ÐºÑ <code>-hero</code>, <code>-banner</code>, <code>-content</code> Ð²Ñ‹Ð±Ð¸Ñ€Ð°ÐµÑ‚ Ñ€Ð°Ð·Ð´ÐµÐ», Ð¸Ð½Ð°Ñ‡Ðµ â€” Â«Ð³Ð°Ð»ÐµÑ€ÐµÑÂ». Alt-Ñ‚ÐµÐºÑÑ‚ RU/UZ Ð¿Ð¾Ð´ÑÑ‚Ð°Ð²Ð»ÑÐµÑ‚ÑÑ Ð¸Ð· Ð·Ð°Ð³Ð¾Ð»Ð¾Ð²ÐºÐ° ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñ‹ â€”
        ÑÑ‚Ð¾ ÑˆÐ°Ð±Ð»Ð¾Ð½, Ð° Ð½Ðµ Ð¾Ð¿Ð¸ÑÐ°Ð½Ð¸Ðµ ÐºÐ°Ñ€Ñ‚Ð¸Ð½ÐºÐ¸, Ð¿Ð¾Ð¿Ñ€Ð°Ð²ÑŒÑ‚Ðµ Ð¿Ñ€Ð¸ Ð½ÐµÐ¾Ð±Ñ…Ð¾Ð´Ð¸Ð¼Ð¾ÑÑ‚Ð¸. ÐœÐ°ÐºÑ. Ñ€Ð°Ð·Ð¼ÐµÑ€ Ñ„Ð°Ð¹Ð»Ð°: <?= e($maxUploadMb) ?> ÐœÐ‘.
    </p>

    <p class="mq-hint mq-usage">
        ÐœÐµÑÑ‚Ð¾ Ð¿Ð¾Ð´ Ñ„Ð¾Ñ‚Ð¾: Ð·Ð°Ð½ÑÑ‚Ð¾ <strong><?= e($usageMb) ?> ÐœÐ‘</strong><?= !empty($quotaMb) ? ' Ð¸Ð· ' . e($quotaMb) . ' ÐœÐ‘ (Ð»Ð¸Ð¼Ð¸Ñ‚ UPLOAD_QUOTA_MB)' : ' (Ð»Ð¸Ð¼Ð¸Ñ‚ Ð½Ðµ Ð·Ð°Ð´Ð°Ð½ â€” Ð´Ð¾Ð±Ð°Ð²ÑŒÑ‚Ðµ <code>UPLOAD_QUOTA_MB</code> Ð² .env)' ?>.
        Ð¥Ð¾ÑÑ‚Ð¸Ð½Ð³ Ð¼Ð°Ð»ÐµÐ½ÑŒÐºÐ¸Ð¹: ÑÑ‚Ð°Ð²ÑŒÑ‚Ðµ Ð¼Ð°ÐºÑ. ÑˆÐ¸Ñ€Ð¸Ð½Ñƒ 1366 Ð¸ Ñ„Ð¾Ñ€Ð¼Ð°Ñ‚ WebP.
    </p>

    <section class="mq-gen" id="mqStock" aria-labelledby="mqStockTitle">
        <h2 id="mqStockTitle">ÐÐ°Ð¹Ñ‚Ð¸ Ñ„Ð¾Ñ‚Ð¾ Ð² Ð¸Ð½Ñ‚ÐµÑ€Ð½ÐµÑ‚Ðµ</h2>
        <?php if (empty($stockReady)): ?>
            <div class="mq-warn">ÐÐµ Ð·Ð°Ð´Ð°Ð½ ÐºÐ»ÑŽÑ‡ Ð² <code>.env</code>: Ð´Ð¾Ð±Ð°Ð²ÑŒÑ‚Ðµ <code>PIXABAY_API_KEY</code> (Ð±ÐµÑÐ¿Ð»Ð°Ñ‚Ð½Ð¾: <a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener noreferrer">pixabay.com/api/docs</a>) Ð¸Ð»Ð¸ <code>PEXELS_API_KEY</code>.</div>
        <?php else: ?>
        <div class="mq-gen-grid">
            <label>Ð¡Ñ‚Ñ€Ð°Ð½Ð¸Ñ†Ð°
                <input type="text" id="mqStockPage" list="mqPages" placeholder="slug ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñ‹" autocomplete="off">
            </label>
            <label class="mq-stock-q">Ð—Ð°Ð¿Ñ€Ð¾Ñ (Ð¿Ð¾Ð´ÑÑ‚Ð°Ð²Ð¸Ñ‚ÑÑ Ð¸Ð· ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñ‹, Ð»ÑƒÑ‡ÑˆÐµ Ð¿Ð¾-Ð°Ð½Ð³Ð»Ð¸Ð¹ÑÐºÐ¸)
                <input type="text" id="mqStockQuery" placeholder="refrigerator kitchen" autocomplete="off" maxlength="100">
            </label>
            <label>Ð Ð°Ð·Ð´ÐµÐ»
                <select id="mqStockSection">
                    <option value="gallery">Ð“Ð°Ð»ÐµÑ€ÐµÑ</option><option value="content">ÐšÐ¾Ð½Ñ‚ÐµÐ½Ñ‚</option>
                    <option value="hero">Hero</option><option value="banner">Ð‘Ð°Ð½Ð½ÐµÑ€</option>
                </select>
            </label>
        </div>
        <div class="mq-gen-actions">
            <button type="button" class="btn btn-secondary" id="mqStockSearchBtn">Ð˜ÑÐºÐ°Ñ‚ÑŒ</button>
            <button type="button" class="btn btn-primary" id="mqStockAdd" disabled>Ð”Ð¾Ð±Ð°Ð²Ð¸Ñ‚ÑŒ Ð²Ñ‹Ð±Ñ€Ð°Ð½Ð½Ñ‹Ðµ (0)</button>
            <span id="mqStockStatus" class="mq-gen-status" aria-live="polite"></span>
        </div>
        <div id="mqStockAlt" class="mq-stock-alt" hidden></div>
        <div id="mqStockGrid" class="mq-stock-grid"></div>
        <div class="mq-gen-actions">
            <button type="button" class="btn btn-secondary" id="mqStockMore" hidden>ÐŸÐ¾ÐºÐ°Ð·Ð°Ñ‚ÑŒ ÐµÑ‰Ñ‘</button>
        </div>
        <p class="mq-note">ÐžÑ‚Ð¼ÐµÑ‚ÑŒÑ‚Ðµ Ñ„Ð¾Ñ‚Ð¾ ÐºÐ»Ð¸ÐºÐ¾Ð¼ Ð¸ Ð½Ð°Ð¶Ð¼Ð¸Ñ‚Ðµ Â«Ð”Ð¾Ð±Ð°Ð²Ð¸Ñ‚ÑŒ Ð²Ñ‹Ð±Ñ€Ð°Ð½Ð½Ñ‹ÐµÂ» â€” Ð¾Ð½Ð¸ ÑÑ€Ð°Ð·Ñƒ Ð·Ð°Ð³Ñ€ÑƒÐ·ÑÑ‚ÑÑ Ð½Ð° ÑÐ°Ð¹Ñ‚ Ð¸ Ð¿Ñ€Ð¸ÐºÑ€ÐµÐ¿ÑÑ‚ÑÑ Ðº ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ðµ. Ð¤Ð¾Ñ‚Ð¾ Ð¾Ð±Ñ‰Ð¸Ðµ (Ð½Ð°Ð¿Ñ€Ð¸Ð¼ÐµÑ€, Â«Ñ…Ð¾Ð»Ð¾Ð´Ð¸Ð»ÑŒÐ½Ð¸ÐºÂ»), Ð° Ð½Ðµ ÐºÐ¾Ð½ÐºÑ€ÐµÑ‚Ð½Ð°Ñ Ð¼Ð¾Ð´ÐµÐ»ÑŒ. <span id="mqStockCredit">Ð¤Ð¾Ñ‚Ð¾ Ð±ÐµÑÐ¿Ð»Ð°Ñ‚Ð½Ñ‹Ðµ, ÑƒÐºÐ°Ð·Ñ‹Ð²Ð°Ñ‚ÑŒ Ð°Ð²Ñ‚Ð¾Ñ€Ð° Ð½Ðµ Ð¾Ð±ÑÐ·Ð°Ñ‚ÐµÐ»ÑŒÐ½Ð¾.</span></p>
        <?php endif; ?>
    </section>

    <section class="mq-gen" id="mqGen" aria-labelledby="mqGenTitle">
        <h2 id="mqGenTitle">Ð¡Ð¾Ð·Ð´Ð°Ñ‚ÑŒ Ð¸Ð·Ð¾Ð±Ñ€Ð°Ð¶ÐµÐ½Ð¸Ñ Ñ Ð¿Ð¾Ð¼Ð¾Ñ‰ÑŒÑŽ Ð˜Ð˜</h2>
        <?php if (empty($gen['configured'])): ?>
            <div class="mq-warn">ÐÐµ Ð·Ð°Ð´Ð°Ð½ <code>OPENROUTER_API_KEY</code> Ð² <code>.env</code> â€” Ð³ÐµÐ½ÐµÑ€Ð°Ñ†Ð¸Ñ Ð½ÐµÐ´Ð¾ÑÑ‚ÑƒÐ¿Ð½Ð°.</div>
        <?php elseif (empty($gen['ready'])): ?>
            <div class="mq-warn">ÐÐµÑ‚ Ñ‚Ð°Ð±Ð»Ð¸Ñ†Ñ‹ <code>image_generations</code> â€” Ð·Ð°Ð¿ÑƒÑÑ‚Ð¸Ñ‚Ðµ <code>php migrate.php</code> (Ð¼Ð¸Ð³Ñ€Ð°Ñ†Ð¸Ñ 019), Ð·Ð°Ñ‚ÐµÐ¼ Ð¾Ð±Ð½Ð¾Ð²Ð¸Ñ‚Ðµ ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñƒ.</div>
        <?php else: ?>
        <div class="mq-gen-grid">
            <label class="mq-gen-wide">Ð¡Ñ‚Ñ€Ð°Ð½Ð¸Ñ†Ð°
                <input type="text" id="mqGenPage" list="mqPages" placeholder="slug ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñ‹" autocomplete="off">
            </label>
            <label class="mq-gen-wide">ÐžÐ¿Ð¸ÑÐ°Ð½Ð¸Ðµ (Ð¿Ñ€Ð°Ð²ÑŒÑ‚Ðµ ÐºÐ°Ðº Ð½ÑƒÐ¶Ð½Ð¾)
                <textarea id="mqGenPrompt" rows="3" maxlength="600" placeholder="Ð’Ñ‹Ð±ÐµÑ€Ð¸Ñ‚Ðµ ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñƒ â€” Ð¾Ð¿Ð¸ÑÐ°Ð½Ð¸Ðµ Ð¿Ð¾Ð´ÑÑ‚Ð°Ð²Ð¸Ñ‚ÑÑ Ð¸Ð· ÐµÑ‘ Ð·Ð°Ð³Ð¾Ð»Ð¾Ð²ÐºÐ°"></textarea>
            </label>
            <label>Ð¡ÐºÐ¾Ð»ÑŒÐºÐ¾
                <select id="mqGenCount">
                    <option value="1">1</option><option value="2" selected>2</option><option value="3">3</option><option value="4">4</option>
                </select>
            </label>
            <label>Ð¤Ð¾Ñ€Ð¼Ð°Ñ‚
                <select id="mqGenAspect">
                    <?php foreach ($gen['aspects'] as $a): ?><option value="<?= e($a) ?>"><?= e($a) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Ð Ð°Ð·Ð´ÐµÐ»
                <select id="mqGenSection">
                    <option value="gallery">Ð“Ð°Ð»ÐµÑ€ÐµÑ</option><option value="content">ÐšÐ¾Ð½Ñ‚ÐµÐ½Ñ‚</option>
                    <option value="hero">Hero</option><option value="banner">Ð‘Ð°Ð½Ð½ÐµÑ€</option>
                </select>
            </label>
            <label>ÐœÐ¾Ð´ÐµÐ»ÑŒ
                <select id="mqGenModel">
                    <?php foreach ($gen['models'] as $id => $label): ?><option value="<?= e($id) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="mq-gen-actions">
            <button type="button" class="btn btn-primary" id="mqGenBtn">Ð¡Ð¾Ð·Ð´Ð°Ñ‚ÑŒ</button>
            <span id="mqGenStatus" class="mq-gen-status" aria-live="polite"></span>
            <span class="mq-gen-quota" id="mqGenQuota">Ð¡ÐµÐ³Ð¾Ð´Ð½Ñ ÑÐ¾Ð·Ð´Ð°Ð½Ð¾: <?= (int)$gen['used'] ?> Ð¸Ð· <?= (int)$gen['limit'] ?></span>
        </div>
        <p class="mq-note">Ð ÐµÐ·ÑƒÐ»ÑŒÑ‚Ð°Ñ‚Ñ‹ Ð¿Ð¾ÑÐ²ÑÑ‚ÑÑ Ð² ÑÐ¿Ð¸ÑÐºÐµ Ð½Ð¸Ð¶Ðµ â€” Ð½Ð¸Ñ‡ÐµÐ³Ð¾ Ð½Ðµ ÑÐ¾Ñ…Ñ€Ð°Ð½ÑÐµÑ‚ÑÑ, Ð¿Ð¾ÐºÐ° Ð²Ñ‹ Ð½Ðµ Ð½Ð°Ð¶Ð¼Ñ‘Ñ‚Ðµ Â«Ð—Ð°Ð³Ñ€ÑƒÐ·Ð¸Ñ‚ÑŒ Ð¸ Ð¿Ñ€Ð¸ÐºÑ€ÐµÐ¿Ð¸Ñ‚ÑŒÂ». Ð˜Ð˜ Ñ€Ð¸ÑÑƒÐµÑ‚ Â«Ð¿Ð¾Ñ…Ð¾Ð¶Ð¸Ð¹Â» Ð¿Ñ€ÐµÐ´Ð¼ÐµÑ‚, Ð° Ð½Ðµ ÐºÐ¾Ð½ÐºÑ€ÐµÑ‚Ð½ÑƒÑŽ Ð¼Ð¾Ð´ÐµÐ»ÑŒ: Ð½Ðµ Ð²Ñ‹Ð´Ð°Ð²Ð°Ð¹Ñ‚Ðµ Ñ‚Ð°ÐºÐ¸Ðµ ÐºÐ°Ñ€Ñ‚Ð¸Ð½ÐºÐ¸ Ð·Ð° Ñ„Ð¾Ñ‚Ð¾ Ñ€ÐµÐ°Ð»ÑŒÐ½Ð¾Ð³Ð¾ Ñ‚Ð¾Ð²Ð°Ñ€Ð°. ÐŸÐ»Ð°Ñ‚Ð½Ð¾ (ÑÐ¿Ð¸ÑÑ‹Ð²Ð°ÐµÑ‚ÑÑ Ñ Ð±Ð°Ð»Ð°Ð½ÑÐ° OpenRouter), ÐµÑÑ‚ÑŒ Ð´Ð½ÐµÐ²Ð½Ð¾Ð¹ Ð»Ð¸Ð¼Ð¸Ñ‚.</p>
        <?php endif; ?>
    </section>

    <div class="mq-drop" id="mqDrop" tabindex="0" role="button" aria-label="Ð”Ð¾Ð±Ð°Ð²Ð¸Ñ‚ÑŒ Ñ„Ð¾Ñ‚Ð¾">
        <input type="file" id="mqFile" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
        <strong>ÐŸÐµÑ€ÐµÑ‚Ð°Ñ‰Ð¸Ñ‚Ðµ Ñ„Ð¾Ñ‚Ð¾ ÑÑŽÐ´Ð°</strong>
        <span>Ð¸Ð»Ð¸ Ð½Ð°Ð¶Ð¼Ð¸Ñ‚Ðµ, Ñ‡Ñ‚Ð¾Ð±Ñ‹ Ð²Ñ‹Ð±Ñ€Ð°Ñ‚ÑŒ Â· Ð¼Ð¾Ð¶Ð½Ð¾ Ð²ÑÑ‚Ð°Ð²Ð¸Ñ‚ÑŒ Ð¸Ð· Ð±ÑƒÑ„ÐµÑ€Ð° (Ctrl+V)</span>
    </div>

    <div class="mq-bulk" id="mqBulk" hidden>
        <label>Ð”Ð»Ñ Ñ„Ð°Ð¹Ð»Ð¾Ð² Ð±ÐµÐ· ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñ‹:
            <input type="text" id="mqBulkPage" list="mqPages" placeholder="slug ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ñ‹" autocomplete="off">
        </label>
        <button type="button" class="btn btn-secondary" id="mqApplyPage">ÐŸÑ€Ð¸Ð¼ÐµÐ½Ð¸Ñ‚ÑŒ</button>
        <label>Ð Ð°Ð·Ð´ÐµÐ» Ð´Ð»Ñ Ð²ÑÐµÑ…:
            <select id="mqBulkSection">
                <option value="">â€” Ð½Ðµ Ð¼ÐµÐ½ÑÑ‚ÑŒ â€”</option>
                <option value="gallery">Ð“Ð°Ð»ÐµÑ€ÐµÑ</option>
                <option value="content">ÐšÐ¾Ð½Ñ‚ÐµÐ½Ñ‚</option>
                <option value="hero">Hero</option>
                <option value="banner">Ð‘Ð°Ð½Ð½ÐµÑ€</option>
            </select>
        </label>
        <label>ÐœÐ°ÐºÑ. ÑˆÐ¸Ñ€Ð¸Ð½Ð°:
            <select id="mqMaxWidth" title="Ð£Ð¼ÐµÐ½ÑŒÑˆÐ°ÐµÑ‚ ÑÐ»Ð¸ÑˆÐºÐ¾Ð¼ Ð±Ð¾Ð»ÑŒÑˆÐ¸Ðµ Ñ„Ð¾Ñ‚Ð¾ Ð¿ÐµÑ€ÐµÐ´ Ð·Ð°Ð³Ñ€ÑƒÐ·ÐºÐ¾Ð¹ (Ð±Ñ‹ÑÑ‚Ñ€ÐµÐµ Ð·Ð°Ð³Ñ€ÑƒÐ·ÐºÐ° Ð¸ ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ð°)">
                <option value="0">ÐšÐ°Ðº ÐµÑÑ‚ÑŒ</option>
                <option value="2000">2000 px</option>
                <option value="1600">1600 px</option>
                <option value="1366" selected>1366 px (Ñ€ÐµÐºÐ¾Ð¼ÐµÐ½Ð´ÑƒÐµÑ‚ÑÑ)</option>
                <option value="1200">1200 px</option>
                <option value="800">800 px</option>
            </select>
        </label>
        <label>Ð¤Ð¾Ñ€Ð¼Ð°Ñ‚:
            <select id="mqFormat">
                <option value="">ÐšÐ°Ðº ÐµÑÑ‚ÑŒ</option>
                <option value="webp" selected>WebP (Ð»ÐµÐ³Ñ‡Ðµ, Ñ€ÐµÐºÐ¾Ð¼ÐµÐ½Ð´ÑƒÐµÑ‚ÑÑ)</option>
                <option value="jpeg">JPEG</option>
            </select>
        </label>
        <span class="mq-spacer"></span>
        <button type="button" class="btn btn-secondary" id="mqClear">ÐžÑ‡Ð¸ÑÑ‚Ð¸Ñ‚ÑŒ ÑÐ¿Ð¸ÑÐ¾Ðº</button>
        <button type="button" class="btn btn-primary" id="mqUpload">Ð—Ð°Ð³Ñ€ÑƒÐ·Ð¸Ñ‚ÑŒ Ð¸ Ð¿Ñ€Ð¸ÐºÑ€ÐµÐ¿Ð¸Ñ‚ÑŒ</button>
    </div>

    <div id="mqRows" class="mq-rows"></div>
    <div id="mqSummary" class="mq-summary" aria-live="polite"></div>

    <datalist id="mqPages"></datalist>

    <!-- Crop window (Cropper.js) -->
    <div class="mq-modal" id="mqCrop" hidden role="dialog" aria-modal="true" aria-label="ÐžÐ±Ñ€ÐµÐ·ÐºÐ° Ñ„Ð¾Ñ‚Ð¾">
        <div class="mq-modal__card">
            <div class="mq-modal__stage" id="mqCropStage"></div>
            <div class="mq-modal__bar">
                <div class="mq-ratios" id="mqRatios">
                    <button type="button" data-ratio="free" class="is-active">Ð¡Ð²Ð¾Ð±Ð¾Ð´Ð½Ð¾</button>
                    <button type="button" data-ratio="1.3333333">4:3</button>
                    <button type="button" data-ratio="1.7777778">16:9</button>
                    <button type="button" data-ratio="1">1:1</button>
                    <button type="button" data-ratio="0.75">3:4</button>
                </div>
                <button type="button" class="btn btn-secondary" data-rotate="-90" aria-label="ÐŸÐ¾Ð²ÐµÑ€Ð½ÑƒÑ‚ÑŒ Ð²Ð»ÐµÐ²Ð¾">âŸ²</button>
                <button type="button" class="btn btn-secondary" data-rotate="90" aria-label="ÐŸÐ¾Ð²ÐµÑ€Ð½ÑƒÑ‚ÑŒ Ð²Ð¿Ñ€Ð°Ð²Ð¾">âŸ³</button>
                <span class="mq-modal__info" id="mqCropInfo"></span>
                <span class="mq-spacer"></span>
                <button type="button" class="btn btn-secondary" id="mqCropCancel">ÐžÑ‚Ð¼ÐµÐ½Ð°</button>
                <button type="button" class="btn btn-primary" id="mqCropApply">ÐŸÑ€Ð¸Ð¼ÐµÐ½Ð¸Ñ‚ÑŒ</button>
            </div>
        </div>
    </div>
</div>

<script>window.MQ_PAGES = <?= json_encode($quickPages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" defer></script>
<script src="<?= BASE_URL ?>/js/admin/media-quick.js?v=<?= assetVersion('/js/admin/media-quick.js') ?>" defer></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
