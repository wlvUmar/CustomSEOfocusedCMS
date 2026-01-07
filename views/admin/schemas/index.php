<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="dashboard-header">
    <h1>Schema Management</h1>
    <p class="dashboard-subtitle">Manage JSON-LD schemas for your pages</p>
</div>

<div class="dashboard-grid full-width">
    <!-- List & Edit Section -->
    <div class="dashboard-section">
        <h2 class="section-title"><i data-feather="code"></i> Schema Editor</h2>
        
        <form action="<?= BASE_URL ?>/admin/schemas/save" method="POST" id="schemaForm">
            <div class="form-group">
                <label>Page Slug</label>
                <select name="slug" id="slugSelect" class="form-control" onchange="loadSchema()">
                    <option value="">-- Select a Page --</option>
                    <?php foreach ($pages as $p): ?>
                        <option value="<?= e($p['slug']) ?>" <?= isset($schemas[$p['slug']]) ? 'data-has-schema="true"' : '' ?>>
                            <?= e($p['title_ru'] ?? $p['title_uz'] ?? 'Untitled') ?> (<?= e($p['slug']) ?>) <?= isset($schemas[$p['slug']]) ? '✓' : '' ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="custom">-- Custom Slug --</option>
                </select>
                <input type="text" name="custom_slug" id="customSlug" class="form-control mt-2" style="display:none;" placeholder="Enter custom slug (e.g. blog/post-1)">
            </div>
            
            <div class="form-group">
                <label>JSON-LD Data</label>
                <textarea name="json" id="jsonEditor" class="form-control code-editor" rows="15" placeholder='{ "@context": "https://schema.org", ... }' style="font-family: monospace;"></textarea>
                <small class="form-text text-muted">Paste your full JSON-LD object here. Leave empty to delete.</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Schema</button>
            </div>
        </form>
    </div>
    
    <!-- Bulk Import Section -->
    <div class="dashboard-section">
        <h2 class="section-title"><i data-feather="upload-cloud"></i> Bulk Import</h2>
        <form action="<?= BASE_URL ?>/admin/schemas/bulk-import" method="POST">
            <div class="form-group">
                <label>Bulk JSON Data</label>
                <textarea name="json" class="form-control code-editor" rows="10" placeholder='{ "slug1": { ... }, "slug2": { ... } }' style="font-family: monospace;"></textarea>
                <small class="form-text text-muted">Import multiple schemas at once. Format: Object where keys are slugs and values are schema objects. Existing keys will be overwritten.</small>
            </div>
            <button type="submit" class="btn btn-secondary"><i data-feather="upload"></i> Import</button>
        </form>
    </div>
</div>

<script>
const schemas = <?= json_encode($schemas) ?>;

function loadSchema() {
    const select = document.getElementById('slugSelect');
    const customSlug = document.getElementById('customSlug');
    const editor = document.getElementById('jsonEditor');
    const slug = select.value;
    
    if (slug === 'custom') {
        customSlug.style.display = 'block';
        editor.value = '';
    } else {
        customSlug.style.display = 'none';
        if (slug && schemas[slug]) {
            editor.value = JSON.stringify(schemas[slug], null, 4);
        } else {
            editor.value = '';
        }
    }
}

document.getElementById('schemaForm').addEventListener('submit', function(e) {
    const select = document.getElementById('slugSelect');
    if (select.value === 'custom') {
        const customSlug = document.getElementById('customSlug');
        if (customSlug.value) {
             const input = document.createElement('input');
             input.type = 'hidden';
             input.name = 'slug';
             input.value = customSlug.value;
             this.appendChild(input);
             select.disabled = true; // Use custom slug instead of select value
        }
    }
});
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
