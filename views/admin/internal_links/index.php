<?php
$pageName = 'internal_links/index';
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="link"></i> Internal Links Manager</h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/internal-links/health" class="btn btn-secondary">
            <i data-feather="shield"></i> Link Health Check
        </a>
        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/bulk-auto-insert" class="inline-form">
            <button type="submit" class="btn btn-primary"
                onclick="return confirm('Auto-insert links for all pages?\n\nThis will add up to 3 relevant links per page.')">
                <i data-feather="zap"></i> Bulk Auto-Insert All
            </button>
        </form>
    </div>
</div>

<!-- Info -->
<div class="info-banner info">
    <i data-feather="info"></i>
    <div>
        <strong>How It Works:</strong>
        The system analyzes your page content and suggests relevant internal links based on keyword matches and title mentions.
        Links improve SEO and user navigation.
    </div>
</div>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <h3><i data-feather="file-text"></i> Total Pages</h3>
        <p class="stat-number"><?= count($pages) ?></p>
    </div>

    <div class="stat-card">
        <h3><i data-feather="target"></i> Pages with Suggestions</h3>
        <p class="stat-number"><?= count($groupedSuggestions) ?></p>
    </div>

    <div class="stat-card">
        <h3><i data-feather="bar-chart-2"></i> Total Suggestions</h3>
        <p class="stat-number">
            <?php
            $totalSuggestions = 0;
            foreach ($groupedSuggestions as $g) {
                $totalSuggestions += count($g['suggestions']);
            }
            echo $totalSuggestions;
            ?>
        </p>
    </div>

    <div class="stat-card">
        <h3><i data-feather="trending-up"></i> Avg Suggestions/Page</h3>
        <p class="stat-number">
            <?= count($pages) ? round($totalSuggestions / count($pages), 1) : 0 ?>
        </p>
    </div>
</div>

<!-- Pages Table -->
<div class="panel">
    <h2 class="panel-title"><i data-feather="list"></i> All Pages</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th class="col-id">#</th>
                <th>Page</th>
                <th class="col-slug">Slug</th>
                <th class="col-center">RU</th>
                <th class="col-center">UZ</th>
                <th class="col-center">Suggestions</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $page): 
            $linksRu = count($linksModel->getExistingLinks($page['id'], 'ru'));
            $linksUz = count($linksModel->getExistingLinks($page['id'], 'uz'));
            $suggestions = $groupedSuggestions[$page['id']]['suggestions'] ?? [];
        ?>
            <tr>
                <td><strong><?= $page['id'] ?></strong></td>

                <td>
                    <strong><?= e($page['title_ru']) ?></strong><br>
                    <small class="text-muted"><?= e($page['title_uz']) ?></small>
                </td>

                <td>
                    <code class="slug"><?= e($page['slug']) ?></code>
                </td>

                <td class="col-center">
                    <?= $linksRu
                        ? '<span class="pill success"><i data-feather="check-circle"></i>'.$linksRu.'</span>'
                        : '<i data-feather="minus-circle" class="muted-icon"></i>'
                    ?>
                </td>

                <td class="col-center">
                    <?= $linksUz
                        ? '<span class="pill success"><i data-feather="check-circle"></i>'.$linksUz.'</span>'
                        : '<i data-feather="minus-circle" class="muted-icon"></i>'
                    ?>
                </td>

                <td class="col-center">
                    <?= count($suggestions)
                        ? '<span class="pill warning"><i data-feather="lightbulb"></i>'.count($suggestions).'</span>'
                        : '<span class="text-muted">-</span>'
                    ?>
                </td>

                <td>
                    <div class="actions">
                        <a href="<?= BASE_URL ?>/admin/internal-links/manage/<?= $page['id'] ?>"
                           class="btn btn-sm btn-primary">
                            <i data-feather="settings"></i> Manage
                        </a>
                        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>"
                           class="btn btn-sm">
                            <i data-feather="edit"></i>
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
