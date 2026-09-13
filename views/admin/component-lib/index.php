<?php
require BASE_PATH . '/views/admin/layout/header.php';
?>
<div class="page-header">
    <div>
        <h1>Components</h1>
        <p class="subtitle">DB-canonical library — file is generated. <?= $total ?> blocks across <?= count($categories) ?> categories.</p>
    </div>
    <div class="btn-group">
        <form method="POST" action="<?= BASE_URL ?>/admin/components/rebuild" class="inline-form" onsubmit="return confirm('Regenerate components.css from DB?');">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-secondary"><i data-feather="refresh-cw"></i> Rebuild CSS</button>
        </form>
    </div>
</div>

<div class="component-filters">
    <form method="GET" action="<?= BASE_URL ?>/admin/components" class="filters-row">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search slug, title…" class="filter-input" />
        <select name="category" class="filter-select">
            <option value="">All categories (<?= $total ?>)</option>
            <?php foreach ($categories as $cat): $label = $categoryLabels[$cat] ?? $cat; $cnt = $catCounts[$cat] ?? 0; ?>
                <option value="<?= e($cat) ?>" <?= $category===$cat?'selected':'' ?>><?= e($label) ?> (<?= $cnt ?>)</option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="filter-select">
            <option value="">All statuses</option>
            <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
            <option value="deprecated" <?= $status==='deprecated'?'selected':'' ?>>Deprecated</option>
        </select>
        <button type="submit" class="btn btn-primary"><i data-feather="search"></i> Filter</button>
        <?php if ($q!==''||$category!==''||$status!==''): ?><a href="<?= BASE_URL ?>/admin/components" class="btn btn-secondary">Clear</a><?php endif; ?>
    </form>
    <div class="filter-stats">
        Showing <strong><?= count($rows) ?></strong> of <?= $total ?> blocks<?php if ($q!==''): ?> for "<?= e($q) ?>"<?php endif; ?>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>Slug</th>
            <th>Title</th>
            <th>Category</th>
            <th>Status</th>
            <th>Tokens</th>
            <th>Updated</th>
            <th class="text-center">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="text-center"><div class="empty-state"><i data-feather="inbox"></i><p>No components match filters.</p></div></td></tr>
        <?php else: foreach ($rows as $r):
            $tokens = $r['tokens_used'] ? json_decode($r['tokens_used'], true) : [];
        ?>
        <tr>
            <td class="col-slug"><code><?= e($r['slug']) ?></code></td>
            <td><?= e($r['title']) ?></td>
            <td><span class="badge badge-category"><?= e($categoryLabels[$r['category']] ?? $r['category']) ?></span></td>
            <td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
            <td class="col-tokens"><?php if ($tokens): foreach (array_slice($tokens,0,4) as $t): ?><span class="token-chip"><?= e($t) ?></span><?php endforeach; if (count($tokens)>4): ?><span class="muted">+<?= count($tokens)-4 ?></span><?php endif; else: ?><span class="muted">—</span><?php endif; ?></td>
            <td class="muted small"><?= e(substr($r['updated_at'] ?? '',0,16)) ?></td>
            <td class="text-center">
                <a href="<?= BASE_URL ?>/admin/components/edit/<?= e($r['slug']) ?>" class="btn btn-sm btn-primary" title="Edit"><i data-feather="edit"></i> Edit</a>
            </td>
        </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
