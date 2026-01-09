<?php 
require BASE_PATH . '/views/admin/layout/header.php'; 

$pageModel = new Page();
$hierarchy = $pageModel->getHierarchy(false);
$allPages = $pageModel->getAll(true);

$sort = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'asc';

$sortedPages = $allPages;
usort($sortedPages, function($a, $b) use ($sort, $order) {
    $comparison = 0;
    
    switch ($sort) {
        case 'id':
            $comparison = $a['id'] <=> $b['id'];
            break;
        case 'slug':
            $comparison = strcasecmp($a['slug'], $b['slug']);
            break;
        case 'title_ru':
            $comparison = strcasecmp($a['title_ru'], $b['title_ru']);
            break;
        case 'title_uz':
            $comparison = strcasecmp($a['title_uz'], $b['title_uz']);
            break;
        case 'status':
            $comparison = ($a['is_published'] <=> $b['is_published']);
            break;
        default:
            $comparison = $a['id'] <=> $b['id'];
    }
    
    return $order === 'desc' ? -$comparison : $comparison;
});

// Rebuild hierarchy with sorted pages
function rebuildHierarchy($sortedPages, $parentId = null) {
    return array_filter(array_map(function($page) use ($sortedPages, $parentId) {
        if ($page['parent_id'] == $parentId) {
            $page['children'] = rebuildHierarchy($sortedPages, $page['id']);
            return $page;
        }
        return null;
    }, $sortedPages));
}

$sortedHierarchy = rebuildHierarchy($sortedPages);

function getSortLink($field, $currentSort, $currentOrder) {
    $newOrder = ($currentSort === $field && $currentOrder === 'asc') ? 'desc' : 'asc';
    return BASE_URL . '/admin/pages?sort=' . $field . '&order=' . $newOrder;
}

function getSortIndicator($field, $currentSort, $currentOrder) {
    if ($currentSort !== $field) return '';
    return $currentOrder === 'asc' ? ' ▲' : ' ▼';
}

