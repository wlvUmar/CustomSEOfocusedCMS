<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1><i data-feather="activity"></i> Search Engine Crawl Analysis</h1>
    <div class="header-actions">
        <select onchange="window.location='?days='+this.value" class="btn">
            <option value="7" <?= $days == 7 ? 'selected' : '' ?>>Last 7 Days</option>
            <option value="14" <?= $days == 14 ? 'selected' : '' ?>>Last 14 Days</option>
            <option value="30" <?= $days == 30 ? 'selected' : '' ?>>Last 30 Days</option>
            <option value="90" <?= $days == 90 ? 'selected' : '' ?>>Last 90 Days</option>
        </select>
    </div>
</div>

<div class="info-banner">
    <strong><i data-feather="info"></i> About This Report:</strong> This shows search engine bot/crawler visits only. 
    Regular user visits are excluded. Higher bot frequency signals that search engines consider your content important.
</div>

<?php if (empty($crawl_frequency)): ?>
    <div class="empty-state">
        <h2>No Crawl Data Available</h2>
        <p>Analytics tracking will show data once search engine bots start accessing your pages.</p>
    </div>
<?php else: ?>

<!-- Charts Row -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
    <!-- Bot Activity Over Time -->
    <div class="chart-box" style="margin: 0;">
        <h2><i data-feather="trending-up"></i> Bot Activity (Daily Visits)</h2>
        <canvas id="botActivityChart" height="250"></canvas>
    </div>

    <!-- Bot Distribution -->
    <div class="chart-box" style="margin: 0;">
        <h2><i data-feather="pie-chart"></i> Bot Distribution</h2>
        <canvas id="botDistributionChart" height="250"></canvas>
    </div>
</div>

<!-- Top Crawled Pages -->
<div class="chart-box">
    <h2><i data-feather="bar-chart-2"></i> Top 10 Crawled Pages</h2>
    <canvas id="topPagesChart" height="100"></canvas>
</div>

<!-- Summary Stats Cards -->
<div class="crawl-summary-cards">
    <?php
    $dailyPages = 0;
    $weeklyPages = 0;
    $monthlyPages = 0;
    $rarePages = 0;
    
    foreach ($crawl_frequency as $page) {
        $daysSince = (strtotime('today') - strtotime($page['last_visit'])) / 86400;
        if ($daysSince <= 1) $dailyPages++;
        elseif ($daysSince <= 7) $weeklyPages++;
        elseif ($daysSince <= 30) $monthlyPages++;
        else $rarePages++;
    }
    ?>
    
    <div class="summary-card daily">
        <div class="card-icon"><i data-feather="zap"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $dailyPages ?></div>
            <div class="card-label">Daily Crawl</div>
            <div class="card-desc">Visited < 24h ago</div>
        </div>
    </div>
    
    <div class="summary-card weekly">
        <div class="card-icon"><i data-feather="calendar"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $weeklyPages ?></div>
            <div class="card-label">Weekly Crawl</div>
            <div class="card-desc">Visited < 7 days ago</div>
        </div>
    </div>
    
    <div class="summary-card monthly">
        <div class="card-icon"><i data-feather="clock"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $monthlyPages ?></div>
            <div class="card-label">Monthly Crawl</div>
            <div class="card-desc">Visited < 30 days ago</div>
        </div>
    </div>
    
    <div class="summary-card rare">
        <div class="card-icon"><i data-feather="alert-triangle"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $rarePages ?></div>
            <div class="card-label">Rare/Stale</div>
            <div class="card-desc">Needs attention</div>
        </div>
    </div>
</div>

