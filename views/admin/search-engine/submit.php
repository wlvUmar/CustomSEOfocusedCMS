<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<link rel="stylesheet" href="<?= BASE_URL ?>/css/admin/search-engine.css">

<div class="page-header">
    <h1>
        <i data-feather="send"></i>
        Submit Pages to Search Engines
    </h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/search-engine" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Submit Options Grid -->
<div class="submit-options">
    <!-- Single Page Submission -->
    <div class="submit-card">
        <h2><i data-feather="file-text"></i> Submit Single Page</h2>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/search-engine/submit-page">
            <?= csrfField() ?>
            
            <div class="form-group">
                <label>Select Page</label>
                <select name="slug" class="form-control" required>
                    <option value="">-- Choose a page --</option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?= e($page['slug']) ?>">
                            <?= e($page['title_ru']) ?> (<?= e($page['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Select Search Engines</label>
                <?php if (empty($enabledEngines)): ?>
                    <div class="alert alert-warning">
                        <i data-feather="alert-triangle"></i>
                        No search engines are enabled! <a href="<?= BASE_URL ?>/admin/search-engine/config">Enable engines in config</a> first.
                    </div>
                <?php endif; ?>
                <div class="checkbox-group">
                    <label class="<?= in_array('bing', $enabledEngines) ? '' : 'disabled-engine' ?>">
                        <input type="checkbox" name="engines[]" value="bing" 
                               <?= in_array('bing', $enabledEngines) ? 'checked' : 'disabled' ?>>
                        <i data-feather="globe"></i> Bing (Recommended)
                        <?php if (!in_array('bing', $enabledEngines)): ?>
                            <small class="text-muted">(not enabled)</small>
                        <?php endif; ?>
                    </label>
                    <label class="<?= in_array('yandex', $enabledEngines) ? '' : 'disabled-engine' ?>">
                        <input type="checkbox" name="engines[]" value="yandex"
                               <?= !in_array('yandex', $enabledEngines) ? 'disabled' : '' ?>>
                        <i data-feather="compass"></i> Yandex
                        <?php if (!in_array('yandex', $enabledEngines)): ?>
                            <small class="text-muted">(not enabled)</small>
                        <?php endif; ?>
                    </label>
                    <label class="<?= in_array('google', $enabledEngines) ? '' : 'disabled-engine' ?>">
                        <input type="checkbox" name="engines[]" value="google"
                               <?= !in_array('google', $enabledEngines) ? 'disabled' : '' ?>>
                        <i data-feather="chrome"></i> Google (Sitemap)
                        <?php if (!in_array('google', $enabledEngines)): ?>
                            <small class="text-muted">(not enabled)</small>
                        <?php endif; ?>
                    </label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i data-feather="send"></i> Submit Page
            </button>
        </form>
    </div>
    
    <!-- Batch Submission -->
    <div class="submit-card">
        <h2><i data-feather="layers"></i> Batch Submit Multiple Pages</h2>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/search-engine/batch-submit" id="batch-submit-form">
            <?= csrfField() ?>
            
            <div class="form-group">
                <label>Select Engine</label>
                <?php if (empty($enabledEngines)): ?>
                    <div class="alert alert-warning">
                        <i data-feather="alert-triangle"></i>
                        No engines enabled! <a href="<?= BASE_URL ?>/admin/search-engine/config">Enable in config</a>
                    </div>
                <?php endif; ?>
                <select name="engine" class="form-control" required <?= empty($enabledEngines) ? 'disabled' : '' ?>>
                    <option value="bing" <?= !in_array('bing', $enabledEngines) ? 'disabled' : '' ?>>
                        Bing (Recommended) <?= !in_array('bing', $enabledEngines) ? '- NOT ENABLED' : '' ?>
                    </option>
                    <option value="yandex" <?= !in_array('yandex', $enabledEngines) ? 'disabled' : '' ?>>
                        Yandex <?= !in_array('yandex', $enabledEngines) ? '- NOT ENABLED' : '' ?>
                    </option>
                    <option value="google" <?= !in_array('google', $enabledEngines) ? 'disabled' : '' ?>>
                        Google <?= !in_array('google', $enabledEngines) ? '- NOT ENABLED' : '' ?>
                    </option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Select Pages</label>
                <div class="checklist-actions">
                    <button type="button" class="btn btn-sm btn-secondary" id="select-all">
                        Select All
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" id="select-none">
                        Select None
                    </button>
                </div>
                
                <div class="page-checklist">
                    <?php if (empty($pages)): ?>
                        <p class="text-muted">No published pages available</p>
                    <?php else: ?>
                        <?php foreach ($pages as $page): ?>
                        <label class="page-checkbox">
                            <input type="checkbox" name="slugs[]" value="<?= e($page['slug']) ?>">
                            <div>
                                <span><?= e($page['title_ru']) ?></span>
                                <small><?= e($page['slug']) ?></small>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i data-feather="send"></i> Submit Selected Pages
            </button>
        </form>
        
        <div id="batch-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%"></div>
            </div>
            <p id="progress-text">Submitting pages...</p>
        </div>
    </div>
</div>

<!-- Unsubmitted Pages Section -->
<?php if (!empty($unsubmitted)): ?>
<div class="recent-activity-section">
    <h2><i data-feather="alert-circle"></i> Unsubmitted Pages (<?= count($unsubmitted) ?>)</h2>
    
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th>Slug</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($unsubmitted as $page): ?>
                <tr>
                    <td>
                        <strong><?= e($page['title_ru']) ?></strong>
                    </td>
                    <td>
                        <code><?= e($page['slug']) ?></code>
                    </td>
                    <td>
                        <?= date('M d, Y', strtotime($page['created_at'])) ?>
                    </td>
                    <td>
                        <form method="POST" action="<?= BASE_URL ?>/admin/search-engine/submit-page" style="display: inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="slug" value="<?= e($page['slug']) ?>">
                            <input type="hidden" name="engines[]" value="bing">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i data-feather="send"></i> Submit to Bing
                            </button>
                        </form>
                        <?php if (isset($page['id'])): ?>
                        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" class="btn btn-sm btn-secondary">
                            <i data-feather="edit"></i> Edit
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <form method="POST" action="<?= BASE_URL ?>/admin/search-engine/submit-unsubmitted" style="margin-top: 20px;">
        <?= csrfField() ?>
        <input type="hidden" name="engine" value="bing">
        <button type="submit" class="btn btn-warning btn-lg" onclick="return confirm('Submit all <?= count($unsubmitted) ?> unsubmitted pages to Bing?')">
            <i data-feather="send"></i> Submit All <?= count($unsubmitted) ?> Pages to Bing
        </button>
    </form>
</div>
<?php endif; ?>

<script src="<?= BASE_URL ?>/js/admin/search-engine.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
