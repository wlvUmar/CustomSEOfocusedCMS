<?php
// FIXED: views/admin/internal_links/manage.php
// Cleaner list-based UI instead of grid cards

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
    <button type="button" class="tab-btn active" onclick="switchTab('ru')">
        <i data-feather="globe"></i> Russian (RU)
    </button>
    <button type="button" class="tab-btn" onclick="switchTab('uz')">
        <i data-feather="globe"></i> Uzbek (UZ)
    </button>
</div>

<!-- Russian Tab -->
<div id="tab-ru" class="tab-content active">
    
    <!-- Auto-Insert Section -->
    <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px;">
        <h2 style="margin-bottom: 15px;">
            <i data-feather="zap"></i> Auto-Insert Links (RU)
        </h2>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/auto-insert" 
              onsubmit="return confirm('Insert suggested links into RU content?')">
            <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
            <input type="hidden" name="language" value="ru">
            
            <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                    <label>Maximum Links to Insert:</label>
                    <select name="max_links" class="btn" style="width: 100%;">
                        <option value="3">3 links</option>
                        <option value="5">5 links</option>
                        <option value="7">7 links</option>
                        <option value="10">10 links</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i data-feather="plus-circle"></i> Auto-Insert Links
                </button>
            </div>
        </form>
    </div>
    
    <!-- Existing Links -->
    <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0;">
                <i data-feather="link"></i> Existing Links (<?= count($existingLinksRu) ?>)
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
            <p style="color: #6b7280; font-style: italic; padding: 20px; text-align: center;">
                <i data-feather="info"></i> No internal links found in RU content.
            </p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i data-feather="type"></i> Anchor Text</th>
                        <th><i data-feather="link"></i> URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingLinksRu as $link): ?>
                    <tr>
                        <td><strong><?= e($link['anchor_text']) ?></strong></td>
                        <td>
                            <code style="font-size: 0.9em; color: #6b7280;">
                                <?= e($link['href']) ?>
                            </code>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Suggestions -->
    <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <h2 style="margin-bottom: 15px;">
            <i data-feather="lightbulb"></i> Suggested Links (<?= count($suggestions) ?>)
        </h2>
        
        <?php if (empty($suggestions)): ?>
            <p style="color: #6b7280; font-style: italic; padding: 20px; text-align: center;">
                <i data-feather="info"></i> No suggestions available. Make sure other pages have relevant keywords.
            </p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i data-feather="file-text"></i> Target Page</th>
                        <th><i data-feather="type"></i> Anchor Text</th>
                        <th><i data-feather="trending-up"></i> Relevance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suggestions as $suggestion): ?>
                    <tr>
                        <td>
                            <strong><?= e($suggestion['to_title']) ?></strong>
                            <br>
                            <code style="font-size: 0.85em; color: #6b7280;">
                                <?= e($suggestion['to_slug']) ?>
                            </code>
                        </td>
                        <td><?= e($suggestion['anchor_text_ru']) ?></td>
                        <td>
                            <span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600;">
                                <i data-feather="star"></i> <?= $suggestion['relevance_score'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
</div>

<!-- Uzbek Tab -->
<div id="tab-uz" class="tab-content">
    
    <!-- Auto-Insert Section -->
    <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px;">
        <h2 style="margin-bottom: 15px;">
            <i data-feather="zap"></i> Auto-Insert Links (UZ)
        </h2>
        
        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/auto-insert" 
              onsubmit="return confirm('Insert suggested links into UZ content?')">
            <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
            <input type="hidden" name="language" value="uz">
            
            <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                    <label>Maximum Links to Insert:</label>
                    <select name="max_links" class="btn" style="width: 100%;">
                        <option value="3">3 links</option>
                        <option value="5">5 links</option>
                        <option value="7">7 links</option>
                        <option value="10">10 links</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i data-feather="plus-circle"></i> Auto-Insert Links
                </button>
            </div>
        </form>
    </div>
    
    <!-- Existing Links -->
    <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0;">
                <i data-feather="link"></i> Existing Links (<?= count($existingLinksUz) ?>)
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
            <p style="color: #6b7280; font-style: italic; padding: 20px; text-align: center;">
                <i data-feather="info"></i> No internal links found in UZ content.
            </p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i data-feather="type"></i> Anchor Text</th>
                        <th><i data-feather="link"></i> URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingLinksUz as $link): ?>
                    <tr>
                        <td><strong><?= e($link['anchor_text']) ?></strong></td>
                        <td>
                            <code style="font-size: 0.9em; color: #6b7280;">
                                <?= e($link['href']) ?>
                            </code>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Suggestions -->
    <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <h2 style="margin-bottom: 15px;">
            <i data-feather="lightbulb"></i> Suggested Links (<?= count($suggestions) ?>)
        </h2>
        
        <?php if (empty($suggestions)): ?>
            <p style="color: #6b7280; font-style: italic; padding: 20px; text-align: center;">
                <i data-feather="info"></i> No suggestions available. Make sure other pages have relevant keywords.
            </p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i data-feather="file-text"></i> Target Page</th>
                        <th><i data-feather="type"></i> Anchor Text</th>
                        <th><i data-feather="trending-up"></i> Relevance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suggestions as $suggestion): ?>
                    <tr>
                        <td>
                            <strong><?= e($suggestion['to_title']) ?></strong>
                            <br>
                            <code style="font-size: 0.85em; color: #6b7280;">
                                <?= e($suggestion['to_slug']) ?>
                            </code>
                        </td>
                        <td><?= e($suggestion['anchor_text_uz']) ?></td>
                        <td>
                            <span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600;">
                                <i data-feather="star"></i> <?= $suggestion['relevance_score'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
</div>

<script>
function switchTab(lang) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById('tab-' + lang).classList.add('active');
    event.target.closest('.tab-btn').classList.add('active');
    
    feather.replace();
}
</script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>