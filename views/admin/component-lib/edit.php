<?php
require BASE_PATH . '/views/admin/layout/header.php';
$slug = $row['slug'] ?? '';
$cat = $row['category'] ?? 'utilities';
?>
<div class="page-header pg-head">
    <div>
        <h1>Playground <code><?= e($slug) ?></code> <span id="dirty-dot" class="pg-dirty hidden" title="Unsaved changes">●</span></h1>
        <p class="subtitle"><?= e($row['title'] ?? '') ?> — <?= e($categoryLabels[$cat] ?? $cat) ?></p>
        <ol class="pg-steps">
            <li><strong>1</strong> Edit HTML + CSS</li>
            <li><strong>2</strong> Check live preview</li>
            <li><strong>3</strong> Save &amp; Rebuild</li>
        </ol>
    </div>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/components" class="btn btn-secondary"><i data-feather="arrow-left"></i> List</a>
        <button type="submit" form="component-form" class="btn btn-primary"><i data-feather="save"></i> Save &amp; Rebuild</button>
    </div>
</div>

<div id="draft-banner" class="pg-draft hidden">
    <span>Unsaved draft from <strong id="draft-ts"></strong> found in this browser.</span>
    <span class="pg-draft-actions">
        <button type="button" id="draft-restore" class="btn btn-sm btn-secondary">Restore draft</button>
        <button type="button" id="draft-discard" class="btn btn-sm">Discard</button>
    </span>
</div>

<section class="preview-panel pg-preview">
    <div class="preview-toolbar">
        <strong>Preview</strong>
        <span id="preview-status" class="muted small">loading…</span>
        <span id="preview-auto-badge" class="badge badge-warn hidden">auto demo</span>
        <span id="overflow-badge" class="badge badge-warn hidden">overflow</span>
        <span id="css-issues" class="muted small"></span>
        <span class="pg-spacer"></span>
        <button type="button" id="preview-open" class="btn btn-sm" title="Open preview in new tab">Open <i data-feather="external-link"></i></button>
        <button type="button" id="preview-copy" class="btn btn-sm" title="Copy demo HTML">Copy HTML</button>
    </div>
    <div class="preview-presets">
        <button type="button" class="btn btn-sm is-active" data-w="fluid">Fluid</button>
        <button type="button" class="btn btn-sm" data-w="375">375</button>
        <button type="button" class="btn btn-sm" data-w="768">768</button>
        <button type="button" class="btn btn-sm" data-w="1024">1024</button>
        <input type="range" id="preview-width" min="320" max="1440" value="1440" aria-label="Preview width" />
        <span id="preview-width-label" class="muted small">Fluid</span>
        <label class="muted small"><input type="checkbox" id="preview-section-wrap" checked /> .c-section</label>
        <span class="pg-bgseg" role="group" aria-label="Preview background">
            <button type="button" class="btn btn-sm is-active" data-bg="light">Light</button>
            <button type="button" class="btn btn-sm" data-bg="dark">Dark</button>
        </span>
    </div>
    <div id="mod-bar" class="pg-mods hidden">
        <span class="chip-bar-label">Variants:</span>
        <span id="mod-pills"></span>
        <span class="muted small">toggle <code>.<?= e($slug) ?>--*</code> live</span>
    </div>
    <div class="preview-frame-wrap">
        <div id="preview-stage" class="preview-stage">
            <iframe id="preview-frame" sandbox="allow-scripts" title="Component preview"></iframe>
        </div>
    </div>
    <div class="preview-hint muted small">Preview is live &amp; unsaved — <strong>Save &amp; Rebuild</strong> writes to <code>components.css</code> (.bak kept). Empty HTML shows an auto skeleton from CSS selectors. <kbd>Ctrl</kbd>+<kbd>S</kbd> saves, <kbd>Ctrl</kbd>+<kbd>Space</kbd> autocompletes.</div>
</section>

