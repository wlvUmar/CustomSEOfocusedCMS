<?php 
// path: ./views/admin/rotations/manage.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>Content Rotations: <?= e($page['title_ru']) ?></h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/rotations/overview" class="btn btn-secondary">Overview</a>
        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" class="btn btn-secondary">Edit Page</a>
    </div>
</div>

<div class="rotation-stats-bar">
    <div class="stat-item">
        <span class="stat-label">Coverage:</span>
        <span class="stat-value"><?= $stats['covered_months'] ?>/12 months</span>
    </div>
    <div class="stat-item">
        <span class="stat-label">Current Month:</span>
        <span class="stat-value current-month"><?= $months[date('n')] ?></span>
    </div>
    <div class="stat-item">
        <span class="stat-label">Active:</span>
        <span class="stat-value"><?= count($stats['active_months']) ?></span>
    </div>
</div>

<?php if (!empty($stats['missing_months'])): ?>
<div class="alert alert-error">
    <strong>Missing months:</strong> 
    <?php 
    $missingNames = array_map(function($m) use ($months) {
        return $months[$m];
    }, $stats['missing_months']);
    echo implode(', ', $missingNames);
    ?>
    <a href="<?= BASE_URL ?>/admin/rotations/new/<?= $page['id'] ?>" class="btn btn-sm" style="margin-left: 15px;">
        Add Content
    </a>
</div>
<?php endif; ?>

<form id="bulk-form" method="POST" action="<?= BASE_URL ?>/admin/rotations/bulk-action">
    <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
    
    <div class="bulk-actions-bar">
        <div>
            <input type="checkbox" id="select-all" onchange="toggleAll(this)">
            <label for="select-all" style="margin-left: 8px;">Select All</label>
        </div>
        
        <div class="bulk-buttons">
            <button type="submit" name="action" value="activate" class="btn btn-sm" onclick="return confirmBulk('activate')">
                ✓ Activate Selected
            </button>
            <button type="submit" name="action" value="deactivate" class="btn btn-sm" onclick="return confirmBulk('deactivate')">
                ✗ Deactivate Selected
            </button>
            <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirmBulk('delete')">
                🗑 Delete Selected
            </button>
        </div>
    </div>

    <table class="data-table rotation-table">
        <thead>
            <tr>
                <th style="width: 40px;">
                    <input type="checkbox" disabled>
                </th>
                <th>Month</th>
                <th>Preview (RU)</th>
                <th>Preview (UZ)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $hasContent = [];
            foreach ($rotations as $r) {
                $hasContent[$r['active_month']] = $r;
            }
            
            $currentMonth = date('n');
            
            foreach ($months as $num => $name): 
                $rotation = $hasContent[$num] ?? null;
                $isCurrent = ($num == $currentMonth);
            ?>
            <tr class="<?= $isCurrent ? 'current-month-row' : '' ?> <?= $rotation && !$rotation['is_active'] ? 'inactive-row' : '' ?>">
                <td>
                    <?php if ($rotation): ?>
                    <input type="checkbox" name="ids[]" value="<?= $rotation['id'] ?>" class="row-checkbox">
                    <?php endif; ?>
                </td>
                
                <td>
                    <strong><?= $name ?></strong>
                    <?php if ($isCurrent): ?>
                    <span class="badge" style="background: #3b82f6; margin-left: 8px;">ACTIVE NOW</span>
                    <?php endif; ?>
                </td>
                
                <td>
                    <?php if ($rotation): ?>
                        <div class="content-preview">
                            <?= e(substr(strip_tags($rotation['content_ru']), 0, 100)) ?>
                            <?php if (strlen(strip_tags($rotation['content_ru'])) > 100): ?>...<?php endif; ?>
                        </div>
                    <?php else: ?>
                        <em class="no-content">No content</em>
                    <?php endif; ?>
                </td>
                
                <td>
                    <?php if ($rotation): ?>
                        <div class="content-preview">
                            <?= e(substr(strip_tags($rotation['content_uz']), 0, 100)) ?>
                            <?php if (strlen(strip_tags($rotation['content_uz'])) > 100): ?>...<?php endif; ?>
                        </div>
                    <?php else: ?>
                        <em class="no-content">No content</em>
                    <?php endif; ?>
                </td>
                
                <td>
                    <?php if ($rotation): ?>
                        <span class="badge <?= $rotation['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $rotation['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    <?php else: ?>
                        <span class="badge" style="background: #e5e7eb; color: #6b7280;">Empty</span>
                    <?php endif; ?>
                </td>
                
                <td>
                    <div class="action-buttons">
                        <?php if ($rotation): ?>
                            <a href="<?= BASE_URL ?>/admin/rotations/edit/<?= $rotation['id'] ?>" 
                               class="btn btn-sm" title="Edit">
                                ✏️ Edit
                            </a>
                            
                            <button type="button" onclick="showCloneModal(<?= $rotation['id'] ?>, '<?= e($name) ?>')" 
                                    class="btn btn-sm" title="Clone to other months">
                                📋 Clone
                            </button>
                            
                            <form method="POST" action="<?= BASE_URL ?>/admin/rotations/delete" 
                                  style="display:inline;" onsubmit="return confirm('Delete this rotation?')">
                                <input type="hidden" name="id" value="<?= $rotation['id'] ?>">
                                <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑</button>
                            </form>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/admin/rotations/new/<?= $page['id'] ?>?month=<?= $num ?>" 
                               class="btn btn-sm btn-primary">
                                + Add Content
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</form>

