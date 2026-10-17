<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>
<?php
$lang = $_SESSION['lang'] ?? 'ru';
$filter = $filter ?? 'all';
$currentPage = $currentPage ?? null;
$attachSlot = $attachSlot ?? ($_GET['attach_slot'] ?? null);
$attachPageId = $attachPageId ?? ($_GET['page_id'] ?? null);
$allPages = $allPages ?? [];
$filterKeys = ['all', 'used', 'unused'];
$filterLabels = [
    'all' => $lang === 'uz' ? 'Barcha fayllar' : 'Все файлы',
    'used' => $lang === 'uz' ? 'Ishlatilmoqda' : 'Используются',
    'unused' => $lang === 'uz' ? 'Ishlatilmayapti' : 'Не используются',
    'attached' => $lang === 'uz' ? 'Sahifaga biriktirilgan' : 'Прикреплены к странице',
];
$filterLinks = [];
foreach (array_merge($filterKeys, ['attached']) as $f) {
    $q = [];
    if ($f !== 'all') $q['filter'] = $f;
    if ($attachPageId) $q['page_id'] = $attachPageId;
    if ($attachSlot) $q['attach_slot'] = $attachSlot;
    $filterLinks[$f] = '?' . http_build_query($q);
}
$csrfRaw = generateCSRFToken();
$csrfForJs = htmlspecialchars($csrfRaw, ENT_QUOTES);
$mediaBoot = ['csrf' => $csrfRaw, 'filter' => $filter, 'attachPageId' => $attachPageId, 'attachSlot' => $attachSlot];
?>

<div class="page-header">
    <h1><i data-feather="image"></i> <?= $lang === 'uz' ? 'Media kutubxonasi' : 'Медиатека' ?></h1>
    <div class="page-actions">
        <button data-action="open-upload" class="btn btn-primary">
            <i data-feather="upload"></i> <?= $lang === 'uz' ? 'Yuklash' : 'Загрузить' ?>
        </button>
        <button id="regen-variants-btn" data-action="regen-variants" class="btn btn-secondary">
            <i data-feather="refresh-ccw"></i> <?= $lang === 'uz' ? 'Variantlarni qayta yaratish' : 'Перегенерировать варианты' ?>
        </button>
    </div>
</div>
<!-- Attach context banner (when opened from page editor) -->
<?php if ($attachPageId): ?>
<div class="c-attach-banner">
    <span><?= $lang === 'uz' ? 'Sahifa uchun biriktirilmoqda' : 'Привязка для страницы' ?> #<?= (int)$attachPageId ?> · <code>{{media.<?= e($attachSlot ?: 'content') ?>}}</code><?= $currentPage ? ' — ' . e($currentPage['slug'] ?? $currentPage['title_ru'] ?? '') : '' ?></span>
    <span class="c-attach-note"><?= $lang === 'uz' ? '— quyidagi istalgan rasmni tanlang. Bir klikda biriktirish uchun «Tezkor biriktirish»ni bosing.' : '— выберите любое изображение ниже. Для привязки в один клик нажмите «Быстро прикрепить».' ?></span>
    <a href="<?= BASE_URL ?>/admin/pages/edit/<?= (int)$attachPageId ?>" class="btn btn-sm c-push-right">← <?= $lang === 'uz' ? 'Muharrirga qaytish' : 'Назад к редактору' ?></a>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="media-toolbar">
    <div class="filter-tabs" id="media-filter-tabs">
        <?php foreach ($filterKeys as $f): ?>
        <a href="<?= e($filterLinks[$f]) ?>" data-media-filter="<?= $f ?>" class="filter-tab <?= $filter === $f ? 'active' : '' ?>">
            <?= e($filterLabels[$f]) ?>
        </a>
        <?php endforeach; ?>
        <?php if ($attachPageId): ?>
        <a href="<?= e($filterLinks['attached']) ?>" data-media-filter="attached" class="filter-tab <?= $filter === 'attached' ? 'active' : '' ?>">
            <?= e($filterLabels['attached']) ?>
        </a>
        <?php endif; ?>
    </div>

    <div class="search-box">
        <input type="text" id="search" placeholder="<?= $lang === 'uz' ? 'Fayl nomi bo‘yicha qidiruv...' : 'Поиск по имени файла...' ?>">
    </div>
