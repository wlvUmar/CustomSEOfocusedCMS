<?php 
// path: ./views/admin/faqs/list.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<div class="page-header">
    <h1>FAQs</h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/faqs/new" class="btn btn-primary">
            <i data-feather="plus"></i> Add New FAQ
        </a>
        <button onclick="showUploadModal()" class="btn">
            <i data-feather="upload"></i> Bulk Upload
        </button>
        <a href="<?= BASE_URL ?>/admin/faqs/download-template" class="btn btn-secondary">
            <i data-feather="download"></i> Download Template
        </a>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Page</th>
            <th>Question (RU)</th>
            <th>Order</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($faqs as $faq): ?>
        <tr>
            <td><?= $faq['id'] ?></td>
            <td><?= e($faq['page_slug']) ?></td>
            <td><?= e(substr($faq['question_ru'], 0, 60)) ?>...</td>
            <td><?= $faq['sort_order'] ?></td>
            <td>
                <span class="badge <?= $faq['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                    <?= $faq['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </td>
            <td>
                <a href="<?= BASE_URL ?>/admin/faqs/edit/<?= $faq['id'] ?>" class="btn btn-sm">
                    <i data-feather="edit"></i> Edit
                </a>
                <form method="POST" action="<?= BASE_URL ?>/admin/faqs/delete" style="display:inline;" 
                      onsubmit="return confirm('Delete this FAQ?')">
                    <input type="hidden" name="id" value="<?= $faq['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i data-feather="trash-2"></i> Delete
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Upload Modal -->
<div id="upload-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i data-feather="upload"></i> Bulk Upload FAQs</h2>
            <button onclick="closeUploadModal()" class="close-btn"><i data-feather="x"></i></button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/faqs/bulk-upload" enctype="multipart/form-data">
            <div class="help-text" style="margin-bottom: 20px;">
                <strong>Supported formats:</strong> CSV, JSON<br>
                <strong>Required fields:</strong> page_slug, question_ru, answer_ru<br>
                <strong>Optional fields:</strong> question_uz, answer_uz, sort_order, is_active
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
    "page_slug": "home",
    "question_ru": "Какой вопрос?",
    "question_uz": "Qanday savol?",
    "answer_ru": "Это ответ",
    "answer_uz": "Bu javob",
    "sort_order": 0,
    "is_active": 1
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

<script>
function showUploadModal() {
    document.getElementById('upload-modal').style.display = 'flex';
    feather.replace();
}

function closeUploadModal() {
    document.getElementById('upload-modal').style.display = 'none';
}
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>