<!-- Clone Modal -->
<div id="clone-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Clone Content to Other Months</h2>
            <button onclick="closeCloneModal()" class="close-btn">×</button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/rotations/clone">
            <input type="hidden" name="source_id" id="clone-source-id">
            
            <p>Clone content from <strong id="clone-source-name"></strong> to:</p>
            
            <div class="form-group">
                <label>Target Month:</label>
                <select name="target_month" required>
                    <option value="">Select month...</option>
                    <?php foreach ($months as $num => $name): ?>
                    <option value="<?= $num ?>"><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">Clone Content</button>
                <button type="button" onclick="closeCloneModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.rotation-stats-bar {
    display: flex;
    gap: 30px;
    padding: 20px;
    background: white;
    border-radius: 6px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-item {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 0.85em;
    color: var(--text-muted);
    margin-bottom: 4px;
}

.stat-value {
    font-size: 1.2em;
    font-weight: 600;
    color: var(--text-dark);
}

.stat-value.current-month {
    color: #3b82f6;
}

.bulk-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: var(--accent-light);
    border-radius: 6px;
    margin-bottom: 15px;
}

.bulk-buttons {
    display: flex;
    gap: 10px;
}

.rotation-table {
    margin-top: 0;
}

.current-month-row {
    background: #eff6ff !important;
    border-left: 3px solid #3b82f6;
}

.inactive-row {
    opacity: 0.6;
}

.content-preview {
    font-size: 0.9em;
    color: var(--text-muted);
    line-height: 1.4;
}

.no-content {
    color: #9ca3af;
}

.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    padding: 0;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid var(--accent-light);
}

.modal-header h2 {
    margin: 0;
    font-size: 1.3em;
}

.close-btn {
    background: none;
    border: none;
    font-size: 2em;
    cursor: pointer;
    color: var(--text-muted);
    line-height: 1;
}

.modal form {
    padding: 20px;
}

.modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--accent-light);
}
</style>

<script>
function toggleAll(checkbox) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function confirmBulk(action) {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    if (checked === 0) {
        alert('Please select at least one item');
        return false;
    }
    
    const messages = {
        'activate': `Activate ${checked} rotation(s)?`,
        'deactivate': `Deactivate ${checked} rotation(s)?`,
        'delete': `Delete ${checked} rotation(s)? This cannot be undone!`
    };
    
    return confirm(messages[action] || 'Continue?');
}

function showCloneModal(sourceId, sourceName) {
    document.getElementById('clone-source-id').value = sourceId;
    document.getElementById('clone-source-name').textContent = sourceName;
    document.getElementById('clone-modal').style.display = 'flex';
}

function closeCloneModal() {
    document.getElementById('clone-modal').style.display = 'none';
}
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>