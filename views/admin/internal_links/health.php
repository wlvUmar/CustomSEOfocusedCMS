<?php
$pageName = 'internal_links/health';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="shield"></i> Link Health Checker</h1>
    <div class="btn-group">
        <button onclick="location.reload()" class="btn">
            <i data-feather="refresh-cw"></i> Refresh
        </button>
        <a href="<?= BASE_URL ?>/admin/internal-links" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back to Links
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid">
    <div class="stat-card <?= $summary['total_broken'] > 0 ? 'stat-danger' : 'stat-success' ?>">
        <h3><i data-feather="alert-triangle"></i> Broken Links</h3>
        <p class="stat-number"><?= $summary['total_broken'] ?></p>
    </div>

    <div class="stat-card">
        <h3><i data-feather="file-text"></i> Pages Affected</h3>
        <p class="stat-number"><?= $summary['pages_affected'] ?></p>
    </div>

    <div class="stat-card stat-info">
        <h3><i data-feather="shield"></i> Health Status</h3>
        <div class="health-status">
            <?php if ($summary['total_broken'] === 0): ?>
                <span class="status success"><i data-feather="check-circle"></i> Healthy</span>
            <?php elseif ($summary['total_broken'] < 5): ?>
                <span class="status warning"><i data-feather="alert-circle"></i> Warning</span>
            <?php else: ?>
                <span class="status danger"><i data-feather="x-circle"></i> Critical</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-card">
        <h3><i data-feather="clock"></i> Last Checked</h3>
        <p class="stat-muted"><?= date('M d, H:i') ?></p>
    </div>
</div>

<?php if (empty($brokenLinks)): ?>

<div class="empty-state">
    <i data-feather="check-circle"></i>
    <h2>All Links Healthy!</h2>
    <p>No broken internal links detected. Your site's link structure is in great shape.</p>
    <a href="<?= BASE_URL ?>/admin/internal-links" class="btn btn-primary">
        <i data-feather="link"></i> Back to Link Manager
    </a>
</div>

<?php else: ?>

<div class="info-banner warning">
    <i data-feather="alert-triangle"></i>
    <div>
        <strong>Found <?= count($brokenLinks) ?> broken link(s)</strong><br>
        These links point to pages that don't exist or aren't published.
    </div>
</div>

<?php foreach ($byPage as $pageId => $data): ?>
<div class="broken-page">
    <div class="broken-header">
        <div>
            <h3><i data-feather="file-text"></i> <?= e($data['page']['title_ru']) ?></h3>
            <code><?= e($data['page']['slug']) ?></code>
            <span class="badge danger"><?= count($data['broken_links']) ?> broken</span>
        </div>

        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $data['page']['id'] ?>" class="btn btn-sm">
            <i data-feather="edit"></i> Edit Page
        </a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Broken URL</th>
                <th>Link Text</th>
                <th>Lang</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['broken_links'] as $link): ?>
            <tr>
                <td><code class="text-danger"><?= e($link['broken_url']) ?></code></td>
                <td><?= e($link['link_text']) ?></td>
                <td><span class="badge"><?= strtoupper($link['language']) ?></span></td>
                <td>
                    <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/fix-broken"
                          onsubmit="return confirm('Remove this broken link?')">
                        <input type="hidden" name="page_id" value="<?= $data['page']['id'] ?>">
                        <input type="hidden" name="language" value="<?= $link['language'] ?>">
                        <button class="btn btn-sm btn-primary">
                            <i data-feather="tool"></i> Fix
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
