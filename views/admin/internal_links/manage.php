<?php
// NEW FILE: views/admin/internal_links/manage.php
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="link"></i> Manage Internal Links: <?= e($page['title_ru']) ?></h1>
    <div class="btn-group">
        <a href="<?= BASE_URL ?>/admin/internal-links" class="btn btn-secondary">
            <i data-feather="arrow-left"></i> Back
        </a>
        <a href="<?= BASE_URL ?>/admin/pages/edit/<?= $page['id'] ?>" class="btn btn-secondary">
            <i data-feather="edit"></i> Edit Page
        </a>
    </div>
</div>

<!-- Language Tabs -->
<div class="tabs" style="margin-bottom: 30px;">
    <button type="button" class="tab-btn active" onclick="switchTab('ru')">Russian (RU)</button>
    <button type="button" class="tab-btn" onclick="switchTab('uz')">Uzbek (UZ)</button>
</div>

<!-- Russian Tab -->
<div id="tab-ru" class="tab-content active">
    <div style="display: grid; gap: 20px;">
        
        <!-- Auto-Insert Section -->
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <h2 style="margin-bottom: 15px;">
                <i data-feather="zap"></i> Auto-Insert Links (RU)
            </h2>
            
            <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/auto-insert" 
                  onsubmit="return confirm('Insert suggested links into RU content?')">
                <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                <input type="hidden" name="language" value="ru">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Maximum Links to Insert:</label>
                        <select name="max_links" class="btn" style="width: 200px;">
                            <option value="3">3 links</option>
                            <option value="5">5 links</option>
                            <option value="7">7 links</option>
                            <option value="10">10 links</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="plus-circle"></i> Auto-Insert Links
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Existing Links -->
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">
                    <i data-feather="list"></i> Existing Links (<?= count($existingLinksRu) ?>)
                </h2>
                
                <?php if (!empty($existingLinksRu)): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/remove-links" 
                      style="display: inline;"
                      onsubmit="return confirm('Remove ALL internal links from RU content?')">
                    <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                    <input type="hidden" name="language" value="ru">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i data-feather="trash-2"></i> Remove All
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <?php if (empty($existingLinksRu)): ?>
                <p style="color: #6b7280; font-style: italic;">No internal links found in RU content.</p>
            <?php else: ?>
                <div style="display: grid; gap: 10px;">
                    <?php foreach ($existingLinksRu as $link): ?>
                    <div style="background: #f9fafb; padding: 12px; border-radius: 6px; border-left: 3px solid #10b981;">
                        <div style="margin-bottom: 5px;">
                            <strong style="color: #1f2937;"><?= e($link['anchor_text']) ?></strong>
                        </div>
                        <code style="font-size: 0.85em; color: #6b7280;">
                            → <?= e($link['href']) ?>
                        </code>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Suggestions -->
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <h2 style="margin-bottom: 15px;">
                <i data-feather="target"></i> Suggested Links (<?= count($suggestions) ?>)
            </h2>
            
            <?php if (empty($suggestions)): ?>
                <p style="color: #6b7280; font-style: italic;">
                    No suggestions available. Make sure other pages have relevant keywords.
                </p>
            <?php else: ?>
                <div style="display: grid; gap: 10px;">
                    <?php foreach ($suggestions as $suggestion): ?>
                    <div style="background: #f9fafb; padding: 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <strong style="color: #1f2937;"><?= e($suggestion['to_title']) ?></strong>
                            <br>
                            <span style="font-size: 0.85em; color: #6b7280;">
                                Anchor: "<?= e($suggestion['anchor_text_ru']) ?>" 
                                → <code><?= e($suggestion['to_slug']) ?></code>
                            </span>
                        </div>
                        <div>
                            <span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600;">
                                Score: <?= $suggestion['relevance_score'] ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- Uzbek Tab -->
<div id="tab-uz" class="tab-content">
    <div style="display: grid; gap: 20px;">
        
        <!-- Auto-Insert Section -->
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <h2 style="margin-bottom: 15px;">
                <i data-feather="zap"></i> Auto-Insert Links (UZ)
            </h2>
            
            <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/auto-insert" 
                  onsubmit="return confirm('Insert suggested links into UZ content?')">
                <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                <input type="hidden" name="language" value="uz">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Maximum Links to Insert:</label>
                        <select name="max_links" class="btn" style="width: 200px;">
                            <option value="3">3 links</option>
                            <option value="5">5 links</option>
                            <option value="7">7 links</option>
                            <option value="10">10 links</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="plus-circle"></i> Auto-Insert Links
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Existing Links -->
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;">
                    <i data-feather="list"></i> Existing Links (<?= count($existingLinksUz) ?>)
                </h2>
                
                <?php if (!empty($existingLinksUz)): ?>
                <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/remove-links" 
                      style="display: inline;"
                      onsubmit="return confirm('Remove ALL internal links from UZ content?')">
                    <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                    <input type="hidden" name="language" value="uz">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i data-feather="trash-2"></i> Remove All
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <?php if (empty($existingLinksUz)): ?>
                <p style="color: #6b7280; font-style: italic;">No internal links found in UZ content.</p>
            <?php else: ?>
                <div style="display: grid; gap: 10px;">
                    <?php foreach ($existingLinksUz as $link): ?>
                    <div style="background: #f9fafb; padding: 12px; border-radius: 6px; border-left: 3px solid #10b981;">
                        <div style="margin-bottom: 5px;">
                            <strong style="color: #1f2937;"><?= e($link['anchor_text']) ?></strong>
                        </div>
                        <code style="font-size: 0.85em; color: #6b7280;">
                            → <?= e($link['href']) ?>
                        </code>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Suggestions -->
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <h2 style="margin-bottom: 15px;">
                <i data-feather="target"></i> Suggested Links (<?= count($suggestions) ?>)
            </h2>
            
            <?php if (empty($suggestions)): ?>
                <p style="color: #6b7280; font-style: italic;">
                    No suggestions available. Make sure other pages have relevant keywords.
                </p>
            <?php else: ?>
                <div style="display: grid; gap: 10px;">
                    <?php foreach ($suggestions as $suggestion): ?>
                    <div style="background: #f9fafb; padding: 12px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <strong style="color: #1f2937;"><?= e($suggestion['to_title']) ?></strong>
                            <br>
                            <span style="font-size: 0.85em; color: #6b7280;">
                                Anchor: "<?= e($suggestion['anchor_text_uz']) ?>" 
                                → <code><?= e($suggestion['to_slug']) ?></code>
                            </span>
                        </div>
                        <div>
                            <span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600;">
                                Score: <?= $suggestion['relevance_score'] ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<script>
function switchTab(lang) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    // Show selected tab
    document.getElementById('tab-' + lang).classList.add('active');
    event.target.classList.add('active');
}
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>