<?php 
$pageName = 'internal_links/index';
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1><i data-feather="git-branch"></i> Internal Links Manager</h1>
    <div class="btn-group">
        <button onclick="showAutoConnectModal()" class="btn btn-primary">
            <i data-feather="zap"></i> Auto-Connect Pages
        </button>
        <button onclick="showBulkModal()" class="btn btn-secondary">
            <i data-feather="layers"></i> Bulk Actions
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

<!-- Pages Table -->
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 40px;">
                <input type="checkbox" id="select-all" onchange="toggleAll(this)">
            </th>
            <th>Page</th>
            <th>Slug</th>
            <th style="text-align: center;">Outgoing Links</th>
            <th style="text-align: center;">Incoming Links</th>
            <th style="text-align: center;">Widget Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pages as $page): ?>
        <tr>
            <td>
                <input type="checkbox" class="page-checkbox" value="<?= $page['id'] ?>">
            </td>
            <td>
                <strong><?= e($page['title_ru']) ?></strong>
                <?php if ($page['outgoing_links'] == 0 && $page['incoming_links'] == 0): ?>
                <span class="badge badge-danger" style="margin-left: 8px;">Orphan</span>
                <?php endif; ?>
            </td>
            <td><code><?= e($page['slug']) ?></code></td>
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
                <span class="badge badge-success">Enabled</span>
                <?php else: ?>
                <span class="badge badge-danger">Disabled</span>
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
                    <option value="all-to-all">All-to-All: Connect every page to every other page (dense network)</option>
                    <option value="popular-to-all">Hub Model: Connect all pages to most popular pages</option>
                    <option value="related">Smart: Connect related pages based on content similarity</option>
                </select>
                <small>Choose how pages should be connected automatically</small>
            </div>
            
            <div class="form-group">
                <label><strong>Max Links per Page (for Smart strategy):</strong></label>
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

<script src="<?= BASE_URL ?>/js/admin/internal-links.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
