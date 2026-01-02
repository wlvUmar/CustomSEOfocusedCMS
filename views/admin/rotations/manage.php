<?php 
// path: ./views/admin/rotations/manage.php
$pageName = 'rotations/manage';
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>Content Rotations: <?= e($page['title_ru']) ?></h1>
    <div class="btn-group">
        <button onclick="showPreviewModal()" class="btn btn-primary">
            <i data-feather="eye"></i> Preview Page
        </button>
        <a href="<?= BASE_URL ?>/admin/rotations/overview" class="btn btn-secondary">
            <i data-feather="list"></i> Overview
        </a>
        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" class="btn btn-secondary">
            <i data-feather="edit"></i> Edit Page
        </a>
    </div>
</div>
<div class="bulk-actions-bar" style="margin-bottom: 20px; justify-content: flex-end;">
    <div class="bulk-buttons">
        <button onclick="showUploadModal()" class="btn">
            <i data-feather="upload"></i> Bulk Upload
        </button>
        <a href="<?= BASE_URL ?>/admin/rotations/download-template" class="btn btn-secondary">
            <i data-feather="download"></i> Download CSV Template
        </a>
    </div>
</div>
<div class="rotation-stats-bar">
    <div class="stat-item">
        <span class="stat-label"><i data-feather="pie-chart"></i> Coverage:</span>
        <span class="stat-value"><?= $stats['covered_months'] ?>/12 months</span>
    </div>
    <div class="stat-item">
        <span class="stat-label"><i data-feather="clock"></i> Current Month:</span>
        <span class="stat-value current-month"><?= $months[date('n')] ?></span>
    </div>
    <div class="stat-item">
        <span class="stat-label"><i data-feather="check-circle"></i> Active:</span>
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
        <i data-feather="plus-square"></i> Add Content
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
                <i data-feather="check"></i> Activate Selected
            </button>
            <button type="submit" name="action" value="deactivate" class="btn btn-sm" onclick="return confirmBulk('deactivate')">
                <i data-feather="x"></i> Deactivate Selected
            </button>
            <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirmBulk('delete')">
                <i data-feather="trash-2"></i> Delete Selected
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
                <th>Title (RU)</th>
                <th>Content Preview (RU)</th>
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
                    <span class="badge" style="background: #3b82f6; margin-left: 8px;">
                        <i data-feather="clock"></i> ACTIVE NOW
                    </span>
                    <?php endif; ?>
                </td>
                
                <td>
                    <?php if ($rotation && $rotation['title_ru']): ?>
                        <strong><?= e(substr($rotation['title_ru'], 0, 50)) ?><?php if (strlen($rotation['title_ru']) > 50): ?>...<?php endif; ?></strong>
                        <?php if ($rotation['description_ru']): ?>
                            <br><small style="color: #6b7280;"><?= e(substr($rotation['description_ru'], 0, 60)) ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <em class="no-content">No title</em>
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
                        <span class="badge <?= $rotation['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $rotation['is_active'] ? '<i data-feather="check-circle"></i> Active' : '<i data-feather="x-circle"></i> Inactive' ?>
                        </span>
                    <?php else: ?>
                        <span class="badge" style="background: #e5e7eb; color: #6b7280;">
                            <i data-feather="minus-circle"></i> Empty
                        </span>
                    <?php endif; ?>
                </td>
                
                <td>
                    <div class="action-buttons">
                        <?php if ($rotation): ?>
                            <a href="<?= BASE_URL ?>/admin/rotations/edit/<?= $rotation['id'] ?>" 
                               class="btn btn-sm" title="Edit">
                                <i data-feather="edit"></i> Edit
                            </a>
                            
                            <button type="button" onclick="showCloneModal(<?= $rotation['id'] ?>, '<?= e($name) ?>')" 
                                    class="btn btn-sm" title="Clone to other months">
                                <i data-feather="copy"></i> Clone
                            </button>
                            
                            <form method="POST" action="<?= BASE_URL ?>/admin/rotations/delete" 
                                  style="display:inline;" onsubmit="return confirm('Delete this rotation?')">
                                <input type="hidden" name="id" value="<?= $rotation['id'] ?>">
                                <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                    <i data-feather="trash-2"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/admin/rotations/new/<?= $page['id'] ?>?month=<?= $num ?>" 
                               class="btn btn-sm btn-primary">
                                <i data-feather="plus-square"></i> Add Content
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
            <button onclick="closeCloneModal()" class="close-btn"><i data-feather="x"></i></button>
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
                <button type="submit" class="btn btn-primary"><i data-feather="copy"></i> Clone Content</button>
                <button type="button" onclick="closeCloneModal()" class="btn btn-secondary"><i data-feather="x-circle"></i> Cancel</button>
            </div>

        </form>
    </div>
