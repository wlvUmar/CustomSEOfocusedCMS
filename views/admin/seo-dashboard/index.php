<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>
<?php
$gscConnected = !empty($gsc['connected']);
$siteRoot = rtrim((string)BASE_URL, '/') . '/';
?>

<div class="sd" id="sd">
    <div class="page-header">
        <h1>SEO Dashboard</h1>
        <div class="header-actions sd-actions">
            <span class="sd-pill <?= $gscConnected ? 'sd-pill--ok' : 'sd-pill--bad' ?>">
                Search Console: <?= $gscConnected ? 'Ð¿Ð¾Ð´ÐºÐ»ÑŽÑ‡Ñ‘Ð½' . (!empty($gsc['site_url']) ? ' Â· ' . e($gsc['site_url']) : '') : 'Ð½Ðµ Ð¿Ð¾Ð´ÐºÐ»ÑŽÑ‡Ñ‘Ð½' ?>
            </span>
            <select id="sdDays" class="btn" aria-label="ÐŸÐµÑ€Ð¸Ð¾Ð´">
                <option value="7">7 Ð´Ð½ÐµÐ¹</option>
                <option value="28" selected>28 Ð´Ð½ÐµÐ¹</option>
                <option value="90">90 Ð´Ð½ÐµÐ¹</option>
            </select>
            <button type="button" id="sdRefresh" class="btn btn-secondary">ÐžÐ±Ð½Ð¾Ð²Ð¸Ñ‚ÑŒ</button>
        </div>
    </div>

    <?php if (!$gscConnected): ?>
    <div class="sd-card sd-card--warn">
        Search Console Ð½Ðµ Ð¿Ð¾Ð´ÐºÐ»ÑŽÑ‡Ñ‘Ð½ â€” Ñ€Ð°Ð·Ð´ÐµÐ»Ñ‹ Ñ Ð´Ð°Ð½Ð½Ñ‹Ð¼Ð¸ Google Ð±ÑƒÐ´ÑƒÑ‚ Ð¿ÑƒÑÑ‚Ñ‹Ð¼Ð¸. ÐŸÐ¾Ð´ÐºÐ»ÑŽÑ‡Ð¸Ñ‚Ðµ ÐµÐ³Ð¾ Ð²
        <a href="<?= BASE_URL ?>/admin/ai-studio">AI Studio</a> (ÐºÐ½Ð¾Ð¿ÐºÐ° Connect GSC). ÐÑƒÐ´Ð¸Ñ‚ ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ† Ð½Ð¸Ð¶Ðµ Ñ€Ð°Ð±Ð¾Ñ‚Ð°ÐµÑ‚ Ð±ÐµÐ· Ð¿Ð¾Ð´ÐºÐ»ÑŽÑ‡ÐµÐ½Ð¸Ñ.
    </div>
    <?php endif; ?>

    <p class="sd-hint">Ð”Ð°Ð½Ð½Ñ‹Ðµ Search Console Ð¿Ñ€Ð¸Ñ…Ð¾Ð´ÑÑ‚ Ñ Ð·Ð°Ð´ÐµÑ€Ð¶ÐºÐ¾Ð¹ ~3 Ð´Ð½Ñ Ð¸ ÐºÑÑˆÐ¸Ñ€ÑƒÑŽÑ‚ÑÑ Ð½Ð° Ñ‡Ð°Ñ. Ð­Ñ‚Ð¾ Ñ‚Ðµ Ð¶Ðµ Ð¾Ñ‚Ñ‡Ñ‘Ñ‚Ñ‹, Ñ‡Ñ‚Ð¾ Ð¾Ñ‚Ð´Ð°Ñ‘Ñ‚ SEO API (<code>/api/seo/v1/help</code>).</p>

    <!-- Overview -->
    <section class="sd-card" id="sdOverview">
        <h2>ÐžÐ±Ð·Ð¾Ñ€</h2>
        <div class="sd-body"><div class="sd-loading">Ð—Ð°Ð³Ñ€ÑƒÐ·ÐºÐ°â€¦</div></div>
    </section>

    <!-- Brand -->
    <section class="sd-card" id="sdBrand">
        <h2>Ð‘Ñ€ÐµÐ½Ð´Ð¾Ð²Ñ‹Ð¹ Ð·Ð°Ð¿Ñ€Ð¾Ñ</h2>
        <p class="sd-sub">ÐŸÐ¾ÐºÐ°Ð·Ñ‹Ð²Ð°ÐµÑ‚, Ð·Ð°Ð½Ð¸Ð¼Ð°ÐµÑ‚Ðµ Ð»Ð¸ Ð²Ñ‹ Ð¿Ð¾Ð·Ð¸Ñ†Ð¸ÑŽ Ð¿Ð¾ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸ÑŽ Ð±Ñ€ÐµÐ½Ð´Ð° Ð¸ ÐºÐ°ÐºÐ°Ñ ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†Ð° Ñ€Ð°Ð½Ð¶Ð¸Ñ€ÑƒÐµÑ‚ÑÑ. Ð­Ñ‚Ð¾ Ð³Ð»Ð°Ð²Ð½Ñ‹Ð¹ Ñ„Ð°ÐºÑ‚Ð¾Ñ€ Ð´Ð»Ñ sitelinks.</p>
        <form class="sd-form" id="sdBrandForm">
            <input type="text" id="sdBrandInput" placeholder="Ð½Ð°Ð¿Ñ€Ð¸Ð¼ÐµÑ€: ÐºÑƒÐ¿Ð»ÑŽ Ñ‚Ð°ÑˆÐºÐµÐ½Ñ‚" maxlength="100" autocomplete="off" aria-label="Ð‘Ñ€ÐµÐ½Ð´">
            <button type="submit" class="btn">ÐŸÑ€Ð¾Ð²ÐµÑ€Ð¸Ñ‚ÑŒ</button>
        </form>
        <div class="sd-body"><div class="sd-empty">Ð’Ð²ÐµÐ´Ð¸Ñ‚Ðµ Ð½Ð°Ð·Ð²Ð°Ð½Ð¸Ðµ Ð±Ñ€ÐµÐ½Ð´Ð° (ÐºÐ°Ðº ÐµÐ³Ð¾ Ð½Ð°Ð±Ð¸Ñ€Ð°ÑŽÑ‚ Ð² Google) Ð¸ Ð½Ð°Ð¶Ð¼Ð¸Ñ‚Ðµ Â«ÐŸÑ€Ð¾Ð²ÐµÑ€Ð¸Ñ‚ÑŒÂ».</div></div>
    </section>

    <!-- Opportunities -->
    <section class="sd-card" id="sdOpp">
        <h2>Ð’Ð¾Ð·Ð¼Ð¾Ð¶Ð½Ð¾ÑÑ‚Ð¸ Ñ€Ð¾ÑÑ‚Ð°</h2>
        <div class="sd-tabs" role="tablist" id="sdOppTabs">
            <button type="button" class="sd-tab is-active" data-tab="striking_distance">Ð‘Ð»Ð¸Ð·ÐºÐ¾ Ðº Ñ‚Ð¾Ð¿Ñƒ (Ð¿Ð¾Ð·. 5â€“20)</button>
            <button type="button" class="sd-tab" data-tab="low_ctr">ÐÐ¸Ð·ÐºÐ¸Ð¹ CTR</button>
            <button type="button" class="sd-tab" data-tab="zero_clicks">ÐŸÐ¾ÐºÐ°Ð·Ñ‹ Ð±ÐµÐ· ÐºÐ»Ð¸ÐºÐ¾Ð²</button>
        </div>
        <div class="sd-body"><div class="sd-loading">Ð—Ð°Ð³Ñ€ÑƒÐ·ÐºÐ°â€¦</div></div>
    </section>

    <!-- Movers -->
    <section class="sd-card" id="sdMovers">
        <h2>Ð”Ð¸Ð½Ð°Ð¼Ð¸ÐºÐ° ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ† (Ð¿ÐµÑ€Ð¸Ð¾Ð´ Ðº Ð¿Ñ€ÐµÐ´Ñ‹Ð´ÑƒÑ‰ÐµÐ¼Ñƒ)</h2>
        <div class="sd-body"><div class="sd-loading">Ð—Ð°Ð³Ñ€ÑƒÐ·ÐºÐ°â€¦</div></div>
    </section>

    <!-- Audit -->
    <section class="sd-card" id="sdAudit">
        <div class="sd-head">
            <h2>ÐÑƒÐ´Ð¸Ñ‚ ÑÑ‚Ñ€Ð°Ð½Ð¸Ñ†</h2>
            <select id="sdAuditSeverity" class="btn" aria-label="Ð¡ÐµÑ€ÑŒÑ‘Ð·Ð½Ð¾ÑÑ‚ÑŒ">
                <option value="warn" selected>ÐžÑˆÐ¸Ð±ÐºÐ¸ Ð¸ Ð¿Ñ€ÐµÐ´ÑƒÐ¿Ñ€ÐµÐ¶Ð´ÐµÐ½Ð¸Ñ</option>
                <option value="error">Ð¢Ð¾Ð»ÑŒÐºÐ¾ Ð¾ÑˆÐ¸Ð±ÐºÐ¸</option>
                <option value="info">Ð’ÑÑ‘, Ð²ÐºÐ»ÑŽÑ‡Ð°Ñ Ð·Ð°Ð¼ÐµÑ‡Ð°Ð½Ð¸Ñ</option>
            </select>
        </div>
        <div class="sd-body"><div class="sd-loading">Ð—Ð°Ð³Ñ€ÑƒÐ·ÐºÐ°â€¦</div></div>
    </section>

    <!-- Indexing -->
    <section class="sd-card" id="sdIndex">
        <h2>Ð˜Ð½Ð´ÐµÐºÑÐ°Ñ†Ð¸Ñ</h2>
        <h3>ÐŸÑ€Ð¾Ð²ÐµÑ€Ð¸Ñ‚ÑŒ URL</h3>
        <form class="sd-form" id="sdInspectForm">
            <input type="url" id="sdInspectInput" value="<?= e($siteRoot) ?>" maxlength="500" aria-label="URL">
            <button type="submit" class="btn">ÐŸÑ€Ð¾Ð²ÐµÑ€Ð¸Ñ‚ÑŒ</button>
        </form>
        <div class="sd-body" id="sdInspectResult"></div>
        <h3>Sitemap</h3>
        <div class="sd-body" id="sdSitemaps"><div class="sd-loading">Ð—Ð°Ð³Ñ€ÑƒÐ·ÐºÐ°â€¦</div></div>
    </section>
</div>

<script src="<?= BASE_URL ?>/js/admin/seo-dashboard.js?v=<?= assetVersion('/js/admin/seo-dashboard.js') ?>" defer></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