<form method="POST" action="<?= BASE_URL ?>/admin/components/save" id="component-form" class="admin-form pg-form">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= e($row['id'] ?? '') ?>" />

    <div class="pg-editors">
        <section class="pg-card">
            <div class="pg-card-head">
                <strong>HTML demo</strong>
                <span class="pg-card-actions">
                    <select id="snippet-select" class="filter-select" title="Insert starter snippet">
                        <option value="">+ Snippet…</option>
                    </select>
                    <button type="button" id="use-auto-demo" class="btn btn-sm" title="Copy auto skeleton into editor">Auto → editor</button>
                    <button type="button" id="html-format" class="btn btn-sm" title="Prettify HTML">Format</button>
                    <button type="button" id="html-copy" class="btn btn-sm" title="Copy HTML">Copy</button>
                    <button type="button" id="html-clear" class="btn btn-sm" title="Clear (falls back to auto demo)">Clear</button>
                </span>
            </div>
            <textarea name="html_demo" rows="14" id="html_demo" class="code-editor" spellcheck="false" placeholder='<div class="<?= e($slug) ?>">…</div>'><?= e($row['html_demo'] ?? '') ?></textarea>
            <div class="pg-card-foot">
                <span class="chip-bar-label">Parts:</span>
                <span id="part-chips" class="muted small">—</span>
            </div>
        </section>

        <section class="pg-card">
            <div class="pg-card-head">
                <strong>CSS body</strong>
                <span class="pg-card-actions">
                    <span id="css-size" class="muted small"></span>
                    <button type="button" id="css-format" class="btn btn-sm" title="Prettify CSS">Format</button>
                    <button type="button" id="css-copy" class="btn btn-sm" title="Copy CSS">Copy</button>
                </span>
            </div>
            <div class="token-chip-bar">
                <span class="chip-bar-label">Tokens:</span>
                <?php foreach ($tokens as $name => $val): ?>
                    <?php $isColor = (bool)preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($val)) || str_starts_with(trim($val), 'rgba(') || str_starts_with(trim($val), 'rgb('); ?>
                    <button type="button" class="token-chip token-chip--clickable" data-token="<?= e($name) ?>" title="<?= e($name . ': ' . $val) ?>"><?php if ($isColor): ?><span class="tok-dot" style="background:<?= e(trim($val)) ?>"></span><?php endif; ?><?= e($name) ?></button>
                <?php endforeach; ?>
            </div>
            <textarea id="css_body" name="css_body" rows="22" class="code-editor" spellcheck="false"><?= e($row['css_body'] ?? '') ?></textarea>
        </section>
    </div>

    <details class="pg-details">
        <summary>Meta — slug, category, title, status <span class="muted small">(rarely touched)</span></summary>
        <div class="pg-details-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Slug (c-*)</label>
                    <input type="text" name="slug" value="<?= e($row['slug'] ?? '') ?>" pattern="c-[a-z0-9\-]+" required />
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <?php foreach ($categories as $c): $label = $categoryLabels[$c] ?? $c; ?>
                            <option value="<?= e($c) ?>" <?= ($row['category'] ?? '')===$c?'selected':'' ?>><?= e($label) ?></option>
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
    </details>

    <details class="pg-details" <?= empty($revisions) ? '' : 'open' ?>>
        <summary>Revisions<?php if (!empty($revisions)): ?> (<?= count($revisions) ?>)<?php endif; ?> <span class="muted small">— Load previews without saving, Restore rewrites + rebuilds</span></summary>
        <div class="pg-details-body">
            <?php if (!empty($revisions)): ?>
            <table class="data-table small">
                <thead><tr><th>ID</th><th>Changed</th><th>Source</th><th>By</th><th>At</th><th class="text-center">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($revisions as $rev): ?>
                    <tr>
                        <td><?= e($rev['id']) ?></td>
                        <td class="muted small"><?= e($rev['changed_fields'] ?? '—') ?></td>
                        <td><?= e($rev['source']) ?></td>
                        <td class="muted small"><?= e($rev['created_by_name'] ?? '') ?></td>
                        <td class="muted small"><?= e($rev['created_at']) ?></td>
                        <td class="text-center pg-rev-actions">
                            <button type="button" class="btn btn-sm" data-rev-load="<?= e($rev['id']) ?>">Load</button>
                            <button type="button" class="btn btn-sm btn-secondary" data-rev-restore="<?= e($rev['id']) ?>">Restore</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="muted">No revisions yet — they appear after the first save.</p>
            <?php endif; ?>
        </div>
    </details>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save &amp; Rebuild</button>
        <span id="save-hint" class="muted small"></span>
    </div>
</form>

<form method="POST" action="<?= BASE_URL ?>/admin/components/restore" id="restore-form" class="hidden">
    <?= csrfField() ?>
    <input type="hidden" name="revision_id" id="restore-revision-id" value="" />
</form>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/lint.min.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closetag.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/selection/active-line.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/css-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/html-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/xml-hint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/lint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/lint/css-lint.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/comment/comment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.9/beautify.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.9/beautify-css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.14.9/beautify-html.min.js"></script>
<script>
window.COMPONENT_SLUG = <?= json_encode($slug) ?>;
window.COMPONENT_TOKENS = <?= json_encode(array_keys($tokens)) ?>;
window.COMPONENT_CATEGORY = <?= json_encode($cat) ?>;
</script>
<script src="<?= BASE_URL ?>/js/admin/component-lib.js?v=3"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