</div>

<!-- Upload Form (Hidden) -->
<form id="upload-form" class="c-hidden">
    <input type="hidden" id="csrf-token" value="<?= $csrfForJs ?>">
    <input type="file" id="upload-input" accept="image/*" multiple>
</form>

<!-- Media Grid -->
<div class="media-grid" id="media-grid">
    <?php if (empty($media)): ?>
        <div class="empty-state">
            <i data-feather="image" class="c-empty-icon"></i>
            <h3><?= $lang === 'uz' ? 'Fayllar topilmadi' : 'Файлы не найдены' ?></h3>
            <p><?= $lang === 'uz' ? 'Boshlash uchun birinchi rasmni yuklang' : 'Загрузите первое изображение' ?></p>
            <button data-action="open-upload" class="btn btn-primary">
                <?= $lang === 'uz' ? 'Yuklash' : 'Загрузить' ?>
            </button>
        </div>
    <?php else: ?>
        <?php foreach ($media as $item): ?>
            <?php 
            // Get media ID - try multiple sources
            $mediaId = 0;
            if (isset($item['media_id']) && $item['media_id']) {
                $mediaId = (int)$item['media_id'];
            } else if (isset($item['id']) && $item['id']) {
                $mediaId = (int)$item['id'];
            }
            ?>
            <?php
                $thumbUrl = $item['thumb_url'] ?? (UPLOAD_URL . ($item['filename'] ?? ''));
                $thumbW = $item['thumb_width'] ?? null;
                $thumbH = $item['thumb_height'] ?? null;
                $isAttached = !empty($item['is_attached_to_page']);
                ?>
            <div class="media-card<?= $isAttached ? ' c-attached' : '' ?>" data-id="<?= $mediaId ?>" data-name="<?= e($item['original_name']) ?>">
                <div class="media-thumbnail c-thumb-wrap">
                    <img src="<?= e($thumbUrl) ?>" alt="<?= e($item['original_name']) ?>" loading="lazy" decoding="async" <?= $thumbW ? 'width="'.(int)$thumbW.'"' : '' ?> <?= $thumbH ? 'height="'.(int)$thumbH.'"' : '' ?> class="c-thumb-img">
                    <?php if ($isAttached): ?>
                    <span class="c-attached-badge">✓ <?= $lang === 'uz' ? 'biriktirilgan' : 'прикреплено' ?><?= $item['attached_section'] ? ' ('.e($item['attached_section']).')' : '' ?></span>
                    <?php endif; ?>
                </div>

                <div class="media-details">
                    <div class="media-id">ID: <?= $mediaId ?></div>
                    <div class="media-filename" title="<?= e($item['original_name']) ?>">
                        <?= e(strlen($item['original_name']) > 30 ? substr($item['original_name'], 0, 27) . '...' : $item['original_name']) ?>
                    </div>
                    <div class="media-meta">
                        <?= number_format($item['file_size'] / 1024, 1) ?> KB
                        <?php if (isset($item['usage_count'])): ?>
                            • <span class="usage-badge <?= $item['usage_count'] > 0 ? 'used' : 'unused' ?>">
                                <?= $item['usage_count'] ?> page<?= $item['usage_count'] != 1 ? 's' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['pages'])): ?>
                        <div class="media-usage">
                            <div class="media-usage-title">Attached to:</div>
                            <?php foreach ($item['pages'] as $pageItem): ?>
                                <?php
                                $slug = $pageItem['slug'] ?? ($pageItem['title_ru'] ?? '');
                                $section = $pageItem['section'] ?? '';
                                $pageId = $pageItem['id'] ?? ($pageItem['page_id'] ?? null);
                                ?>
                                <div class="media-usage-item">
                                    <span class="media-usage-label">
                                        <?= e($slug) ?><?= $section ? ' (' . e($section) . ')' : '' ?>
                                    </span>
                                    <?php if (!empty($pageId)): ?>
                                        <button
                                            type="button"
                                            class="media-detach"
                                            data-media-id="<?= $mediaId ?>"
                                            data-page-id="<?= (int)$pageId ?>"
                                            data-section="<?= e($section) ?>"
                                        >
                                            Detach
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="media-actions">
                    <?php if (!empty($attachPageId)): ?>
                    <button class="btn btn-sm btn-primary" data-action="quick-attach" data-media-id="<?= $mediaId ?>" title="<?= $lang === 'uz' ? 'Biriktirish' : 'Прикрепить' ?> {{media.<?= e($attachSlot ?: 'content') ?>}} #<?= (int)$attachPageId ?>">
                        <i data-feather="zap"></i> <?= $lang === 'uz' ? 'Tezkor biriktirish' : 'Быстро прикрепить' ?>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-secondary" data-action="show-attach" data-media-id="<?= $mediaId ?>" title="<?= $lang === 'uz' ? 'Sahifaga biriktirish' : 'Прикрепить к странице' ?>">
                        <i data-feather="link"></i> <?= $lang === 'uz' ? 'Biriktirish' : 'Прикрепить' ?>
                    </button>
                    <button class="btn btn-sm btn-secondary" data-action="copy-link" data-link="<?= UPLOAD_URL . e($item['filename']) ?>" title="<?= $lang === 'uz' ? 'Havolani nusxalash' : 'Скопировать ссылку' ?>">
                        <i data-feather="copy"></i> <?= $lang === 'uz' ? 'Havolani nusxalash' : 'Скопировать ссылку' ?>
                    </button>
                    <button class="btn btn-sm btn-danger" data-action="delete-media" data-media-id="<?= $mediaId ?>" data-usage="<?= $item['usage_count'] ?? 0 ?>" title="<?= $lang === 'uz' ? 'O‘chirish' : 'Удалить' ?>">
                        <i data-feather="trash-2"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="attach-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= $lang === 'uz' ? 'Mediani sahifaga biriktirish' : 'Прикрепить медиа к странице' ?></h3>
            <button data-action="close-attach" class="btn-close">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="attach-media-id">
            
            <div class="form-group">
                <label><?= $lang === 'uz' ? 'Sahifani tanlang:' : 'Выберите страницу:' ?></label>
                <input type="hidden" id="attach-page-id" required>
                <div id="attach-page-selected" class="muted">No page selected</div>
                <div id="attach-page-tree" class="page-tree">
                    <?php 
                    function renderPageHierarchy($pages, $parentId = 0) {
                        $output = '';
                        $childPages = array_filter($pages, function($p) use ($parentId) {
                            return ($p['parent_id'] ?? 0) == $parentId;
                        });
                        
                        usort($childPages, function($a, $b) {
                            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
                        });
                        
                        foreach ($childPages as $page) {
                            $label = e($page['slug'] ?? $page['title_ru'] ?? '');
                            $hasChildren = false;
                            foreach ($pages as $maybeChild) {
                                if (($maybeChild['parent_id'] ?? 0) == $page['id']) {
                                    $hasChildren = true;
                                    break;
                                }
                            }
                            
                            if ($hasChildren) {
                                $output .= '<details class="page-branch">';
                                $output .= '<summary>';
                                $output .= '<span class="page-label">' . $label . '</span>';
                                $output .= '<button type="button" class="page-pick" data-page-id="' . $page['id'] . '" data-page-label="' . $label . '">Select</button>';
                                $output .= '</summary>';
                                $output .= '<div class="page-children">';
                                $output .= renderPageHierarchy($pages, $page['id']);
                                $output .= '</div>';
                                $output .= '</details>';
                            } else {
                                $output .= '<button type="button" class="page-leaf page-pick" data-page-id="' . $page['id'] . '" data-page-label="' . $label . '">' . $label . '</button>';
                            }
                        }
                        return $output;
                    }
                    echo renderPageHierarchy($allPages);
                    ?>
                </div>
            </div>

            <div class="form-group">
                <label>Section / Slot:</label>
                <input id="attach-section" class="form-control" list="attach-section-list" placeholder="hero, banner or custom slot like card_fridges">
                <datalist id="attach-section-list">
                    <option value="content"></option>
                    <option value="hero"></option>
                    <option value="gallery"></option>
                    <option value="banner"></option>
                    <option value="card_fridges"></option>
                    <option value="card_tv"></option>
                    <option value="card_climate"></option>
                    <option value="team_photo"></option>
                </datalist>
                <small class="help-subtext">Hero/Banner are global. For in-content visuals use slot name from <code>{{media.your_slot}}</code> (e.g. <code>card_fridges</code>). Empty slots stay invisible.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Alt Text (RU):</label>
                    <input type="text" id="attach-alt-ru" class="form-control" placeholder="Описание изображения">
                </div>
                <div class="form-group">
                    <label>Alt Text (UZ):</label>
                    <input type="text" id="attach-alt-uz" class="form-control" placeholder="Rasm tavsifi">
                </div>
            </div>

            <div class="form-group">
                <label>Alignment:</label>
                <select id="attach-alignment" class="form-control">
                    <option value="center">Center</option>
                    <option value="left">Left</option>
                    <option value="right">Right</option>
                    <option value="full">Full Width</option>
                </select>
            </div>

            <div class="form-group">
                <label>Max Width (px):</label>
                <input type="number" id="attach-width" class="form-control" placeholder="Leave empty for auto">
            </div>
        </div>
        <div class="modal-footer">
            <button data-action="close-attach" class="btn"><?= $lang === 'uz' ? 'Bekor qilish' : 'Отмена' ?></button>
            <button data-action="attach-media" class="btn btn-primary"><?= $lang === 'uz' ? 'Mediani biriktirish' : 'Прикрепить медиа' ?></button>
        </div>
    </div>
