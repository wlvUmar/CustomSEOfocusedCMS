<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="page-header">
    <h1><i data-feather="git-branch"></i> Internal Links Manager</h1>
    <div class="btn-group">
        <button onclick="expandAll()" class="btn btn-secondary">
            <i data-feather="maximize-2"></i>
        </button>
        <button onclick="collapseAll()" class="btn btn-secondary">
            <i data-feather="minimize-2"></i>
        </button>
        <button onclick="showAutoConnectModal()" class="btn btn-primary">
            <i data-feather="zap"></i> Auto-Connect
        </button>
        <button onclick="showBulkModal()" class="btn btn-secondary">
            <i data-feather="layers"></i> Bulk
        </button>
    </div>
</div>

<!-- Network Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #3b82f6;">
            <i data-feather="file-text"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total_pages'] ?></h3>
            <p>Total Pages</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #10b981;">
            <i data-feather="link"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total_links'] ?></h3>
            <p>Total Links</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #f59e0b;">
            <i data-feather="activity"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['density_percentage'] ?>%</h3>
            <p>Network Density</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #8b5cf6;">
            <i data-feather="folder"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['total_root_pages'] ?? 0 ?></h3>
            <p>Root Pages</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #06b6d4;">
            <i data-feather="layers"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['hierarchy_depth'] ?? 0 ?></h3>
            <p>Max Depth</p>
        </div>
    </div>
    
    <div class="stat-card <?= ($stats['orphan_pages'] ?? 0) > 0 ? 'stat-warning' : '' ?>">
        <div class="stat-icon" style="background: #ef4444;">
            <i data-feather="alert-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?= $stats['orphan_pages'] ?></h3>
            <p>Orphan Pages</p>
        </div>
    </div>
</div>

<div class="info-banner">
    <i data-feather="info"></i>
    <div>
        <strong>Network Density:</strong> <?= $stats['density_percentage'] ?>% 
        (<?= $stats['total_links'] ?> of <?= $stats['max_possible_links'] ?> possible connections)
        <br>
        <strong>Average:</strong> <?= $stats['avg_links_per_page'] ?> links per page
    </div>
</div>

<!-- Filter Buttons -->
<div class="section-header">
    <h2>Pages & Link Status</h2>
    <div class="filter-buttons">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="root">Root</button>
        <button class="filter-btn" data-filter="orphan">Orphans</button>
    </div>
</div>