function renderPageRow($page, $depth = 0) {
    $indent = str_repeat('—', $depth);
    $hasChildren = !empty($page['children']);
    ?>
    <tr class="page-row depth-<?= $depth ?>" data-page-id="<?= $page['id'] ?>">
        <td class="col-id">
            <div class="id-cell">
                <?php if ($depth > 0): ?>
                    <span class="hierarchy-indent"><?= $indent ?></span>
                    <i data-feather="corner-down-right" class="hierarchy-icon"></i>
                <?php endif; ?>
                
                <?php if ($hasChildren): ?>
                    <button class="toggle-children" onclick="toggleChildren(<?= $page['id'] ?>)" type="button">
                        <i data-feather="chevron-down"></i>
                    </button>
                <?php else: ?>
                    <span class="toggle-placeholder"></span>
                <?php endif; ?>
                
                <span class="page-id"><?= $page['id'] ?></span>
            </div>
        </td>
        <td class="col-slug">
            <?= e($page['slug']) ?>
        </td>
        <td class="col-title">
            <?= e($page['title_ru']) ?>
            <?php if ($hasChildren): ?>
                <span class="children-badge"><?= count($page['children']) ?></span>
            <?php endif; ?>
        </td>
        <td class="col-title">
            <?= e($page['title_uz']) ?>
        </td>
        <td class="col-status text-center">
            <span class="status-badge status-<?= $page['is_published'] ? 'active' : 'inactive' ?>">
                <?= $page['is_published'] ? 'Published' : 'Draft' ?>
            </span>
        </td>
        <td class="col-actions text-center">
            <div class="action-buttons">
                
                <?php if ($page['is_published']): ?>
                    <a href="<?= BASE_URL ?>/<?= e($page['slug']) ?>" 
                       target="_blank" 
                       class="btn btn-sm"
                       title="View Page">
                        <i data-feather="external-link"></i>
                    </a>
                <?php endif; ?>



                <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" 
                   class="btn btn-sm btn-primary"
                   title="Edit Page">
                    <i data-feather="edit"></i>
                </a>
                
                <form method="POST" 
                      action="<?= BASE_URL ?>/admin/pages/delete" 
                      style="display:inline;"
                      onsubmit="return confirm('Delete this page<?= $hasChildren ? ' and all its sub-pages' : '' ?>?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $page['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                        <i data-feather="trash-2"></i>
                    </button>
                </form>
            </div>
        </td>
    </tr>
    <?php
    
    // Render children recursively
    if ($hasChildren) {
        foreach ($page['children'] as $child) {
            renderPageRow($child, $depth + 1);
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1>Pages</h1>
        <p class="subtitle">Manage your website pages and hierarchy</p>
    </div>
    <div class="btn-group">
        <button onclick="expandAll()" class="btn btn-secondary">
            <i data-feather="maximize-2"></i> Expand All
        </button>
        <button onclick="collapseAll()" class="btn btn-secondary">
            <i data-feather="minimize-2"></i> Collapse All
        </button>
        <a href="<?= BASE_URL ?>/admin/pages/new" class="btn btn-primary">
            <i data-feather="plus"></i> New Page
        </a>
    </div>
</div>

<div class="hierarchy-stats">
    <div class="stat-item">
        <i data-feather="file-text"></i>
        <span><?= count($allPages) ?> total pages</span>
    </div>
    <div class="stat-item">
        <i data-feather="folder"></i>
        <span><?= count($hierarchy) ?> root pages</span>
    </div>
    <div class="stat-item">
        <i data-feather="layers"></i>
        <span><?= count($allPages) - count($hierarchy) ?> sub-pages</span>
    </div>
</div>

<table class="data-table pages-hierarchy-table">
    <thead>
        <tr>
            <th class="col-id">
                <a href="<?= getSortLink('id', $sort, $order) ?>" class="sortable-header">
                    ID<span class="sort-indicator"><?= getSortIndicator('id', $sort, $order) ?></span>
                </a>
            </th>
            <th class="col-slug">
                <a href="<?= getSortLink('slug', $sort, $order) ?>" class="sortable-header">
                    Slug<span class="sort-indicator"><?= getSortIndicator('slug', $sort, $order) ?></span>
                </a>
            </th>
            <th class="col-title">
                <a href="<?= getSortLink('title_ru', $sort, $order) ?>" class="sortable-header">
                    Title (RU)<span class="sort-indicator"><?= getSortIndicator('title_ru', $sort, $order) ?></span>
                </a>
            </th>
            <th class="col-title">
                <a href="<?= getSortLink('title_uz', $sort, $order) ?>" class="sortable-header">
                    Title (UZ)<span class="sort-indicator"><?= getSortIndicator('title_uz', $sort, $order) ?></span>
                </a>
            </th>
            <th class="col-status text-center">
                <a href="<?= getSortLink('status', $sort, $order) ?>" class="sortable-header">
                    Status<span class="sort-indicator"><?= getSortIndicator('status', $sort, $order) ?></span>
                </a>
            </th>
            <th class="col-actions text-center">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        if (empty($sortedHierarchy)): 
        ?>
        <tr>
            <td colspan="6" class="text-center">
                <div class="empty-state">
                    <i data-feather="inbox"></i>
                    <p>No pages yet</p>
                    <a href="<?= BASE_URL ?>/admin/pages/new" class="btn btn-primary">
                        Create First Page
                    </a>
                </div>
            </td>
        </tr>
        <?php 
        else:
            foreach ($sortedHierarchy as $page) {
                renderPageRow($page);
            }
        endif;
        ?>
    </tbody>
</table>

<style>

</style>

<script>
function toggleChildren(pageId) {
    const btn = document.querySelector(`[data-page-id="${pageId}"] .toggle-children`);
    const allRows = document.querySelectorAll('.page-row');
    
    let foundCurrent = false;
    let currentDepth = -1;
    
    for (let row of allRows) {
        if (row.dataset.pageId == pageId) {
            foundCurrent = true;
            currentDepth = parseInt(row.className.match(/depth-(\d+)/)[1]);
            continue;
        }
        
        if (foundCurrent) {
            const rowDepth = parseInt(row.className.match(/depth-(\d+)/)[1]);
            
            if (rowDepth <= currentDepth) {
                break;
            }
            
            if (rowDepth === currentDepth + 1) {
                row.classList.toggle('hidden');
            }
        }
    }
    
    btn.classList.toggle('collapsed');
}

function expandAll() {
    document.querySelectorAll('.page-row').forEach(row => {
        row.classList.remove('hidden');
    });
    document.querySelectorAll('.toggle-children').forEach(btn => {
        btn.classList.remove('collapsed');
    });
}

function collapseAll() {
    document.querySelectorAll('.page-row').forEach(row => {
        if (row.className.match(/depth-[1-9]/)) {
            row.classList.add('hidden');
        }
    });
    document.querySelectorAll('.toggle-children').forEach(btn => {
        btn.classList.add('collapsed');
    });
}
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>