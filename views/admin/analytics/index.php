<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header c-page-end">
    <div class="header-actions">
    <select id="periodSelect" class="btn">
        <optgroup label="Ежедневные">
            <option value="range:today" <?= ($stats['range'] ?? '') === 'today' ? 'selected' : '' ?>>Сегодня</option>
            <option value="range:yesterday" <?= ($stats['range'] ?? '') === 'yesterday' ? 'selected' : '' ?>>Вчера</option>
            <option value="range:day_before" <?= ($stats['range'] ?? '') === 'day_before' ? 'selected' : '' ?>>Позавчера</option>
        </optgroup>
        <optgroup label="Еженедельные">
            <option value="range:last_week" <?= ($stats['range'] ?? '') === 'last_week' ? 'selected' : '' ?>>Прошлая неделя (Пн–Вс)</option>
        </optgroup>
        <optgroup label="Ежемесячные">
            <option value="months:1" <?= empty($stats['range']) && $stats['months'] == 1 ? 'selected' : '' ?>>Последний месяц</option>
            <option value="months:3" <?= empty($stats['range']) && $stats['months'] == 3 ? 'selected' : '' ?>>Последние 3 месяца</option>
            <option value="months:6" <?= empty($stats['range']) && $stats['months'] == 6 ? 'selected' : '' ?>>Последние 6 месяцев</option>
            <option value="months:12" <?= empty($stats['range']) && $stats['months'] == 12 ? 'selected' : '' ?>>Последние 12 месяцев</option>
        </optgroup>
    </select>

    <a href="<?= BASE_URL ?>/admin/analytics/export?months=<?= $stats['months'] ?><?= !empty($stats['range']) ? '&range=' . urlencode($stats['range']) : '' ?>"
       class="btn btn-secondary" title="Экспорт CSV" aria-label="Экспорт CSV">
        <i data-feather="download"></i>
    </a>
</div>
</div>

<?php if (!empty($stats['range_label'])): ?>
<div class="c-period">
    Период: <strong><?= e($stats['range_label']) ?></strong>
</div>
<?php endif; ?>


<!-- Performance Scorecards (GSC Style) -->
<?php
$analyticsBoot = [
    'labels' => array_keys($stats['visits_chart'] ?? []),
    'visits' => array_values($stats['visits_chart'] ?? []),
    'clicks' => array_values($stats['clicks_chart'] ?? []),
    'phones' => array_values($stats['phone_calls_chart'] ?? []),
];
?>
<script>window.performanceChartData = <?= json_encode($analyticsBoot) ?>;</script>
<script src="<?= BASE_URL ?>/js/admin/analytics.js?v=10"></script>