<!-- Pages Table -->
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 40px;">
                <input type="checkbox" id="select-all" onchange="toggleAll(this)">
            </th>
            <th>Page</th>
            <th>Hierarchy</th>
            <th style="text-align: center;">Outgoing</th>
            <th style="text-align: center;">Incoming</th>
            <th style="text-align: center;">Widget</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pages as $page): 
            $isRoot = empty($page['parent_id']);
            $hasChildren = !empty($page['children']);
            $isOrphan = $page['outgoing_links'] == 0 && $page['incoming_links'] == 0 && $isRoot;
            $depth = $page['level'] ?? 0;
            $indent = $depth * 25;
        ?>
        <tr class="page-row depth-<?= $depth ?>" 
            data-page-id="<?= $page['id'] ?>"
            data-is-root="<?= $isRoot ? '1' : '0' ?>"
            data-is-child="<?= !$isRoot ? '1' : '0' ?>"
            data-is-orphan="<?= $isOrphan ? '1' : '0' ?>">
            <td>
                <input type="checkbox" class="page-checkbox" value="<?= $page['id'] ?>">
            </td>
            <td>
                <div style="display: flex; align-items: center; padding-left: <?= $indent ?>px;">
                    <?php if ($depth > 0): ?>
                        <span style="color: #9ca3af; margin-right: 4px;">↳</span>
                    <?php endif; ?>
                    
                    <?php if ($hasChildren): ?>
                        <button class="toggle-children" onclick="toggleChildren(<?= $page['id'] ?>)" type="button">
                            <i data-feather="chevron-down"></i>
                        </button>
                    <?php else: ?>
                        <span class="toggle-placeholder"></span>
                    <?php endif; ?>

                    <div>
                        <strong><?= e($page['title_ru']) ?></strong>
                        <?php if ($isOrphan): ?>
                        <span class="badge badge-danger" style="margin-left: 8px;">Orphan</span>
                        <?php endif; ?>
                        <div style="font-size: 0.85em; color: #6b7280;">
                            <?= e($page['slug']) ?>
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <div class="hierarchy-info">
                    <?php if ($page['parent']): ?>
                        <div class="hierarchy-badge">
                            <i data-feather="corner-down-right"></i>
                            Sub of: <?= e($page['parent']['title_ru']) ?>
                        </div>
                    <?php else: ?>
                        <span class="hierarchy-badge root">
                            <i data-feather="folder"></i> Root
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($hasChildren): ?>
                        <span class="hierarchy-badge children">
                            <i data-feather="layers"></i> <?= count($page['children']) ?> sub-pages
                        </span>
                    <?php endif; ?>
                </div>
            </td>
            <td style="text-align: center;">
                <span class="badge" style="background: #3b82f6;">
                    <?= $page['outgoing_links'] ?>
                </span>
            </td>
            <td style="text-align: center;">
                <span class="badge" style="background: #10b981;">
                    <?= $page['incoming_links'] ?>
                </span>
            </td>
            <td style="text-align: center;">
                <?php if ($page['show_link_widget']): ?>
                <span class="badge badge-success">ON</span>
                <?php else: ?>
                <span class="badge badge-secondary">OFF</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="action-buttons">
                    <a href="<?= BASE_URL ?>/admin/internal-links/manage/<?= $page['id'] ?>" 
                       class="btn btn-sm" title="Manage Links">
                        <i data-feather="edit"></i> Manage
                    </a>
                    <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" 
                       class="btn btn-sm btn-secondary" title="Edit Page">
                        <i data-feather="file-text"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Auto-Connect Modal -->
<div id="auto-connect-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i data-feather="zap"></i> Auto-Connect Pages</h2>
            <button onclick="closeAutoConnectModal()" class="close-btn"><i data-feather="x"></i></button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/auto-connect">
            <div class="form-group">
                <label><strong>Connection Strategy:</strong></label>
                <select name="strategy" required>
                    <option value="hierarchy-aware" selected>Hierarchy-Aware (Recommended)</option>
                    <option value="all-to-all">All-to-All: Connect every page to every other page</option>
                    <option value="related">Smart: Connect related pages based on content</option>
                    <option value="popular-to-all">Hub Model: Connect all pages to popular pages</option>
                </select>
                <small>
                    <strong>Hierarchy-Aware:</strong> Links parent↔children, children↔siblings, and related pages based on your page hierarchy.
                </small>
            </div>
            
            <div class="form-group">
                <label><strong>Max Links per Page:</strong></label>
                <input type="number" name="max_links" value="5" min="1" max="20">
            </div>
            
            <div class="alert alert-warning">
                <i data-feather="alert-triangle"></i>
                <strong>Warning:</strong> This will create many links at once. Existing links will not be duplicated.
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="zap"></i> Auto-Connect
                </button>
                <button type="button" onclick="closeAutoConnectModal()" class="btn btn-secondary">
                    <i data-feather="x-circle"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div id="bulk-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i data-feather="layers"></i> Bulk Actions</h2>
            <button onclick="closeBulkModal()" class="close-btn"><i data-feather="x"></i></button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/bulk-action" onsubmit="return validateBulkForm()">
            <div class="form-group">
                <label><strong>Action:</strong></label>
                <select name="action" id="bulk-action" onchange="toggleTargetField()" required>
                    <option value="">Select action...</option>
                    <option value="add-links">Add link to all selected pages</option>
                    <option value="remove-links">Remove link from all selected pages</option>
                    <option value="enable-widget">Enable link widget for selected pages</option>
                    <option value="disable-widget">Disable link widget for selected pages</option>
                </select>
            </div>
            
            <div class="form-group" id="target-page-group" style="display: none;">
                <label><strong>Target Page:</strong></label>
                <select name="target_page_id" id="target-page">
                    <option value="">Select page...</option>
                    <?php foreach ($pages as $page): ?>
                    <option value="<?= $page['id'] ?>"><?= e($page['title_ru']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small>All selected pages will link to/unlink from this page</small>
            </div>
            
            <input type="hidden" name="page_ids" id="bulk-page-ids">
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="check"></i> Apply to <span id="selected-count">0</span> Page(s)
                </button>
                <button type="button" onclick="closeBulkModal()" class="btn btn-secondary">
                    <i data-feather="x-circle"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    margin-top: 30px;
}

