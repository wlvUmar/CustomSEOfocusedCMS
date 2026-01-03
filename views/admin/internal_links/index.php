<?php
// NEW FILE: views/admin/internal_links/index.php
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="page-header">
    <h1><i data-feather="link"></i> Internal Links Manager</h1>
    <div class="btn-group">
        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/bulk-auto-insert" style="display: inline;">
            <button type="submit" class="btn btn-primary" 
                    onclick="return confirm('Auto-insert links for all pages? This will add up to 3 relevant links per page.')">
                <i data-feather="zap"></i> Bulk Auto-Insert
            </button>
        </form>
    </div>
</div>

<div class="info-banner" style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 16px; margin-bottom: 30px; border-radius: 4px;">
    <strong><i data-feather="info"></i> How It Works:</strong>
    The system analyzes your page content and suggests relevant internal links based on title matches and keyword overlap. 
    You can auto-insert links or manage them manually per page.
</div>

<!-- Statistics Overview -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="stat-card">
        <h3>Total Pages</h3>
        <p class="stat-number"><?= count($pages) ?></p>
    </div>
    
    <div class="stat-card">
        <h3>Total Suggestions</h3>
        <p class="stat-number"><?= count($groupedSuggestions) ?></p>
    </div>
    
    <div class="stat-card">
        <h3>Avg Suggestions/Page</h3>
        <p class="stat-number">
            <?php
            $totalSuggestions = 0;
            foreach ($groupedSuggestions as $group) {
                $totalSuggestions += count($group['suggestions']);
            }
            echo count($pages) > 0 ? round($totalSuggestions / count($pages), 1) : 0;
            ?>
        </p>
    </div>
</div>

<!-- Pages with Suggestions -->
<?php if (empty($groupedSuggestions)): ?>
    <div class="empty-state" style="background: white; padding: 60px; text-align: center; border-radius: 8px;">
        <h2>No Link Suggestions</h2>
        <p>Make sure your pages have titles and keywords set for better suggestions.</p>
    </div>
<?php else: ?>

<div style="display: grid; gap: 20px;">
    <?php foreach ($groupedSuggestions as $group): 
        $page = $group['page'];
        $suggestions = array_slice($group['suggestions'], 0, 5); // Show top 5
    ?>
    
    <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
            <div>
                <h3 style="margin: 0 0 5px 0; font-size: 1.2em;">
                    <?= e($page['title']) ?>
                </h3>
                <code style="background: #f3f4f6; padding: 3px 8px; border-radius: 4px; font-size: 0.85em;">
                    <?= e($page['slug']) ?>
                </code>
            </div>
            
            <div style="display: flex; gap: 8px;">
                <a href="<?= BASE_URL ?>/admin/internal-links/manage/<?= $page['id'] ?>" 
                   class="btn btn-sm btn-primary">
                    <i data-feather="settings"></i> Manage
                </a>
            </div>
        </div>
        
        <div style="background: #f9fafb; padding: 15px; border-radius: 6px; border-left: 3px solid #3b82f6;">
            <strong style="color: #374151; margin-bottom: 10px; display: block;">
                <i data-feather="target"></i> Top Suggested Links:
            </strong>
            
            <div style="display: grid; gap: 10px;">
                <?php foreach ($suggestions as $suggestion): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                    <div style="flex: 1;">
                        <strong style="color: #1f2937;"><?= e($suggestion['to_title']) ?></strong>
                        <span style="color: #6b7280; font-size: 0.85em; margin-left: 10px;">
                            (<?= e($suggestion['to_slug']) ?>)
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="background: #10b981; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.75em; font-weight: 600;">
                            Score: <?= $suggestion['relevance_score'] ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($group['suggestions']) > 5): ?>
            <p style="margin-top: 10px; color: #6b7280; font-size: 0.9em;">
                + <?= count($group['suggestions']) - 5 ?> more suggestions
            </p>
            <?php endif; ?>
        </div>
    </div>
    
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>