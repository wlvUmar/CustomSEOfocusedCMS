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
                Search Console: <?= $gscConnected ? 'подключён' . (!empty($gsc['site_url']) ? ' · ' . e($gsc['site_url']) : '') : 'не подключён' ?>
            </span>
            <select id="sdDays" class="btn" aria-label="Период">
                <option value="7">7 дней</option>
                <option value="28" selected>28 дней</option>
                <option value="90">90 дней</option>
            </select>
            <button type="button" id="sdRefresh" class="btn btn-secondary">Обновить</button>
        </div>
    </div>

    <?php if (!$gscConnected): ?>
    <div class="sd-card sd-card--warn">
        Search Console не подключён — разделы с данными Google будут пустыми. Подключите его в
        <a href="<?= BASE_URL ?>/admin/ai-studio">AI Studio</a> (кнопка Connect GSC). Аудит страниц ниже работает без подключения.
    </div>
    <?php endif; ?>

    <p class="sd-hint">Данные Search Console приходят с задержкой ~3 дня и кэшируются на час. Это те же отчёты, что отдаёт SEO API (<code>/api/seo/v1/help</code>).</p>

    <!-- Overview -->
    <section class="sd-card" id="sdOverview">
        <h2>Обзор</h2>
        <div class="sd-body"><div class="sd-loading">Загрузка…</div></div>
    </section>

    <!-- Brand -->
    <section class="sd-card" id="sdBrand">
        <h2>Брендовый запрос</h2>
        <p class="sd-sub">Показывает, занимаете ли вы позицию по названию бренда и какая страница ранжируется. Это главный фактор для sitelinks.</p>
        <form class="sd-form" id="sdBrandForm">
            <input type="text" id="sdBrandInput" placeholder="например: куплю ташкент" maxlength="100" autocomplete="off" aria-label="Бренд">
            <button type="submit" class="btn">Проверить</button>
        </form>
        <div class="sd-body"><div class="sd-empty">Введите название бренда (как его набирают в Google) и нажмите «Проверить».</div></div>
    </section>

    <!-- Opportunities -->
    <section class="sd-card" id="sdOpp">
        <h2>Возможности роста</h2>
        <div class="sd-tabs" role="tablist" id="sdOppTabs">
            <button type="button" class="sd-tab is-active" data-tab="striking_distance">Близко к топу (поз. 5–20)</button>
            <button type="button" class="sd-tab" data-tab="low_ctr">Низкий CTR</button>
            <button type="button" class="sd-tab" data-tab="zero_clicks">Показы без кликов</button>
        </div>
        <div class="sd-body"><div class="sd-loading">Загрузка…</div></div>
    </section>

    <!-- Movers -->
    <section class="sd-card" id="sdMovers">
        <h2>Динамика страниц (период к предыдущему)</h2>
        <div class="sd-body"><div class="sd-loading">Загрузка…</div></div>
    </section>

    <!-- Audit -->
    <section class="sd-card" id="sdAudit">
        <div class="sd-head">
            <h2>Аудит страниц</h2>
            <select id="sdAuditSeverity" class="btn" aria-label="Серьёзность">
                <option value="warn" selected>Ошибки и предупреждения</option>
                <option value="error">Только ошибки</option>
                <option value="info">Всё, включая замечания</option>
            </select>
        </div>
        <div class="sd-body"><div class="sd-loading">Загрузка…</div></div>
    </section>

    <!-- Indexing -->
    <section class="sd-card" id="sdIndex">
        <h2>Индексация</h2>
        <h3>Проверить URL</h3>
        <form class="sd-form" id="sdInspectForm">
            <input type="url" id="sdInspectInput" value="<?= e($siteRoot) ?>" maxlength="500" aria-label="URL">
            <button type="submit" class="btn">Проверить</button>
        </form>
        <div class="sd-body" id="sdInspectResult"></div>
        <h3>Sitemap</h3>
        <div class="sd-body" id="sdSitemaps"><div class="sd-loading">Загрузка…</div></div>
    </section>
</div>

<script src="<?= BASE_URL ?>/js/admin/seo-dashboard.js?v=<?= assetVersion('/js/admin/seo-dashboard.js') ?>" defer></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