</div>


<div id="upload-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i data-feather="upload"></i> Bulk Upload Rotations</h2>
            <button onclick="closeUploadModal()" class="close-btn"><i data-feather="x"></i></button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/rotations/bulk-upload" enctype="multipart/form-data">
            <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
            
            <div class="help-text" style="margin-bottom: 20px;">
                <strong>Supported formats:</strong> CSV, JSON<br>
                <strong>Required fields:</strong> page_id, active_month, content_ru, content_uz<br>
                <strong>Optional fields:</strong> is_active, meta_title_ru, meta_title_uz, meta_description_ru, meta_description_uz, etc.
            </div>
            
            <div class="form-group">
                <label>Select File (CSV or JSON):</label>
                <input type="file" name="file" accept=".csv,.json" required>
            </div>
            
            <details style="margin: 20px 0;">
                <summary style="cursor: pointer; font-weight: 600; margin-bottom: 10px;">
                    JSON Format Example
                </summary>
                <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;">[
  {
    "page_id": 1,
    "active_month": 1,
    "content_ru": "Январский контент",
    "content_uz": "Yanvar matni",
    "is_active": 1,
    "meta_title_ru": "Заголовок"
  }
]</pre>
            </details>
            
            <div class="modal-actions">
                <button type="submit" class="btn btn-primary">
                    <i data-feather="upload"></i> Upload
                </button>
                <button type="button" onclick="closeUploadModal()" class="btn btn-secondary">
                    <i data-feather="x-circle"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<div id="preview-modal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2><i data-feather="eye"></i> Preview Page with Rotation</h2>
            <button onclick="closePreviewModal()" class="close-btn"><i data-feather="x"></i></button>
        </div>
        
        <div style="padding: 20px;">
            <div class="form-row" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label><strong>Select Month:</strong></label>
                    <select id="preview-month" class="btn" style="width: 100%;">
                        <?php foreach ($months as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $num == date('n') ? 'selected' : '' ?>>
                            <?= $name ?> <?= $num == date('n') ? '(Current)' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><strong>Language:</strong></label>
                    <select id="preview-lang" class="btn" style="width: 100%;">
                        <option value="ru">Русский</option>
                        <option value="uz">O'zbekcha</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-actions">
                <button onclick="openPreview()" class="btn btn-primary" style="width: 100%;">
                    <i data-feather="external-link"></i> Open Preview in New Tab
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showPreviewModal() {
    document.getElementById('preview-modal').style.display = 'flex';
    feather.replace();
}

function closePreviewModal() {
    document.getElementById('preview-modal').style.display = 'none';
}

function openPreview() {
    const month = document.getElementById('preview-month').value;
    const lang = document.getElementById('preview-lang').value;
    const url = '<?= BASE_URL ?>/admin/preview/<?= $page['id'] ?>?month=' + month + '&lang=' + lang;
    window.open(url, '_blank', 'width=1200,height=800');
    closePreviewModal();
}

// Close modal on outside click
document.getElementById('preview-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closePreviewModal();
    }
});
function toggleAll(checkbox) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function showUploadModal() {
    document.getElementById('upload-modal').style.display = 'flex';
    feather.replace();
}

function closeUploadModal() {
    document.getElementById('upload-modal').style.display = 'none';
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
