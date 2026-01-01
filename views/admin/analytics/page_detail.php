<?php
// path: ./views/admin/analytics/page_detail.php
$pageName = 'analytics/page_detail';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1>Page Analytics: <?= e($page['slug']) ?></h1>
    <div class="header-actions">
        <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>" target="_blank" class="btn">
            <i data-feather="eye"></i> View Page
        </a>
        <a href="<?= BASE_URL ?>/admin/analytics" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back to Analytics
        </a>
    </div>
</div>

<div class="info-banner">
    <strong><i data-feather="file-text"></i> Page Overview:</strong>
    This page shows how a single slug performs in terms of visits, clicks,
    crawl freshness, and content rotation effectiveness.
</div>

<!-- PAGE META SUMMARY -->
<div class="crawl-summary-cards">
    <div class="summary-card daily">
        <div class="card-icon"><i data-feather="globe"></i></div>
        <div class="card-content">
            <div class="card-number"><?= e($page['slug']) ?></div>
            <div class="card-label">Slug</div>
            <div class="card-desc">Canonical page identifier</div>
        </div>
    </div>

    <div class="summary-card weekly">
        <div class="card-icon"><i data-feather="refresh-cw"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $page['enable_rotation'] ? 'ON' : 'OFF' ?></div>
            <div class="card-label">Content Rotation</div>
            <div class="card-desc">
                <?= $page['enable_rotation'] ? 'Freshness enabled' : 'Static content' ?>
            </div>
        </div>
    </div>

    <div class="summary-card monthly">
        <div class="card-icon"><i data-feather="volume-2"></i></div>
        <div class="card-content">
            <div class="card-number"><?= $page['is_published'] ? 'Published' : 'Hidden' ?></div>
            <div class="card-label">Visibility</div>
            <div class="card-desc">Search engine access</div>
        </div>
    </div>
</div>

<!-- PERFORMANCE TRENDS -->
<div class="crawl-table-container">
    <h2>Month-over-Month Performance</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Period</th>
                <th>Visits</th>
                <th>Clicks</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Current Month</strong></td>
                <td><?= number_format($trends['current']['visits']) ?></td>
                <td><?= number_format($trends['current']['clicks']) ?></td>
            </tr>
            <tr>
                <td><strong>Previous Month</strong></td>
                <td><?= number_format($trends['previous']['visits']) ?></td>
                <td><?= number_format($trends['previous']['clicks']) ?></td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top:15px;">
        <strong>Change:</strong><br>
        Visits: <?= $trends['changes']['visits'] ?>%<br>
        Clicks: <?= $trends['changes']['clicks'] ?>%
    </p>
</div>

<!-- ROTATION IMPACT -->
<div class="crawl-table-container">
    <h2>Rotation Impact</h2>

    <?php if (empty($rotation_comparison)): ?>
        <div class="empty-state">
            <p>No rotation data recorded for this page.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Visits</th>
                    <th>Clicks</th>
                    <th>Rotation Active</th>
                    <th>Times Shown</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rotation_comparison as $row): ?>
                <tr>
                    <td><?= $row['year'] ?>-<?= str_pad($row['month'], 2, '0', STR_PAD_LEFT) ?></td>
                    <td><?= number_format($row['total_visits']) ?></td>
                    <td><?= number_format($row['total_clicks']) ?></td>
                    <td>
                        <span class="status-badge <?= $row['rotation_month'] ? 'status-daily' : 'status-rare' ?>">
                            <?= $row['rotation_month'] ? 'Yes' : 'No' ?>
                        </span>
                    </td>
                    <td><?= (int)($row['times_shown'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- DAILY ACTIVITY -->
<div class="crawl-table-container">
    <h2>Daily Activity (Last 30 Days)</h2>

    <?php if (empty($daily_activity)): ?>
        <p>No activity recorded.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Visits</th>
                    <th>Clicks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily_activity as $row): ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                    <td><?= number_format($row['visits']) ?></td>
                    <td><?= number_format($row['clicks']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- RECOMMENDATIONS -->
<div class="recommendations-panel">
    <h2>SEO & Crawl Recommendations</h2>

    <?php if (!$page['enable_rotation']): ?>
    <div class="recommendation warning">
        <div class="rec-icon"><i data-feather="alert-circle"></i></div>
        <div class="rec-content">
            <h3>Content rotation disabled</h3>
            <p>
                <strong>Action:</strong> Enable content rotation to increase crawl frequency
                and signal freshness to Google.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="recommendation info">
        <div class="rec-icon"><i data-feather="zap"></i></div>
        <div class="rec-content">
            <h3>Improve performance</h3>
            <ul>
                <li>Rotate titles and descriptions monthly</li>
                <li>Link to this page from high-traffic pages</li>
                <li>Update JSON-LD when content changes</li>
                <li>Request crawl after major rotation updates</li>
            </ul>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
