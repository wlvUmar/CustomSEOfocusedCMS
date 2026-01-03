<?php
// path: views/admin/internal_links/index.php
// INSTRUCTION: Replace the entire file

require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="link"></i> Internal Links Manager</h1>
    <div class="btn-group">
        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/bulk-auto-insert" style="display: inline;">
            <button type="submit" class="btn btn-primary" 
                    onclick="return confirm('Auto-insert links for all pages?\n\nThis will add up to 3 relevant links per page.')">
                <i data-feather="zap"></i> Bulk Auto-Insert All
            </button>
        </form>
    </div>
</div>

<div class="info-banner" style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 16px; margin-bottom: 30px; border-radius: 4px;">
    <strong><i data-feather="info"></i> How It Works:</strong>
    The system analyzes your page content and suggests relevant internal links based on keyword matches and title mentions. 
    Links improve SEO and user navigation.
</div>

<!-- Statistics Overview -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card">
        <h3><i data-feather="file-text"></i> Total Pages</h3>
        <p class="stat-number"><?= count($pages) ?></p>
    </div>
    
    <div class="stat-card">
        <h3><i data-feather="link"></i> Total Suggestions</h3>
        <p class="stat-number"><?= count($groupedSuggestions) ?></p>
    </div>
    
    <div class="stat-card">
        <h3><i data-feather="bar-chart-2"></i> Avg Suggestions/Page</h3>
        <p class="stat-number">
            <?php
            $totalSuggestions = 0;
            foreach ($groupedSuggestions as $group) {
                $totalSuggestions += count($group['suggestions']);
            }
            echo count($pages) > 0 ? round($totalSuggestions / count($pages), 1) : 0;
            ?>
        </p>
    </div>
</div>

<!-- All Pages Table -->
<div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 30px;">
    <h2 style="margin-bottom: 20px;"><i data-feather="list"></i> All Pages</h2>
    
    <table class="data-table">
        <thead>
            <tr>
                <th><i data-feather="hash"></i> ID</th>
                <th><i data-feather="file-text"></i> Page</th>
                <th><i data-feather="code"></i> Slug</th>
                <th><i data-feather="link"></i> Links (RU)</th>
                <th><i data-feather="link"></i> Links (UZ)</th>
                <th><i data-feather="target"></i> Suggestions</th>
                <th><i data-feather="settings"></i> Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $page): 
                $linksRu = count(array_filter($linksModel->getExistingLinks($page['id'], 'ru')));
                $linksUz = count(array_filter($linksModel->getExistingLinks($page['id'], 'uz')));
                $suggestions = isset($groupedSuggestions[$page['id']]) ? count($groupedSuggestions[$page['id']]['suggestions']) : 0;
            ?>
            <tr>
                <td><strong>#<?= $page['id'] ?></strong></td>
                <td>
                    <strong><?= e($page['title_ru']) ?></strong>
                    <br>
                    <small style="color: #6b7280;"><?= e($page['title_uz']) ?></small>
                </td>
                <td><code style="background: #f3f4f6; padding: 3px 8px; border-radius: 4px;"><?= e($page['slug']) ?></code></td>
                <td>
                    <?php if ($linksRu > 0): ?>
                        <span style="background: #d1f4e0; color: #065f46; padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 600;">
                            <i data-feather="check-circle" style="width: 14px; height: 14px; vertical-align: middle;"></i> <?= $linksRu ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #9ca3af; font-size: 0.9em;">
                            <i data-feather="minus-circle" style="width: 14px; height: 14px; vertical-align: middle;"></i> None
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($linksUz > 0): ?>
                        <span style="background: #d1f4e0; color: #065f46; padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 600;">
                            <i data-feather="check-circle" style="width: 14px; height: 14px; vertical-align: middle;"></i> <?= $linksUz ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #9ca3af; font-size: 0.9em;">
                            <i data-feather="minus-circle" style="width: 14px; height: 14px; vertical-align: middle;"></i> None
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($suggestions > 0): ?>
                        <span style="background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 12px; font-size: 0.85em; font-weight: 600;">
                            <i data-feather="lightbulb" style="width: 14px; height: 14px; vertical-align: middle;"></i> <?= $suggestions ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #9ca3af; font-size: 0.9em;">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display: flex; gap: 5px;">
                        <a href="<?= BASE_URL ?>/admin/internal-links/manage/<?= $page['id'] ?>" 
                           class="btn btn-sm btn-primary" title="Manage links">
                            <i data-feather="settings"></i> Manage
                        </a>
                        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" 
                           class="btn btn-sm" title="Edit page">
                            <i data-feather="edit"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.stat-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.stat-card h3 {
    font-size: 0.9em;
    color: #6b7280;
    margin-bottom: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #303034;
    line-height: 1;
    margin: 0;
}

.info-banner {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.info-banner i {
    margin-top: 2px;
    flex-shrink: 0;
}
</style>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>