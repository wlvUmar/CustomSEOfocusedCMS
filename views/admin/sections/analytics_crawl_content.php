<?php 
// path: ./views/admin/analytics/crawl.php
$pageName = 'analytics/crawl';
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>Crawl Frequency Analysis</h1>
    <div class="header-actions">
        <select onchange="window.location='?days='+this.value" class="btn">
            <option value="7" <?= $days == 7 ? 'selected' : '' ?>>Last 7 Days</option>
            <option value="30" <?= $days == 30 ? 'selected' : '' ?>>Last 30 Days</option>
            <option value="60" <?= $days == 60 ? 'selected' : '' ?>>Last 60 Days</option>
            <option value="90" <?= $days == 90 ? 'selected' : '' ?>>Last 90 Days</option>
        </select>
        <a href="<?= BASE_URL ?>/admin/analytics" class="btn btn-secondary">Back to Analytics</a>
    </div>
</div>

<div class="info-banner">
    <strong><i data-feather="zap"></i> Understanding Crawl Frequency:</strong> This shows how often search engines visit your pages. 
    Higher frequency means Google considers your content important. Pages with low frequency may need more internal links or fresher content.
</div>

<?php if (empty($crawl_frequency)): ?>
    <div class="empty-state">
        <h2>No Crawl Data Available</h2>
        <p>Analytics tracking will show data once visitors start accessing your pages.</p>
    </div>
<?php else: ?>

<!-- Summary Stats -->
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
        <div class="card-icon"><i data-feather="calendar"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $dailyPages ?></div>
            <div class="card-label">Daily Crawl</div>
            <div class="card-desc">Visited within 24h</div>
        </div>
    </div>
    
    <div class="summary-card weekly">
        <div class="card-icon"><i data-feather="bar-chart-2"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $weeklyPages ?></div>
            <div class="card-label">Weekly Crawl</div>
            <div class="card-desc">Visited within 7 days</div>
        </div>
    </div>
    
    <div class="summary-card monthly">
        <div class="card-icon"><i data-feather="calendar"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $monthlyPages ?></div>
            <div class="card-label">Monthly Crawl</div>
            <div class="card-desc">Visited within 30 days</div>
        </div>
    </div>
    
    <div class="summary-card rare">
        <div class="card-icon"><i data-feather="alert-circle"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $rarePages ?></div>
            <div class="card-label">Rare/Stale</div>
            <div class="card-desc">Needs attention</div>
        </div>
    </div>
</div>

<!-- Detailed Table -->
<div class="crawl-table-container">
    <h2>Detailed Crawl Analysis (Last <?= $days ?> Days)</h2>
    
    <table class="data-table crawl-table">
        <thead>
            <tr>
                <th>Page</th>
                <th>Days with Visits</th>
                <th>Total Visits</th>
                <th>Avg/Day</th>
                <th>Last Visit</th>
                <th>Days Ago</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($crawl_frequency as $page): 
                $daysSince = round((strtotime('today') - strtotime($page['last_visit'])) / 86400);
                $avgPerDay = round($page['avg_visits_per_day'], 1);
                
                // Determine status
                if ($daysSince <= 1) {
                    $status = 'Daily';
                    $statusClass = 'status-daily';
                } elseif ($daysSince <= 7) {
                    $status = 'Weekly';
                    $statusClass = 'status-weekly';
                } elseif ($daysSince <= 30) {
                    $status = 'Monthly';
                    $statusClass = 'status-monthly';
                } else {
                    $status = 'Rare';
                    $statusClass = 'status-rare';
                }
            ?>
            <tr>
                <td>
                    <strong><?= e($page['page_slug']) ?></strong>
                </td>
                <td><?= $page['days_with_visits'] ?> / <?= $days ?></td>
                <td><?= number_format($page['total_visits']) ?></td>
                <td><?= $avgPerDay ?></td>
                <td><?= date('M d, Y', strtotime($page['last_visit'])) ?></td>
                <td>
                    <span class="days-ago <?= $daysSince > 7 ? 'warning' : '' ?>">
                        <?= $daysSince ?> day<?= $daysSince != 1 ? 's' : '' ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge <?= $statusClass ?>">
                        <?= $status ?>
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <a href="<?= BASE_URL ?>/<?= e($page['page_slug']) ?>" 
                           target="_blank" 
                           class="btn btn-sm" 
                           title="View page">
                            <i data-feather="eye"></i> View
                        </a>
                        <a href="<?= BASE_URL ?>/admin/analytics/page/<?= e($page['page_slug']) ?>" 
                           class="btn btn-sm" 
                           title="View analytics">
                            <i data-feather="bar-chart-2"></i> Stats
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Recommendations -->
<div class="recommendations-panel">
    <h2>Recommendations</h2>
    
    <?php if ($rarePages > 0): ?>
    <div class="recommendation warning">
        <div class="rec-icon"><i data-feather="alert-circle"></i></div>
        <div class="rec-content">
            <h3><?= $rarePages ?> page(s) with rare crawl frequency</h3>
            <p><strong>Action:</strong> These pages need attention. Consider:</p>
            <ul>
                <li>Adding more internal links to these pages</li>
                <li>Updating content to make it fresher</li>
                <li>Enabling content rotation to signal freshness</li>
                <li>Adding to sitemap with higher priority</li>
            </ul>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($dailyPages > 0): ?>
    <div class="recommendation success">
        <div class="rec-icon"><i data-feather="check-circle"></i></div>
        <div class="rec-content">
            <h3><?= $dailyPages ?> page(s) with daily crawl frequency</h3>
            <p><strong>Great!</strong> These pages are performing well. Google considers them important and up-to-date.</p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="recommendation info">
        <div class="rec-icon"><i data-feather="lightbulb"></i></div>
        <div class="rec-content">
            <h3>Improve Overall Crawl Frequency</h3>
            <ul>
                <li><strong>Content Rotation:</strong> Regular content updates signal freshness to search engines</li>
                <li><strong>Internal Linking:</strong> Link to underperforming pages from popular ones</li>
                <li><strong>Sitemap:</strong> Ensure all pages are in sitemap.xml with appropriate priority</li>
                <li><strong>Quality Content:</strong> Longer, more detailed content gets crawled more often</li>
            </ul>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
