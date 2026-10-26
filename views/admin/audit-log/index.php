<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/components/badges.css?v=2">

<?php
$entityTypes = ['page', 'article', 'faq', 'rotation', 'component', 'seo_settings', 'link_widget'];
$actions = ['create', 'update', 'delete', 'publish', 'unpublish', 'link_add', 'link_remove'];
$sources = ['admin', 'ai', 'system'];
$actionBadge = ['create' => 'badge-success', 'delete' => 'badge-danger', 'publish' => 'badge-success', 'unpublish' => 'badge-warning', 'update' => 'badge-info'];
$query = fn(array $over = []) => http_build_query(array_filter(array_merge($filters, ['p' => $page], $over), fn($v) => $v !== '' && $v !== null));
?>

<div class="page-header">
    <h1>Change Log</h1>
    <span class="audit-total"><?= (int)$total ?> entries</span>
</div>

<form method="GET" class="audit-filters">
    <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search slug or summary">
    <select name="entity_type">
        <option value="">All types</option>
        <?php foreach ($entityTypes as $t): ?>
            <option value="<?= $t ?>" <?= $filters['entity_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
    </select>
    <select name="action">
        <option value="">All actions</option>
        <?php foreach ($actions as $a): ?>
            <option value="<?= $a ?>" <?= $filters['action'] === $a ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach; ?>
    </select>
    <select name="source">
        <option value="">All sources</option>
        <?php foreach ($sources as $s): ?>
            <option value="<?= $s ?>" <?= $filters['source'] === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="From date">
    <input type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="To date">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="<?= BASE_URL ?>/admin/audit-log" class="btn btn-secondary">Reset</a>
</form>

<table class="data-table audit-table">
    <thead>
        <tr>
            <th>When</th>
            <th>Who</th>
            <th>Action</th>
            <th>What</th>
            <th>Changes</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="5">No changes recorded.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['created_at']) ?></td>
            <td>
                <?= e($r['username'] ?? '—') ?>
                <span class="badge badge-secondary"><?= e($r['source']) ?></span>
            </td>
            <td><span class="badge <?= $actionBadge[$r['action']] ?? 'badge-info' ?>"><?= e($r['action']) ?></span></td>
            <td>
                <?= e($r['entity_type']) ?>
                <?php if ($r['entity_id']): ?>
                    <a href="?<?= e($query(['entity_type' => $r['entity_type'], 'entity_id' => $r['entity_id'], 'p' => 1])) ?>">#<?= (int)$r['entity_id'] ?></a>
                <?php endif; ?>
                <div class="audit-label"><?= e($r['entity_label'] ?? '') ?></div>
            </td>
            <td>
                <?php if ($r['changes']): ?>
                <details>
                    <summary><?= e($r['summary'] ?? count($r['changes']) . ' field(s)') ?></summary>
                    <?php foreach ($r['changes'] as $field => $c): ?>
                        <div class="audit-change">
                            <strong><?= e($field) ?></strong>
                            <?php if (!empty($c['partial'])): ?>
                                <span class="audit-meta">
                                    <?= (int)$c['from_len'] ?> → <?= (int)$c['to_len'] ?> chars, changed near char <?= (int)$c['at'] ?>
                                </span>
                            <?php endif; ?>
                            <div class="audit-from"><?= $c['from'] === null ? '<em>empty</em>' : e($c['from']) ?></div>
                            <div class="audit-to"><?= $c['to'] === null ? '<em>empty</em>' : e($c['to']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </details>
                <?php else: ?>
                    <?= e($r['summary'] ?? '') ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($pages > 1): ?>
<nav class="audit-pager">
    <?php if ($page > 1): ?><a class="btn btn-sm" href="?<?= e($query(['p' => $page - 1])) ?>">Newer</a><?php endif; ?>
    <span>Page <?= (int)$page ?> of <?= (int)$pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-sm" href="?<?= e($query(['p' => $page + 1])) ?>">Older</a><?php endif; ?>
</nav>
<?php endif; ?>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
