<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1><i data-feather="git-branch"></i> Internal Navigation Analytics</h1>
    <div class="header-actions">
        <select onchange="window.location='?months='+this.value" class="btn">
            <option value="1" <?= $months == 1 ? 'selected' : '' ?>>Last Month</option>
            <option value="3" <?= $months == 3 ? 'selected' : '' ?>>Last 3 Months</option>
            <option value="6" <?= $months == 6 ? 'selected' : '' ?>>Last 6 Months</option>
        </select>
    </div>
</div>

<div class="info-banner">
    <strong><i data-feather="info"></i> Navigation Analysis:</strong> 
    Visualize user flow, track click-through rates, and monitor internal link performance.
</div>

<?php if (empty($navigation_flow) && empty($link_effectiveness)): ?>
    <div class="empty-state">
        <h2>No Navigation Data Yet</h2>
        <p>Internal link tracking will show data once users start navigating between pages.</p>
    </div>
<?php else: ?>

<!-- Top Visuals Row -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
    
    <!-- Top User Flows (Visual) -->
    <div class="chart-box" style="margin: 0;">
        <h2><i data-feather="map"></i> Top User Flows (Last 30 Days)</h2>
        <div class="flow-container" style="padding: 10px 0;">
            <?php if (!empty($navigation_flow)): 
                $maxWeight = max(array_column($navigation_flow, 'weight'));
            ?>
                <?php foreach ($navigation_flow as $path): 
                    $width = ($path['weight'] / $maxWeight) * 100;
                    $width = max(100, $width); // Ensure visible
                ?>
                <div style="display: flex; align-items: center; margin-bottom: 12px; font-size: 14px;">
                    <div style="width: 35%; text-align: right; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 15px; font-weight: 500;">
                        <span title="<?= e($path['from_slug']) ?>"><?= e($path['from_slug']) ?></span>
                    </div>
                    
                    <div style="flex: 1; display: flex; align-items: center; position: relative;">
                        <!-- Arrow Body -->
                        <div style="height: <?= max(4, min(24, ($path['weight'] / $maxWeight) * 24)) ?>px; background: linear-gradient(90deg, #3b82f6, #8b5cf6); width: 100%; border-radius: 4px; opacity: 0.8; position: relative;"></div>
                        <!-- Arrow Head -->
                        <div style="position: absolute; right: -6px; color: #8b5cf6;">
                            <i data-feather="chevron-right"></i>
                        </div>
                        <!-- Label -->
                        <div style="position: absolute; width: 100%; text-align: center; color: white; font-size: 11px; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                            <?= number_format($path['weight']) ?> clicks
                        </div>
                    </div>
                    
                    <div style="width: 35%; padding-left: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;">
                        <span title="<?= e($path['to_slug']) ?>"><?= e($path['to_slug']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #6b7280; padding: 20px;">No flow data available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- CTR Distribution Chart -->
    <div class="chart-box" style="margin: 0;">
        <h2><i data-feather="pie-chart"></i> CTR Distribution</h2>
        <canvas id="ctrChart" height="250"></canvas>
    </div>
</div>

<!-- Trend Chart -->
<div class="chart-box">
    <h2><i data-feather="activity"></i> Internal Link Clicks Trend</h2>
    <canvas id="trendChart" height="100"></canvas>
</div>

<!-- Detailed Data (Collapsible) -->
<div class="chart-box">
    <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" onclick="document.getElementById('detailed-data').style.display = document.getElementById('detailed-data').style.display === 'none' ? 'block' : 'none';">
        <h2><i data-feather="list"></i> Detailed Link Performance</h2>
        <i data-feather="chevron-down"></i>
    </div>
    
    <div id="detailed-data" style="display: none; margin-top: 20px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>From Page</th>
                    <th>To Page</th>
                    <th>Clicks</th>
                    <th>Visits</th>
                    <th>CTR</th>
                    <th>Rating</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($link_effectiveness as $link): 
                    $ctr = $link['click_through_rate'];
                    $rating = $ctr >= 10 ? 'Excellent' : ($ctr >= 5 ? 'Good' : ($ctr >= 2 ? 'Fair' : 'Poor'));
                    $color = $ctr >= 10 ? '#10b981' : ($ctr >= 5 ? '#3b82f6' : ($ctr >= 2 ? '#f59e0b' : '#ef4444'));
                ?>
                <tr>
                    <td><?= e($link['from_slug']) ?></td>
                    <td><?= e($link['to_slug']) ?></td>
                    <td><?= number_format($link['link_clicks']) ?></td>
                    <td><?= number_format($link['from_page_visits']) ?></td>
                    <td><strong><?= $ctr ?>%</strong></td>
                    <td><span style="color: <?= $color ?>; font-weight: 600;"><?= $rating ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
    
    // CTR Chart
    const ctrCtx = document.getElementById('ctrChart');
    if (ctrCtx && <?= !empty($link_stats) ? 'true' : 'false' ?>) {
        new Chart(ctrCtx, {
            type: 'doughnut',
            data: {
                labels: ['Poor (<2%)', 'Fair (2-5%)', 'Good (5-10%)', 'Excellent (>10%)'],
                datasets: [{
                    data: [
                        <?= $link_stats['poor'] ?? 0 ?>,
                        <?= $link_stats['fair'] ?? 0 ?>,
                        <?= $link_stats['good'] ?? 0 ?>,
                        <?= $link_stats['excellent'] ?? 0 ?>
                    ],
                    backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'],
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
    
    // Trend Chart
    const trendCtx = document.getElementById('trendChart');
    const trendData = <?= json_encode($navigation_trends ?? ['labels' => [], 'values' => []]) ?>;
    
    if (trendCtx && trendData.labels.length > 0) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.labels,
                datasets: [{
                    label: 'Internal Link Clicks',
                    data: trendData.values,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
</script>

<?php endif; ?>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>