<!-- Detailed Data (Collapsible) -->
<div class="chart-box">
    <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="toggleDetails()">
        <h2><i data-feather="list"></i> Detailed Crawl Log (<?= count($crawl_frequency) ?> Pages)</h2>
        <i data-feather="chevron-down" id="details-arrow"></i>
    </div>
    
    <div id="detailed-data" style="display: none; margin-top: 20px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Bot Type</th>
                    <th>Visits</th>
                    <th>Last Visit</th>
                    <th>Frequency</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($crawl_frequency as $page): 
                    $daysSince = round((strtotime('today') - strtotime($page['last_visit'])) / 86400);
                    $status = $daysSince <= 1 ? 'Daily' : ($daysSince <= 7 ? 'Weekly' : ($daysSince <= 30 ? 'Monthly' : 'Rare'));
                    $statusColor = $daysSince <= 1 ? '#10b981' : ($daysSince <= 7 ? '#3b82f6' : ($daysSince <= 30 ? '#f59e0b' : '#ef4444'));
                ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/<?= e($page['page_slug']) ?>" target="_blank" style="font-weight: 500;">
                            /<?= e($page['page_slug']) ?>
                        </a>
                    </td>
                    <td><span class="badge badge-<?= strtolower($page['bot_type']) ?>"><?= ucfirst(e($page['bot_type'])) ?></span></td>
                    <td><?= number_format($page['total_visits']) ?></td>
                    <td><?= date('M d', strtotime($page['last_visit'])) ?> <span style="font-size: 11px; color: #6b7280;">(<?= $daysSince ?>d ago)</span></td>
                    <td><span style="color: <?= $statusColor ?>; font-weight: 600;"><?= $status ?></span></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/analytics/page/<?= e($page['page_slug']) ?>" class="btn btn-sm">Stats</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
function toggleDetails() {
    const el = document.getElementById('detailed-data');
    const arrow = document.getElementById('details-arrow');
    if (el.style.display === 'none') {
        el.style.display = 'block';
        arrow.setAttribute('data-feather', 'chevron-up');
    } else {
        el.style.display = 'none';
        arrow.setAttribute('data-feather', 'chevron-down');
    }
    feather.replace();
}

document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
    
    // --- Bot Activity Chart ---
    const activityCtx = document.getElementById('botActivityChart');
    if (activityCtx) {
        // Process daily stats from PHP
        const dailyStats = <?= json_encode($daily_stats ?? []) ?>;
        
        // Extract unique dates and bots
        const dates = [...new Set(dailyStats.map(item => item.visit_date))];
        const bots = [...new Set(dailyStats.map(item => item.bot_type))];
        
        const datasets = bots.map((bot, index) => {
            const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
            const hex = colors[index % colors.length];
            
            // Simple hex to rgba conversion
            let c;
            if(/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)){
                c= hex.substring(1).split('');
                if(c.length== 3){
                    c= [c[0], c[0], c[1], c[1], c[2], c[2]];
                }
                c= '0x'+c.join('');
            }
            
            const bg = c ? 'rgba('+[(c>>16)&255, (c>>8)&255, c&255].join(',')+',0.1)' : 'rgba(59, 130, 246, 0.1)';

            return {
                label: bot.charAt(0).toUpperCase() + bot.slice(1),
                data: dates.map(date => {
                    const entry = dailyStats.find(d => d.visit_date === date && d.bot_type === bot);
                    return entry ? entry.visits : 0;
                }),
                borderColor: hex,
                backgroundColor: bg,
                borderWidth: 2,
                tension: 0.3,
                fill: true
            };
        });

        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: dates.map(d => new Date(d).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
                datasets: datasets
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // --- Bot Distribution Chart ---
    const distCtx = document.getElementById('botDistributionChart');
    if (distCtx) {
        const botSummary = <?= json_encode($bot_summary ?? []) ?>;
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: botSummary.map(b => b.bot_type.charAt(0).toUpperCase() + b.bot_type.slice(1)),
                datasets: [{
                    data: botSummary.map(b => b.total_visits),
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // --- Top Pages Chart ---
    const topCtx = document.getElementById('topPagesChart');
    if (topCtx) {
        const topPages = <?= json_encode(array_slice($crawl_frequency ?? [], 0, 10)) ?>;
        new Chart(topCtx, {
            type: 'bar',
            data: {
                labels: topPages.map(p => '/' + p.page_slug),
                datasets: [{
                    label: 'Bot Visits',
                    data: topPages.map(p => p.total_visits),
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});

// Helper for hex to rgba if needed
function hexToRgb(hex) {
    // This is just a placeholder, the chart config uses generic colors or the function can be improved
    return '0,0,0,0.1'; 
}
</script>

<?php endif; ?>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
