<?php 
// path: ./views/admin/analytics/index.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>Analytics Dashboard</h1>
    <div>
        <select onchange="window.location='?months='+this.value" class="btn">
            <option value="3" <?= $stats['months'] == 3 ? 'selected' : '' ?>>Last 3 Months</option>
            <option value="6" <?= $stats['months'] == 6 ? 'selected' : '' ?>>Last 6 Months</option>
            <option value="12" <?= $stats['months'] == 12 ? 'selected' : '' ?>>Last 12 Months</option>
        </select>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Visits</h3>
        <p class="stat-number"><?= number_format($stats['total']['total_visits'] ?? 0) ?></p>
    </div>
    
    <div class="stat-card">
        <h3>Total Clicks</h3>
        <p class="stat-number"><?= number_format($stats['total']['total_clicks'] ?? 0) ?></p>
    </div>
    
    <div class="stat-card">
        <h3>This Month Visits</h3>
        <p class="stat-number"><?= number_format($stats['current_month']['visits'] ?? 0) ?></p>
    </div>
    
    <div class="stat-card">
        <h3>This Month Clicks</h3>
        <p class="stat-number"><?= number_format($stats['current_month']['clicks'] ?? 0) ?></p>
    </div>
</div>

<div class="chart-container">
    <div class="chart-box">
        <h2>Monthly Visits</h2>
        <canvas id="visitsChart"></canvas>
    </div>
    
    <div class="chart-box">
        <h2>Monthly Clicks</h2>
        <canvas id="clicksChart"></canvas>
    </div>
</div>

<div style="margin-top: 40px;">
    <h2>Page Statistics</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Page</th>
                <th>Language</th>
                <th>Visits</th>
                <th>Clicks</th>
                <th>CTR</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['page_stats'] as $page): ?>
            <tr>
                <td><?= e($page['page_slug']) ?></td>
                <td><span class="badge"><?= strtoupper($page['language']) ?></span></td>
                <td><?= number_format($page['visits']) ?></td>
                <td><?= number_format($page['clicks']) ?></td>
                <td><?= $page['visits'] > 0 ? number_format(($page['clicks'] / $page['visits']) * 100, 2) : 0 ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const visitsData = <?= json_encode($stats['visits_chart']) ?>;
const clicksData = <?= json_encode($stats['clicks_chart']) ?>;

// Visits Chart
new Chart(document.getElementById('visitsChart'), {
    type: 'line',
    data: {
        labels: visitsData.labels,
        datasets: [{
            label: 'Visits',
            data: visitsData.values,
            borderColor: '#303034',
            backgroundColor: 'rgba(48, 48, 52, 0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Clicks Chart
new Chart(document.getElementById('clicksChart'), {
    type: 'line',
    data: {
        labels: clicksData.labels,
        datasets: [{
            label: 'Clicks',
            data: clicksData.values,
            borderColor: '#059669',
            backgroundColor: 'rgba(5, 150, 105, 0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<style>
.chart-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.chart-box {
    background: white;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.chart-box h2 {
    margin-bottom: 20px;
    font-size: 18px;
}

.chart-box canvas {
    height: 250px !important;
}
</style>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>