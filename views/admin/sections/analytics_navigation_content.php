<?php 
// path: ./views/admin/analytics/navigation.php
$pageName = 'analytics/navigation';
?>

<div class="page-header">
    <h1><i data-feather="git-branch"></i> Internal Navigation Analytics</h1>
    <div class="header-actions">
        <select onchange="window.location='?months='+this.value" class="btn">
            <option value="1" <?= $months == 1 ? 'selected' : '' ?>>Last Month</option>
            <option value="3" <?= $months == 3 ? 'selected' : '' ?>>Last 3 Months</option>
            <option value="6" <?= $months == 6 ? 'selected' : '' ?>>Last 6 Months</option>
        </select>
        <a href="<?= BASE_URL ?>/admin/analytics" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back to Analytics
        </a>
    </div>
</div>

<div class="info-banner">
    <strong><i data-feather="info"></i> Navigation Tracking:</strong> 
    This shows how users navigate between pages on your site. Use this data to optimize your internal linking structure and improve user flow.
</div>

<?php if (empty($navigation_flow) && empty($popular_paths)): ?>
    <div class="empty-state">
        <h2>No Navigation Data Yet</h2>
        <p>Internal link tracking will show data once users start navigating between pages.</p>
    </div>
<?php else: ?>

<!-- Popular Navigation Paths -->
<div class="crawl-table-container">
    <h2><i data-feather="trending-up"></i> Most Popular Navigation Paths</h2>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>From Page</th>
                <th><i data-feather="arrow-right"></i></th>
                <th>To Page</th>
                <th>Total Clicks</th>
                <th>Active Months</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($popular_paths as $path): ?>
            <tr>
                <td>
                    <a href="<?= BASE_URL ?>/<?= e($path['from_slug']) ?>" target="_blank">
                        <i data-feather="external-link"></i> <?= e($path['from_slug']) ?>
                    </a>
                </td>
                <td style="text-align: center; color: #6b7280;">
                    <i data-feather="arrow-right"></i>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/<?= e($path['to_slug']) ?>" target="_blank">
                        <i data-feather="external-link"></i> <?= e($path['to_slug']) ?>
                    </a>
                </td>
                <td><strong><?= number_format($path['clicks']) ?></strong> clicks</td>
                <td><?= $path['active_months'] ?> month(s)</td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/analytics/page/<?= e($path['from_slug']) ?>" 
                       class="btn btn-sm">
                        <i data-feather="bar-chart-2"></i> View Source
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Link Effectiveness -->
<?php if (!empty($link_effectiveness)): ?>
<div class="crawl-table-container">
    <h2><i data-feather="percent"></i> Link Effectiveness (Click-Through Rate)</h2>
    <p style="color: #6b7280; margin-bottom: 15px;">
        Shows what percentage of page visitors click each internal link
    </p>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>From Page</th>
                <th>To Page</th>
                <th>Link Clicks</th>
                <th>Page Visits</th>
                <th>CTR</th>
                <th>Performance</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($link_effectiveness as $link): 
                $ctr = $link['click_through_rate'];
                $performance = $ctr >= 10 ? 'Excellent' : ($ctr >= 5 ? 'Good' : ($ctr >= 2 ? 'Fair' : 'Poor'));
                $badgeClass = $ctr >= 10 ? 'badge-success' : ($ctr >= 5 ? 'badge' : 'badge-danger');
            ?>
            <tr>
                <td><?= e($link['from_slug']) ?></td>
                <td><?= e($link['to_slug']) ?></td>
                <td><?= number_format($link['link_clicks']) ?></td>
                <td><?= number_format($link['from_page_visits']) ?></td>
                <td><strong><?= $ctr ?>%</strong></td>
                <td>
                    <span class="badge <?= $badgeClass ?>">
                        <?= $performance ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Navigation Funnels -->
<?php if (!empty($navigation_funnels)): ?>
<div class="crawl-table-container">
    <h2><i data-feather="git-branch"></i> Common Navigation Sequences</h2>
    <p style="color: #6b7280; margin-bottom: 15px;">
        Shows the most common 3-step navigation paths users take
    </p>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Step 1</th>
                <th><i data-feather="arrow-right"></i></th>
                <th>Step 2</th>
                <th><i data-feather="arrow-right"></i></th>
                <th>Step 3</th>
                <th>Occurrences</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($navigation_funnels as $funnel): ?>
            <tr>
                <td><?= e($funnel['step1']) ?></td>
                <td style="text-align: center;"><i data-feather="arrow-right"></i></td>
                <td><?= e($funnel['step2']) ?></td>
                <td style="text-align: center;"><i data-feather="arrow-right"></i></td>
                <td><?= e($funnel['step3']) ?></td>
                <td><strong><?= number_format($funnel['occurrences']) ?></strong> times</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Recommendations Panel -->
<div class="recommendations-panel">
    <h2><i data-feather="lightbulb"></i> Optimization Recommendations</h2>
    
    <?php
    // Find pages with low CTR
    $lowCtrLinks = array_filter($link_effectiveness, function($link) {
        return $link['click_through_rate'] < 2 && $link['link_clicks'] > 10;
    });
    ?>
    
    <?php if (!empty($lowCtrLinks)): ?>
    <div class="recommendation warning">
        <div class="rec-icon"><i data-feather="alert-triangle"></i></div>
        <div class="rec-content">
            <h3>Low-Performing Links Detected</h3>
            <p><strong>Issue:</strong> <?= count($lowCtrLinks) ?> internal links have click-through rates below 2%.</p>
            <p><strong>Action:</strong></p>
            <ul>
                <li>Make links more prominent with better positioning</li>
                <li>Use more compelling anchor text</li>
                <li>Add visual cues (buttons, icons)</li>
                <li>Consider if the linked content is relevant to visitors</li>
            </ul>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="recommendation info">
        <div class="rec-icon"><i data-feather="git-branch"></i></div>
        <div class="rec-content">
            <h3>Optimize Navigation Flow</h3>
            <ul>
                <li><strong>High-performing paths:</strong> Add more links to popular destinations from your top pages</li>
                <li><strong>Orphaned pages:</strong> Check pages with few inbound links - they may need better promotion</li>
                <li><strong>Common sequences:</strong> The navigation funnels show natural user journeys - optimize these paths</li>
                <li><strong>CTR optimization:</strong> Links with 10%+ CTR are performing excellently - study what makes them effective</li>
            </ul>
        </div>
    </div>
    
    <?php if (!empty($navigation_funnels)): ?>
    <div class="recommendation success">
        <div class="rec-icon"><i data-feather="check-circle"></i></div>
        <div class="rec-content">
            <h3>Strong Navigation Patterns Identified</h3>
            <p>Users are following clear navigation paths through your site. This indicates good information architecture. Consider:</p>
            <ul>
                <li>Adding quick links for the most common sequences</li>
                <li>Creating related content sections based on these patterns</li>
                <li>Using breadcrumbs that reflect common navigation paths</li>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

