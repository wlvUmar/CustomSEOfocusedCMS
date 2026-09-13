<?php
require BASE_PATH . '/views/admin/layout/header.php';
$isNew = empty($row['id']);
$slug = $row['slug'] ?? '';
?>
<div class="page-header">
    <div>
        <h1>Edit component <code><?= e($slug) ?></code></h1>
        <p class="subtitle"><?= e($row['title'] ?? '') ?> — <?= e($categoryLabels[$row['category'] ?? ''] ?? ($row['category'] ?? '')) ?></p>
    </div>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/components" class="btn btn-secondary"><i data-feather="arrow-left"></i> Back to list</a>
    </div>
</div>

<div class="preview-panel">
    <div class="preview-toolbar">
        <strong>Live preview</strong>
        <span id="preview-status" class="muted small">debounced 500ms</span>
        <span id="preview-auto-badge" class="badge badge-warn hidden">auto demo</span>
        <span id="overflow-badge" class="badge badge-warn hidden">overflow</span>
    </div>
    <div class="preview-presets">
        <button type="button" class="btn btn-sm is-active" data-w="fluid">Fluid</button>
        <button type="button" class="btn btn-sm" data-w="375">375</button>
        <button type="button" class="btn btn-sm" data-w="768">768</button>
        <button type="button" class="btn btn-sm" data-w="1024">1024</button>
        <button type="button" class="btn btn-sm" data-w="1440">1440</button>
        <input type="range" id="preview-width" min="320" max="1440" value="1440" />
        <span id="preview-width-label" class="muted small">Fluid</span>
        <label class="muted small"><input type="checkbox" id="preview-section-wrap" checked /> inside .c-section</label>
    </div>
    <div id="preview-frame-wrap" class="preview-frame-wrap">
        <div id="preview-stage" class="preview-stage">
            <iframe id="preview-frame" sandbox="allow-scripts" title="Component preview"></iframe>
        </div>
    </div>
    <div class="preview-hint muted small">Preview is live &amp; unsaved — Save &amp; Rebuild writes to <code>components.css</code>. Backup kept as <code>.bak</code>. When HTML demo is empty an auto skeleton from CSS selectors is shown.</div>
</div>

<form method="POST" action="<?= BASE_URL ?>/admin/components/save" id="component-form" class="admin-form">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= e($row['id'] ?? '') ?>" />

    <div class="tab-bar" role="tablist">
        <button type="button" class="tab-btn is-active" data-tab="css" role="tab" aria-selected="true">CSS body</button>
        <button type="button" class="tab-btn" data-tab="html" role="tab" aria-selected="false">HTML demo</button>
        <button type="button" class="tab-btn" data-tab="meta" role="tab" aria-selected="false">Meta</button>
        <button type="button" class="tab-btn" data-tab="revisions" role="tab" aria-selected="false">Revisions<?php if (!empty($revisions)): ?> (<?= count($revisions) ?>)<?php endif; ?></button>
    </div>

    <div class="tab-panes">
        <div class="tab-pane is-active" data-pane="css" role="tabpanel">
            <div class="token-chip-bar">
                <span class="chip-bar-label">Tokens:</span>
                <?php foreach ($tokens as $name => $val): ?>
                    <button type="button" class="token-chip token-chip--clickable" data-token="<?= e($name) ?>" title="<?= e($val) ?>"><?= e($name) ?></button>
                <?php endforeach; ?>
                <span class="muted small">click to insert var(--x)</span>
            </div>
            <div class="form-group">
                <label>CSS body <span class="muted small">(sanitized — @import/javascript:/expression blocked, max 200KB)</span></label>
                <textarea id="css_body" name="css_body" rows="18" class="code-editor" spellcheck="false"><?= e($row['css_body'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="tab-pane" data-pane="html" role="tabpanel">
            <div class="form-group">
                <label>HTML demo <span class="muted small">(optional — when empty an auto skeleton from CSS BEM children is shown)</span></label>
                <textarea name="html_demo" rows="10" id="html_demo" class="code-editor" spellcheck="false" placeholder='<div class=&quot;c-hero-split&quot;>…</div>'><?= e($row['html_demo'] ?? '') ?></textarea>
                <div class="html-demo-actions">
                    <button type="button" id="use-auto-demo" class="btn btn-secondary btn-sm">Use auto demo as starting point</button>
                    <span id="auto-demo-hint" class="muted small hidden">Auto demo copied — edit &amp; save to persist.</span>
                </div>
                <p class="muted small" style="margin-top:6px">Leave empty for instant preview. The skeleton is derived from <code>.<?= e($slug) ?>__*</code> selectors in CSS body plus a category fallback.</p>
            </div>
        </div>

        <div class="tab-pane" data-pane="meta" role="tabpanel">
            <div class="form-row">
                <div class="form-group">
                    <label>Slug (c-*)</label>
                    <input type="text" name="slug" value="<?= e($row['slug'] ?? '') ?>" pattern="c-[a-z0-9\-]+" required />
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <?php foreach ($categories as $cat): $label = $categoryLabels[$cat] ?? $cat; ?>
                            <option value="<?= e($cat) ?>" <?= ($row['category'] ?? '')===$cat?'selected':'' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= e($row['title'] ?? '') ?>" required />
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= ($row['status'] ?? 'active')==='active'?'selected':'' ?>>Active</option>
                        <option value="deprecated" <?= ($row['status'] ?? '')==='deprecated'?'selected':'' ?>>Deprecated</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2"><?= e($row['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Sort</label>
                    <input type="number" name="sort" value="<?= e($row['sort'] ?? 0) ?>" />
                </div>
                <div class="form-group">
                    <label>Variants (JSON)</label>
                    <input type="text" name="variants" value="<?= e($row['variants'] ?? '') ?>" placeholder='{"has_media":true}' />
                </div>
            </div>
        </div>

        <div class="tab-pane" data-pane="revisions" role="tabpanel">
            <?php if (!empty($revisions)): ?>
            <div class="revisions-panel">
                <h3>Recent revisions (last 20)</h3>
                <table class="data-table small">
                    <thead><tr><th>ID</th><th>Changed</th><th>Source</th><th>At</th></tr></thead>
                    <tbody>
                    <?php foreach ($revisions as $rev): ?>
                        <tr><td><?= e($rev['id']) ?></td><td class="muted small"><?= e($rev['changed_fields'] ?? '—') ?></td><td><?= e($rev['source']) ?></td><td class="muted small"><?= e($rev['created_at']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="muted">No revisions yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save & Rebuild</button>
        <span id="save-hint" class="muted small"></span>
    </div>
</form>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/lint.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/lint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/css-lint.min.js"></script>
<script>window.COMPONENT_SLUG = <?= json_encode($slug) ?>; window.COMPONENT_TOKENS = <?= json_encode(array_keys($tokens)) ?>;</script>
<script src="<?= BASE_URL ?>/js/admin/component-lib.js?v=2"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