.filter-buttons {
    display: flex;
    gap: 8px;
}

.filter-btn {
    padding: 6px 12px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.filter-btn:hover {
    background: #f3f4f6;
}

.filter-btn.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.hierarchy-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.hierarchy-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    background: #e5e7eb;
    color: #374151;
    width: fit-content;
}

.hierarchy-badge.root {
    background: #dbeafe;
    color: #1e40af;
}

.hierarchy-badge.children {
    background: #d1fae5;
    color: #065f46;
}

.hierarchy-badge svg {
    width: 14px;
    height: 14px;
}

.page-row[data-is-orphan="1"] {
    opacity: 0.7;
}

/* Collapsible Hierarchy Styles */
.page-row.hidden {
    display: none !important;
}

.toggle-children {
    background: none;
    border: none;
    padding: 2px;
    cursor: pointer;
    color: #6b7280;
    transition: transform 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    margin-right: 4px;
}

.toggle-children:hover {
    color: #111827;
    background: #f3f4f6;
    border-radius: 4px;
}

.toggle-children.collapsed {
    transform: rotate(-90deg);
}

.toggle-placeholder {
    display: inline-block;
    width: 20px;
    height: 20px;
    margin-right: 4px;
}
</style>

<script>
function filterPages(type) {
    const rows = document.querySelectorAll('.page-row');
    const buttons = document.querySelectorAll('.filter-btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-filter="${type}"]`).classList.add('active');
    
    rows.forEach(row => {
        let show = false;
        
        switch(type) {
            case 'all':
                show = true;
                break;
            case 'root':
                show = row.dataset.isRoot === '1';
                break;
            case 'children':
                show = row.dataset.isChild === '1';
                break;
            case 'orphan':
                show = row.dataset.isOrphan === '1';
                break;
        }
        
        row.style.display = show ? '' : 'none';
        
        // Reset hierarchy visibility when filtering
        if (type !== 'all') {
            row.classList.remove('hidden');
        }
    });
}

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
            const rowDepthMatch = row.className.match(/depth-(\d+)/);
            if (!rowDepthMatch) continue;
            
            const rowDepth = parseInt(rowDepthMatch[1]);
            
            if (rowDepth <= currentDepth) {
                break;
            }
            
            // If we are collapsing, hide all descendants
            if (!btn.classList.contains('collapsed')) {
                row.classList.add('hidden');
                // Also collapse the children buttons of descendants to keep state consistent
                const childBtn = row.querySelector('.toggle-children');
                if (childBtn) childBtn.classList.add('collapsed');
            } else {
                // If we are expanding, only show direct children (depth + 1)
                // BUT we need to be careful. If we just show depth+1, we might leave their children hidden (correct).
                if (rowDepth === currentDepth + 1) {
                    row.classList.remove('hidden');
                    // Ensure the direct child is set to collapsed initially
                     const childBtn = row.querySelector('.toggle-children');
                     if (childBtn) childBtn.classList.add('collapsed');
                }
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
        const depthMatch = row.className.match(/depth-(\d+)/);
        if (depthMatch && parseInt(depthMatch[1]) > 0) {
            row.classList.add('hidden');
        }
    });
    document.querySelectorAll('.toggle-children').forEach(btn => {
        btn.classList.add('collapsed');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const type = this.dataset.filter;
            filterPages(type);
        });
    });
});
</script>

<script src="<?= BASE_URL ?>/js/admin/features/internal-links.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