</div>

<style>
.page-tree {
    max-height: 260px;
    overflow: auto;
    padding: 8px 10px;
    border: 1px solid #e1e1e1;
    border-radius: 6px;
    background: #fafafa;
}
.page-branch summary {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 4px 0;
}
.page-label {
    flex: 1;
}
.page-children {
    padding-left: 16px;
    margin: 4px 0 8px;
}
.page-leaf {
    display: block;
    width: 100%;
    text-align: left;
    padding: 4px 6px;
    margin: 2px 0;
    border: 0;
    background: transparent;
    cursor: pointer;
}
.page-leaf:hover,
.page-pick:hover {
    background: #f0f0f0;
}
.page-pick {
    border: 0;
    background: #e9eef5;
    padding: 2px 6px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}
.muted {
    color: #777;
    margin-bottom: 6px;
}
.media-usage {
    color: #666;
    font-size: 12px;
    margin-top: 6px;
}
.media-usage-title {
    font-weight: 600;
    margin-bottom: 4px;
}
.media-usage-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 2px 0;
}
.media-usage-label {
    color: #555;
}
.media-detach {
    border: 0;
    background: #f4d6d6;
    color: #7a1f1f;
    padding: 2px 6px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
}
.media-detach:hover {
    background: #f0c4c4;
}
.c-hidden{display:none!important}
.c-attach-banner{margin:12px 0;padding:12px 14px;border:1px solid var(--teal);background:#eff6ff;border-radius:10px;font-size:13px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.c-attach-note{color:#64748b}
.c-push-right{margin-left:auto}
.c-empty-icon{width:64px;height:64px;opacity:0.3}
.c-attached{outline:2px solid var(--teal);outline-offset:-2px}
.c-thumb-wrap{position:relative}
.c-thumb-img{width:100%;height:140px;object-fit:cover;display:block;background:#f3f4f6}
.c-attached-badge{position:absolute;top:6px;right:6px;background:var(--teal);color:#fff;font-size:11px;padding:2px 6px;border-radius:999px}
</style>

<script id="media-boot" type="application/json"><?= json_encode($mediaBoot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= BASE_URL ?>/js/admin/media.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