<style>
    :root { --teal: #0e9f8a; }
    .c-page-end { justify-content: flex-end; }
    .c-period { margin: 0 0 16px; color: #64748b; font-size: 13px; }
    .c-ficon { width: 14px; height: 14px; }
    .c-ficon-sm { width: 12px; height: 12px; }
    .c-chart-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .c-chart-title { margin: 0; }
    .c-filters { display: flex; gap: 8px; align-items: center; }
    .c-select { padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; background: #fff; color: #1e293b; }
    .c-sec-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .c-sub { color: #64748b; font-size: 13px; }
    .c-sub-top { margin-top: -8px; }
    .c-sub-mb { margin-bottom: 16px; }
    .c-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    .c-table-wide { min-width: 500px; }
    .c-thead { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .c-th { padding: 12px; font-size: 13px; color: #64748b; font-weight: 600; }
    .c-th-l { text-align: left; }
    .c-th-c { text-align: center; }
    .c-sort { cursor: pointer; user-select: none; }
    .c-row { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .c-td { padding: 16px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; }
    .c-td-l { border-left: 1px solid #f1f5f9; border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
    .c-td-r { border-right: 1px solid #f1f5f9; border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
    .c-rank { font-weight: 600; color: #94a3b8; font-size: 14px; }
    .c-td-name { font-size: 14px; color: #1e293b; font-weight: 500; }
    .c-slug { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .c-num { text-align: right; margin-bottom: 4px; font-size: 14px; font-weight: 600; }
    .c-blue { color: #3b82f6; }
    .c-grn-t { color: #10b981; }
    .c-amb-t { color: #f59e0b; }
    .c-sky-t { color: #0284c7; }
    .c-bar { height: 6px; border-radius: 3px; overflow: hidden; }
    .c-bar-blue { background: #e0e7ff; }
    .c-bar-grn { background: #d1fae5; }
    .c-bar-amb { background: #fef3c7; }
    .c-bar-sky { background: #cffafe; }
    .c-fill { display: block; height: 100%; border-radius: 3px; }
    .c-fill-blue { background: #3b82f6; }
    .c-fill-grn { background: var(--teal); }
    .c-fill-amb { background: #f59e0b; }
    .c-fill-sky { background: #0284c7; }
    .c-td-c { text-align: center; }
    .c-pill { display: inline-block; padding: 4px 12px; font-size: 13px; font-weight: 600; }
    .c-pill-r12 { border-radius: 12px; }
    .c-pill-r6 { border-radius: 6px; }
    .c-grn { background: #d1fae5; color: #059669; }
    .c-amb { background: #fef3c7; color: #d97706; }
    .c-red { background: #fee2e2; color: #dc2626; }
    .c-pur { background: #f3e8ff; color: #7c3aed; }
    .c-empty { padding: 40px; text-align: center; color: #94a3b8; }
    .c-hidden { display: none !important; }
    .c-insights { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
    .c-mini { display: flex; align-items: center; padding: 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; text-decoration: none; transition: all 0.2s; }
    .c-mini-ic { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 10px; margin-right: 12px; }
    .c-bg-amb { background: #fff7ed; color: #f59e0b; }
    .c-bg-blue { background: #e0e7ff; color: #3b82f6; }
    .c-bg-grn { background: #d1fae5; color: var(--teal); }
    .c-mini-eyebrow { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; }
    .c-mini-val { font-size: 15px; font-weight: 700; color: #1e293b; }
    .c-debug { background: #1e293b; color: #e2e8f0; padding: 20px; border-radius: 8px; margin-top: 30px; font-family: monospace; font-size: 12px; border: 2px solid #f59e0b; }
    .c-debug-t { margin-bottom: 10px; color: #f59e0b; font-weight: bold; }
    .c-debug-r { margin-bottom: 10px; }
    .c-debug-f { margin-top: 15px; padding-top: 15px; border-top: 1px solid #475569; }
    .c-debug-ok { color: #10b981; }
    .performance-scorecards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .performance-scorecard {
        padding: 20px;
        cursor: pointer;
        transition: all 0.2s;
        border-right: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 8px;
        position: relative;
    }
    .performance-scorecard:last-child { border-right: none; }
    .performance-scorecard:hover { background: #f8fafc; }
    .performance-scorecard.active.visits { border-top: 4px solid #3b82f6; padding-top: 16px; }
    .performance-scorecard.active.clicks { border-top: 4px solid var(--teal); padding-top: 16px; }
    .performance-scorecard.active.phones { border-top: 4px solid #f59e0b; padding-top: 16px; }
    .performance-scorecard.active.ctr { border-top: 4px solid #8b5cf6; padding-top: 16px; }
    .performance-scorecard:not(.active) { opacity: 0.6; }
    .sc-label { color: #64748b; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 6px; }
    .sc-value { font-size: 24px; font-weight: 700; color: #1e293b; }
    .sc-change { font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 4px; }
    .sc-change.positive { color: #10b981; }
    .sc-change.negative { color: #ef4444; }
</style>

<div class="performance-scorecards">
    <?php
    $totalVisits = $stats['total']['total_visits'] ?? 0;
    $totalClicks = $stats['total']['total_clicks'] ?? 0;
    $totalPhones = $stats['total']['total_phone_calls'] ?? 0;
    $overallCtr = $totalVisits > 0 ? round(($totalPhones / $totalVisits) * 100, 2) : 0;

    $scorecards = [
        ['label' => 'Всего визитов', 'icon' => 'eye', 'value' => $totalVisits, 'class' => 'visits', 'metric' => 'visits'],
        ['label' => 'Всего кликов', 'icon' => 'mouse-pointer', 'value' => $totalClicks, 'class' => 'clicks', 'metric' => 'clicks'],
        ['label' => 'Звонков', 'icon' => 'phone', 'value' => $totalPhones, 'class' => 'phones', 'metric' => 'phone_calls'],
        ['label' => 'CTR звонков', 'icon' => 'percent', 'value' => $overallCtr . '%', 'class' => 'ctr', 'metric' => 'ctr']
    ];

    foreach ($scorecards as $sc):
        $change = $stats['trends']['changes'][$sc['metric']] ?? 0;
    ?>
    <div class="performance-scorecard active <?= $sc['class'] ?>" data-metric="<?= $sc['metric'] ?>">
        <div class="sc-label">
            <i data-feather="<?= $sc['icon'] ?>" class="c-ficon"></i>
            <?= $sc['label'] ?>
        </div>
        <div class="sc-value"><?= is_numeric($sc['value']) ? number_format($sc['value']) : $sc['value'] ?></div>
        <?php if (isset($stats['trends']['changes'][$sc['metric']])): ?>
        <div class="sc-change <?= $change >= 0 ? 'positive' : 'negative' ?>">
            <i data-feather="<?= $change >= 0 ? 'trending-up' : 'trending-down' ?>" class="c-ficon-sm"></i>
            <?= abs($change) ?>%
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Performance Line Graph (GSC-style) -->
<div class="chart-box">
    <div class="c-chart-head">
        <h2 class="c-chart-title"><i data-feather="activity"></i> Общая статистика</h2>

        <?php
        $currentSlug = $stats['slug'] ?? '';
        $currentUtm = $stats['utm_source'] ?? '';
        $allSlugs = $stats['all_page_slugs'] ?? [];
        $allUtmSources = $stats['all_utm_sources'] ?? [];
        ?>
        <div class="c-filters">
            <select id="pageSlugFilter" class="c-select">
                <option value="">Все страницы</option>
                <?php foreach ($allSlugs as $s): ?>
                <option value="<?= e($s) ?>" <?= $currentSlug === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="utmSourceFilter" class="c-select">
                <option value="">Все UTM-источники</option>
                <option value="direct" <?= $currentUtm === 'direct' ? 'selected' : '' ?>>Прямой</option>
                <?php foreach ($allUtmSources as $source): ?>
                    <?php if ($source !== 'direct' && !empty($source)): ?>
                    <option value="<?= e($source) ?>" <?= $currentUtm === $source ? 'selected' : '' ?>><?= e($source) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <select id="aggregationSelect" class="c-select">
                <option value="daily" <?= ($stats['aggregation'] ?? 'monthly') === 'daily' ? 'selected' : '' ?>>По дням</option>
                <option value="weekly" <?= ($stats['aggregation'] ?? 'monthly') === 'weekly' ? 'selected' : '' ?>>По неделям</option>
                <option value="monthly" <?= ($stats['aggregation'] ?? 'monthly') === 'monthly' ? 'selected' : '' ?>>По месяцам</option>
            </select>
        </div>
    </div>
    <canvas id="performanceChart" height="320"></canvas>
</div>

<!-- Top Pages Performance -->
<div class="chart-box">
    <div class="c-sec-head">
        <div>
            <h2><i data-feather="trending-up"></i> Популярные страницы</h2>
            <?php if (!empty($stats['range_label'])): ?>
            <div class="c-sub c-sub-top">
                Период: <?= e($stats['range_label']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $topPerformers = $stats['top_performers'] ?? [];
    $maxVisits = !empty($topPerformers) ? max(array_column($topPerformers, 'visits')) : 1;
    $maxClicks = !empty($topPerformers) ? max(array_column($topPerformers, 'clicks')) : 1;
    $maxPhones = !empty($topPerformers) ? max(array_column($topPerformers, 'phone_calls')) : 1;
    ?>

    <div class="table-scroll">
    <table id="top-performers-table" class="c-table">
        <thead class="c-thead">
            <tr>
                <th class="c-th c-th-l">#</th>
                <th data-sort-key="page_slug" class="c-th c-th-l c-sort"><span class="th-label">Страница</span><span class="sort-indicator">↕</span></th>
                <th data-sort-key="visits" class="c-th c-th-c c-sort"><span class="th-label">Визиты</span><i data-feather="eye" class="th-icon"></i><span class="sort-indicator">↕</span></th>
                <th data-sort-key="clicks" class="c-th c-th-c c-sort"><span class="th-label">Клики</span><i data-feather="mouse-pointer" class="th-icon"></i><span class="sort-indicator">↕</span></th>
                <th data-sort-key="phone_calls" class="c-th c-th-c c-sort"><span class="th-label">Звонки</span><i data-feather="phone" class="th-icon"></i><span class="sort-indicator">↕</span></th>
                <th data-sort-key="ctr" class="c-th c-th-c c-sort">CTR <span class="sort-indicator">↕</span></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($topPerformers)): ?>
            <?php foreach ($topPerformers as $index => $page):
                $visits = (int)($page['visits'] ?? 0);
                $clicks = (int)($page['clicks'] ?? 0);
                $phoneCalls = (int)($page['phone_calls'] ?? 0);
                $utmSource = !empty($page['utm_source']) ? $page['utm_source'] : 'direct';
                $ctr = isset($page['ctr'])
                    ? (float)$page['ctr']
                    : ($visits > 0 ? round(($phoneCalls / $visits) * 100, 2) : 0);
                $visitsWidth = $maxVisits > 0 ? ($visits / $maxVisits) * 100 : 0;
                $clicksWidth = $maxClicks > 0 ? ($clicks / $maxClicks) * 100 : 0;
                $phonesWidth = $maxPhones > 0 ? ($phoneCalls / $maxPhones) * 100 : 0;
                $displaySlug = mb_strlen($page['page_slug'] ?? '') > 12 ? mb_substr($page['page_slug'], 0, 12) . '...' : ($page['page_slug'] ?? '');
                $ctrCls = $ctr >= 5 ? 'c-grn' : ($ctr >= 2 ? 'c-amb' : 'c-red');
            ?>
            <tr
                class="c-row"
                data-page-slug="<?= e((string)($page['page_slug'] ?? '')) ?>"
                data-utm-source="<?= e($utmSource) ?>"
                data-visits="<?= $visits ?>"
                data-clicks="<?= $clicks ?>"
                data-phone-calls="<?= $phoneCalls ?>"
                data-ctr="<?= $ctr ?>"
            >
                <td data-rank-cell class="c-td c-td-l c-rank"><?= $index + 1 ?></td>
                <td class="c-td c-td-name">
                    <div class="c-slug" title="<?= htmlspecialchars($page['page_slug']) ?>"><?= htmlspecialchars($displaySlug) ?></div>
                </td>
                <td data-label="Visits" class="c-td">
                    <div class="c-num c-blue"><?= number_format($visits) ?></div>
                    <div class="c-bar c-bar-blue"><i class="c-fill c-fill-blue" data-w="<?= $visitsWidth ?>"></i></div>
                </td>
                <td data-label="Clicks" class="c-td">
                    <div class="c-num c-grn-t"><?= number_format($clicks) ?></div>
                    <div class="c-bar c-bar-grn"><i class="c-fill c-fill-grn" data-w="<?= $clicksWidth ?>"></i></div>
                </td>
                <td data-label="Phone Calls" class="c-td">
                    <div class="c-num c-amb-t"><?= number_format($phoneCalls) ?></div>
                    <div class="c-bar c-bar-amb"><i class="c-fill c-fill-amb" data-w="<?= $phonesWidth ?>"></i></div>
                </td>
                <td data-label="CTR" class="c-td c-td-r c-td-c">
                    <span class="c-pill c-pill-r12 <?= $ctrCls ?>"><?= $ctr ?>%</span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr><td colspan="6" class="c-empty">Данных пока нет</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- UTM Source Performance -->
<div class="chart-box<?= empty($stats['utm_stats']) ? ' c-hidden' : '' ?>" id="utmStatsContainer">
    <h2><i data-feather="link"></i> Трафик по UTM-источникам</h2>
    <?php if (!empty($stats['range_label'])): ?>
    <div class="c-sub c-sub-top c-sub-mb">
        Период: <?= e($stats['range_label']) ?>
    </div>
    <?php endif; ?>

    <div class="table-scroll">
    <table class="c-table c-table-wide">
        <thead class="c-thead">
            <tr>
                <th class="c-th c-th-l">UTM-источник</th>
                <th class="c-th c-th-c">Клики</th>
                <th class="c-th c-th-c">Звонки</th>
                <th class="c-th c-th-c">Всего действий</th>
                <th class="c-th c-th-c">Затронуто страниц</th>
            </tr>
        </thead>
        <tbody id="utmStatsBody">
            <?php
            $totalClicks = array_sum(array_column($stats['utm_stats'], 'clicks'));
            $totalCalls = array_sum(array_column($stats['utm_stats'], 'phone_calls'));
            $maxActions = max(array_map(function($s) { return $s['clicks'] + $s['phone_calls']; }, $stats['utm_stats'])) ?: 1;

            foreach ($stats['utm_stats'] as $source):
                $clicks = (int)($source['clicks'] ?? 0);
                $calls = (int)($source['phone_calls'] ?? 0);
                $total = $clicks + $calls;
                $pages = (int)($source['pages_affected'] ?? 0);
                $width = ($total / $maxActions) * 100;
            ?>
            <tr class="c-row">
                <td class="c-td c-td-l c-td-name">
                    <?= e(!empty($source['utm_source']) ? $source['utm_source'] : 'direct') ?>
                </td>
                <td class="c-td c-td-c">
                    <span class="c-pill c-pill-r6 c-grn">
                        <?= number_format($clicks) ?>
                    </span>
                </td>
                <td class="c-td c-td-c">
                    <span class="c-pill c-pill-r6 c-amb">
                        <?= number_format($calls) ?>
                    </span>
                </td>
                <td class="c-td">
                    <div class="c-num c-sky-t">
                        <?= number_format($total) ?>
                    </div>
                    <div class="c-bar c-bar-sky">
                        <i class="c-fill c-fill-sky" data-w="<?= $width ?>"></i>
                    </div>
                </td>
                <td class="c-td c-td-r c-td-c">
                    <span class="c-pill c-pill-r6 c-pur">
                        <?= number_format($pages) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- CTA Performance (per placement) -->
<?php
$ctaStats = $stats['cta_stats'] ?? ['rows' => [], 'since' => null];
$ctaSince = $ctaStats['since'] ?? null;
?>
<div class="chart-box" id="ctaStatsContainer">
    <h2><i data-feather="target"></i> Эффективность CTA по местам размещения</h2>
    <div class="c-sub c-sub-top c-sub-mb" id="ctaStatsNote">
        <?php if ($ctaSince): ?>
            Данные по местам собираются с <?= e(date('d.m.Y', strtotime($ctaSince))) ?>. Считаются уникальные посетители за день; показ засчитывается, когда видна половина кнопки.
        <?php else: ?>
            Данных пока нет — сбор начнётся после выкладки обновления.
        <?php endif; ?>
        Общие «Клики» и «Звонки» выше считаются как раньше.
    </div>

    <div class="table-scroll">
    <table class="c-table c-table-wide">
        <thead class="c-thead">
            <tr>
                <th class="c-th c-th-l">Кнопка</th>
                <th class="c-th c-th-l">Место</th>
                <th class="c-th c-th-c">Показы</th>
                <th class="c-th c-th-c">Клики</th>
                <th class="c-th c-th-c">CTR</th>
            </tr>
        </thead>
        <tbody id="ctaStatsBody">
            <?php foreach (($ctaStats['rows'] ?? []) as $row): ?>
            <tr class="c-row">
                <td class="c-td c-td-l c-td-name"><?= e($row['label']) ?></td>
                <td class="c-td"><?= e($row['group']) ?></td>
                <td class="c-td c-td-c"><span class="c-pill c-pill-r6 c-pur"><?= number_format($row['views']) ?></span></td>
                <td class="c-td c-td-c"><span class="c-pill c-pill-r6 c-grn"><?= number_format($row['clicks']) ?></span></td>
                <td class="c-td c-td-r c-td-c"><?= $row['ctr'] === null ? '—' : e($row['ctr']) . '%' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>


<!-- Insight Mini-Cards -->
<div class="c-insights">
    <?php
    $rotationImpact = $this->getAnalyticsModel()->getRotationImpact($stats['months']);
    $navStats = $this->getAnalyticsModel()->getNavigationStats($stats['months']);
    $crawlStats = $this->getAnalyticsModel()->getCrawlInsights(7);
    ?>
    <a href="<?= BASE_URL ?>/admin/analytics/rotation" class="mini-insight-card c-mini">
        <div class="c-mini-ic c-bg-amb">
            <i data-feather="repeat"></i>
        </div>
        <div>
            <div class="c-mini-eyebrow">Ротация</div>
            <div class="c-mini-val"><?= $rotationImpact['avg_engagement'] ?>% средний CTR</div>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/admin/analytics/navigation" class="mini-insight-card c-mini">
        <div class="c-mini-ic c-bg-blue">
            <i data-feather="git-branch"></i>
        </div>
        <div>
            <div class="c-mini-eyebrow">Навигация</div>
            <div class="c-mini-val"><?= number_format($navStats['total_clicks'] ?? 0) ?> переходов</div>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/admin/analytics/crawl" class="mini-insight-card c-mini">
        <div class="c-mini-ic c-bg-grn">
            <i data-feather="activity"></i>
        </div>
        <div>
            <div class="c-mini-eyebrow">Обход</div>
            <div class="c-mini-val"><?= $crawlStats['pages_crawled'] ?? 0 ?> стр. (7д)</div>
        </div>
    </a>
</div>


<?php if (!IS_PRODUCTION): ?>
<!-- DEBUG PANEL -->
<div class="c-debug">
    <div class="c-debug-t">🔧 DEBUG PANEL</div>

    <div class="c-debug-r">
        <strong>Chart.js Loaded:</strong> <span id="debug-chartjs">checking...</span>
    </div>

    <div class="c-debug-r">
        <strong>Visits Data:</strong> <span id="debug-visits">checking...</span>
    </div>

    <div class="c-debug-r">
        <strong>Clicks Data:</strong> <span id="debug-clicks">checking...</span>
    </div>

    <div class="c-debug-r">
        <strong>Canvas Elements:</strong> <span id="debug-canvas">checking...</span>
    </div>

    <div class="c-debug-f">
        <strong class="c-debug-ok">✓ Check browser Console (F12) for detailed logs</strong>
    </div>
</div>
<?php endif; ?>


<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
