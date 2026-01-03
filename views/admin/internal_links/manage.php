<?php
$pageName = 'internal_links/manage';

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
<div class="tabs">
    <button class="tab-btn active" onclick="switchTab('ru')">
        <i data-feather="globe"></i> Russian (RU)
    </button>
    <button class="tab-btn" onclick="switchTab('uz')">
        <i data-feather="globe"></i> Uzbek (UZ)
    </button>
</div>

<!-- RU TAB -->
<div id="tab-ru" class="tab-content active">

    <!-- Auto insert -->
    <div class="panel">
        <h2 class="panel-title">
            <i data-feather="zap"></i> Auto-Insert Links (RU)
        </h2>

        <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/auto-insert"
              onsubmit="return confirm('Insert suggested links into RU content?')">
            <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
            <input type="hidden" name="language" value="ru">

            <div class="form-row">
                <div class="form-group">
                    <label>Maximum Links to Insert</label>
                    <select name="max_links" class="btn full-width">
                        <option value="3">3 links</option>
                        <option value="5">5 links</option>
                        <option value="7">7 links</option>
                        <option value="10">10 links</option>
                    </select>
                </div>

                <button class="btn btn-primary">
                    <i data-feather="plus-circle"></i> Auto-Insert Links
                </button>
            </div>
        </form>
    </div>

    <!-- Existing links -->
    <div class="panel">
        <div class="panel-header">
            <h2><i data-feather="link"></i> Existing Links (<?= count($existingLinksRu) ?>)</h2>

            <?php if ($existingLinksRu): ?>
            <form method="POST" action="<?= BASE_URL ?>/admin/internal-links/remove-links"
                  onsubmit="return confirm('Remove ALL internal links from RU content?')">
                <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                <input type="hidden" name="language" value="ru">
                <button class="btn btn-sm btn-danger">
                    <i data-feather="trash-2"></i> Remove All
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!$existingLinksRu): ?>
            <p class="empty-note"><i data-feather="info"></i> No internal links found.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Anchor Text</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingLinksRu as $link): ?>
                    <tr>
                        <td><strong><?= e($link['anchor_text']) ?></strong></td>
                        <td><code class="muted-code"><?= e($link['href']) ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Suggestions -->
    <div class="panel">
        <h2 class="panel-title">
            <i data-feather="lightbulb"></i> Suggested Links (<?= count($suggestions) ?>)
        </h2>

        <?php if (!$suggestions): ?>
            <p class="empty-note"><i data-feather="info"></i> No suggestions available.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Target Page</th>
                        <th>Anchor Text</th>
                        <th>Relevance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suggestions as $s): ?>
                    <tr>
                        <td>
                            <strong><?= e($s['to_title']) ?></strong><br>
                            <code class="muted-code"><?= e($s['to_slug']) ?></code>
                        </td>
                        <td><?= e($s['anchor_text_ru']) ?></td>
                        <td>
                            <span class="pill success">
                                <i data-feather="star"></i> <?= $s['relevance_score'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- UZ TAB -->
<div id="tab-uz" class="tab-content">
    <!-- structure identical to RU, only language + anchor field differs -->
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
