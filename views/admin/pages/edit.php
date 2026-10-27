<?php 
// path: ./views/admin/pages/edit.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<h1><?= $page ? 'Edit Page' : 'Create Page' ?></h1>

<form method="POST" action="<?= BASE_URL ?>/admin/pages/save" class="admin-form">
    <?= csrfField() ?>
    <?php if ($page): ?>
    <input type="hidden" name="id" value="<?= $page['id'] ?>">
    <?php endif; ?>
    
    <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="general">General</button>
        <button type="button" class="tab-btn" data-tab="design">Design</button>
        <button type="button" class="tab-btn" data-tab="seo">SEO & Meta</button>
        <button type="button" class="tab-btn" data-tab="advanced">Advanced SEO</button>
    </div>
    
    <div id="tab-general" class="tab-content active">
        <div class="help-text">
            <strong>Template Variables:</strong> Use {{variable}} syntax. Available: {{page.title}}, {{global.phone}}, {{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, {{date.year}}, {{date.month}}
            <br><strong>Media Slots:</strong> Drop <code>{{media.hero_card}}</code>, <code>{{media.card_fridges}}</code> etc. anywhere in content — attach images in the “Media slots on this page” panel below. Empty slots render nothing (invisible, no layout shift). Attach 1 image → single <code>&lt;img&gt;</code>, 2+ → auto <code>c-gallery-grid</code>. Reserved: <code>hero</code>/<code>banner</code> still use the Hero/Banner uploader — no placeholder needed for them.
            <br><strong>Loops:</strong> {% for item in items %}...{% endfor %}
            <br><strong>Conditionals:</strong> {% if variable %}...{% else %}...{% endif %}
        </div>
        
        <div class="form-group">
            <label>Slug (URL)*</label>
            <input type="text" name="slug" value="<?= e($page['slug'] ?? '') ?>" required>
        </div>
        
        <?php
        $parentBootPages = [];
        foreach (($allPages ?? []) as $ap) {
            $parentBootPages[] = [
                'id' => (int)($ap['id'] ?? 0),
                'parent_id' => (int)($ap['parent_id'] ?? 0),
                'sort_order' => (int)($ap['sort_order'] ?? 0),
                'title' => $ap['title_ru'] ?? $ap['slug'] ?? '',
                'slug' => $ap['slug'] ?? '',
            ];
        }
        $currentParentRaw = $page['parent_id'] ?? null;
        $currentParentBoot = ($currentParentRaw === '' || $currentParentRaw === null || (int)$currentParentRaw === 0) ? '' : (int)$currentParentRaw;
        $pagesEditBoot = [
            'pageId' => isset($page['id']) ? (int)$page['id'] : null,
            'currentPageId' => isset($page['id']) ? (int)$page['id'] : null,
            'currentParentId' => $currentParentBoot,
            'allPages' => $parentBootPages,
        ];
        ?>
        <div class="form-group">
            <label>Parent Page (Optional - for hierarchy)</label>
            <select name="parent_id" class="form-control" data-current-parent="<?= $currentParentBoot ?>" id="parent_id_select">
                <option value="">— Root Level (No Parent) —</option>
            </select>
            <script id="pages-parent-boot" type="application/json"><?= json_encode(['currentPageId' => $pagesEditBoot['currentPageId'], 'currentParentId' => $pagesEditBoot['currentParentId'], 'allPages' => $pagesEditBoot['allPages']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
            <small class="help-subtext">Create a page hierarchy. URLs remain flat, but breadcrumbs will show the path.</small>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Title (RU)*</label>
                <input type="text" name="title_ru" value="<?= e($page['title_ru'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Title (UZ)*</label>
                <input type="text" name="title_uz" value="<?= e($page['title_uz'] ?? '') ?>" required>
            </div>
        </div>
        
        <div class="form-group">
            <label>Content (RU)</label>
            <textarea name="content_ru" id="content_ru" class="tinymce"><?= e($page['content_ru'] ?? '') ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Content (UZ)</label>
            <textarea name="content_uz" id="content_uz" class="tinymce"><?= e($page['content_uz'] ?? '') ?></textarea>
        </div>

        <?php if (!empty($page['id'])): 
            // Preload slot attachments for JS panel (hero/banner excluded from slots UI)
            $slotsData = [];
            try {
                if (!class_exists('PageMedia', false)) require_once BASE_PATH . '/models/PageMedia.php';
                $pm = new PageMedia();
                $all = $pm->getPageMedia($page['id']);
                foreach ($all as $row) {
                    $sec = $row['section'];
                    if ($sec === 'hero' || $sec === 'banner') continue;
                    if (!isset($slotsData[$sec])) $slotsData[$sec] = [];
                    $slotsData[$sec][] = $row;
                }
            } catch (Throwable $e) { $slotsData = []; }
        ?>
        <div class="form-group c-slots-panel" id="media-slots-panel">
            <label class="c-slots-label">Media slots on this page <span id="media-slots-count" class="c-slots-count"></span></label>
            <div id="media-slots-list" class="c-slots-list"></div>
            <small class="help-subtext">Detected from <code>{{media.*}}</code> in RU/UZ content. Save the page first, then attach images. Empty slots stay invisible on the site.</small>
        </div>
        <!-- Inline media picker modal (no navigation away) -->
        <div id="media-picker-modal" class="modal">
            <div class="modal-content c-picker-content">
                <div class="modal-header">
                    <h3 id="media-picker-title">Choose image</h3>
                    <button type="button" data-picker="close" class="btn-close">&times;</button>
                </div>
                <div class="c-picker-tabs" role="tablist">
                    <button type="button" class="c-picker-tab active" data-picker-tab="library" role="tab">Моя медиатека</button>
                    <button type="button" class="c-picker-tab" data-picker-tab="stock" role="tab">Фото из интернета</button>
                </div>
                <div class="c-picker-opts">
                    <label>Макс. ширина
                        <select id="picker-maxw" title="Уменьшает слишком большие фото при загрузке / добавлении">
                            <option value="0">Как есть</option>
                            <option value="2000">2000 px</option>
                            <option value="1600">1600 px</option>
                            <option value="1366" selected>1366 px (рекомендуется)</option>
                            <option value="1200">1200 px</option>
                            <option value="800">800 px</option>
                        </select>
                    </label>
                    <label>Формат
                        <select id="picker-format">
                            <option value="">Как есть</option>
                            <option value="webp" selected>WebP (легче, рекомендуется)</option>
                            <option value="jpeg">JPEG</option>
                        </select>
                    </label>
                    <label class="c-picker-check"><input type="checkbox" id="picker-crop-first"> Обрезать перед загрузкой</label>
                    <small>Действует на «Upload &amp; attach» и на фото из интернета (✂ на фото = обрезать).</small>
                </div>
                <div class="c-picker-bar" id="media-picker-library-bar">
                    <div class="c-picker-filters">
                        <button type="button" class="btn btn-sm media-picker-filter active" data-picker-filter="all">All</button>
                        <button type="button" class="btn btn-sm media-picker-filter" data-picker-filter="unused">Unused</button>
                        <button type="button" class="btn btn-sm media-picker-filter" data-picker-filter="used">Used</button>
                        <button type="button" class="btn btn-sm media-picker-filter" data-picker-filter="attached">Attached to this page</button>
                    </div>
                    <input type="text" id="media-picker-search" placeholder="Search filename..." class="c-picker-search">
                    <label class="btn btn-secondary btn-sm c-upload-label">Upload & attach<input type="file" id="media-picker-upload" accept="image/*" multiple class="c-hidden-input"></label>
                </div>
                <div id="media-picker-grid" class="c-picker-grid"></div>
                <div id="media-picker-status" class="c-picker-status"></div>
                <div id="stock-panel" class="c-stock" hidden>
                    <div class="c-picker-bar">
                        <input type="text" id="stock-search" placeholder="Поиск фото (лучше по-английски), напр. refrigerator kitchen" class="c-picker-search" autocomplete="off">
                        <button type="button" class="btn btn-sm btn-primary" id="stock-search-btn">Искать</button>
                    </div>
                    <div id="stock-alt" class="c-stock-alt" hidden></div>
                    <div id="stock-grid" class="c-picker-grid"></div>
                    <div id="stock-status" class="c-picker-status"></div>
                    <div class="c-stock-foot">
                        <button type="button" class="btn btn-sm" id="stock-more" hidden>Показать ещё</button>
                        <span id="stock-credit">Бесплатные фото, указывать автора не обязательно. Фото общие, не конкретная модель.</span>
                    </div>
                </div>
                <div class="modal-footer c-picker-footer">
                    <a id="media-picker-open-library" href="#" target="_blank" class="btn btn-sm">Open full library →</a>
                    <button type="button" data-picker="close" class="btn">Close</button>
                </div>
            </div>
        </div>
        <script id="pages-edit-boot" type="application/json"><?= json_encode(array_merge($pagesEditBoot, ['csrf' => htmlspecialchars(generateCSRFToken(), ENT_QUOTES), 'baseUrl' => BASE_URL, 'slotsData' => $slotsData]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label>Sort Order</label>
                <input type="number" name="sort_order" value="<?= $page['sort_order'] ?? 0 ?>">
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_published" <?= ($page['is_published'] ?? 1) ? 'checked' : '' ?>>
                    Published
                </label>
            </div>
        </div>

        <div class="form-group">
            <label for="rotation_mode">Content Rotation Mode</label>
            <select id="rotation_mode" name="rotation_mode" class="form-control">
                <option value="disabled" <?= ($page['rotation_mode'] ?? 'auto') == 'disabled' ? 'selected' : '' ?>>
                    Disabled - No rotation, use base content
                </option>
                <option value="auto" <?= ($page['rotation_mode'] ?? 'auto') == 'auto' ? 'selected' : '' ?>>
                    Auto - Monthly rotation (traditional)
                </option>
                <option value="manual" <?= ($page['rotation_mode'] ?? 'auto') == 'manual' ? 'selected' : '' ?>>
                    Manual - Choose which rotation to display
                </option>
            </select>
            <small class="help-subtext">
                <strong>Disabled:</strong> Page shows base content only, no monthly changes.<br>
                <strong>Auto:</strong> Content changes monthly automatically (original behavior).<br>
                <strong>Manual:</strong> You select which month's content to display (best for SEO).
            </small>
        </div>

        <?php if ($page): ?>
        <div id="rotation_selection_group" class="form-group<?= ($page['rotation_mode'] ?? 'auto') == 'manual' ? '' : ' hidden' ?>">
            <label for="selected_rotation_id">Select Rotation to Display</label>
            <?php 
            $availableRotations = $availableRotations ?? [];
            if (!empty($availableRotations)): 
            ?>
                <select id="selected_rotation_id" name="selected_rotation_id" class="form-control">
                    <option value="">— No rotation selected —</option>
                    <?php foreach ($availableRotations as $rotation): ?>
                    <option value="<?= $rotation['id'] ?>" 
                        <?= ($page['selected_rotation_id'] ?? null) == $rotation['id'] ? 'selected' : '' ?>>
                        <?= e($months[$rotation['active_month']] ?? 'Month ' . $rotation['active_month']) ?> 
                        - <?= e(substr($rotation['title_ru'], 0, 50)) ?> 
                        <?= !$rotation['is_active'] ? '(Inactive)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="help-subtext">Select which month's rotation content to display. Leave empty to use base page content.</small>
            <?php else: ?>
                <div class="alert alert-info">
                    No rotations available yet. 
                    <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>" class="btn btn-sm">
                        Create rotations first
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($page): ?>
        <div class="form-actions form-actions-secondary">
            <a href="<?= BASE_URL ?>/admin/rotations/manage/<?= $page['id'] ?>" class="btn btn-secondary">Manage Content Rotations</a>
        </div>
        <?php endif; ?>
        
        <?php if ($page): ?>
        <a href="<?= BASE_URL ?>/admin/link-widget/manage/<?= $page['id'] ?>" class="btn btn-secondary">
            <i data-feather="link"></i> Manage Link Widget
        </a>
        <?php endif; ?>
    </div>

    <div id="tab-design" class="tab-content">
        <div class="form-group">
            <label for="theme">Page theme (layout &amp; style)</label>
            <select id="theme" name="theme" class="form-control">
                <?php $currentTheme = PageTheme::normalize($page['theme'] ?? PageTheme::BASE); ?>
                <?php foreach (PageTheme::all() as $themeKey => $themeLabel): ?>
                <option value="<?= e($themeKey) ?>" <?= $currentTheme === $themeKey ? 'selected' : '' ?>><?= e($themeLabel) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="help-subtext">
                Changes the whole look of this page: hero, cards, steps, FAQ and links get a different layout, fonts and colours.
                The page text is not touched. Custom CSS below still applies on top of the theme.
            </small>
        </div>
        <div class="help-text">
            <strong>Per-page Design:</strong> Override <code>pages.css</code> and <code>components.css</code> for this page only.<br>
            Empty = inherits global default. With CSS you get <strong>full control including header/footer</strong> — use <code>body.page-<?= e($page['slug'] ?? 'your-slug') ?> header { ... }</code> or <code>:root { --teal: #0a4f5c; --surface: #fdfcf8; }</code> token overrides.<br>
            Supports <code>&lt;style&gt;</code> content wrappers extracted to head automatically, so inline <code>&lt;style&gt;</code> in <code>content_ru/uz</code> also moves to head.
            <br><small>Tip: target <code>body.<?= 'page-' . preg_replace('/[^a-z0-9-]/','-', strtolower($page['slug'] ?? 'slug')) ?> header</code> for page-scoped header. Or use <code>:root</code> variable overrides to re-theme all components at once. CSS is sanitized (blocks @import, javascript:, expression).</small>
        </div>
        <div class="form-group">
            <label>Custom CSS (overrides pages.css + components.css + header/footer)</label>
            <textarea name="custom_css" rows="16" class="code" placeholder="/* Example: */
/* Recolor this page */
body.page-<?= e($page['slug'] ?? 'slug') ?> { --teal: #0a4f5c; --orange: #e8610a; --surface: #fdfcf8; }
/* Override header gradient */
body.page-<?= e($page['slug'] ?? 'slug') ?> header { background: linear-gradient(135deg, #0f5f6f 0%, #071a20 100%); }
/* Override footer */
body.page-<?= e($page['slug'] ?? 'slug') ?> footer { background: #0c0d10; }
/* Use any of 100+ components: .c-stats, .c-feature-split, .c-pricing, etc. */
" spellcheck="false" class="code c-code"><?= e($page['custom_css'] ?? '') ?></textarea>
            <small class="help-subtext">Leave empty to keep global defaults. CSS is injected after <code>pages.min.css</code> + <code>components.min.css</code> so it wins. Allowed: any selectors, CSS variables, @media. Blocked: @import, javascript: vectors. Loaded via <code>&lt;style id=&quot;page-custom-css&quot;&gt;</code> in head.</small>
        </div>
    </div>
    
    <div id="tab-seo" class="tab-content">
        <p class="help-text">Leave fields empty to use global defaults. All template variables work here too.</p>
        
        <div class="form-row">
            <div class="form-group">
                <label>Meta Title (RU)</label>
                <input type="text" name="meta_title_ru" value="<?= e($page['meta_title_ru'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>Meta Title (UZ)</label>
                <input type="text" name="meta_title_uz" value="<?= e($page['meta_title_uz'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Meta Keywords (RU)</label>
                <textarea name="meta_keywords_ru" rows="2"><?= e($page['meta_keywords_ru'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Meta Keywords (UZ)</label>
                <textarea name="meta_keywords_uz" rows="2"><?= e($page['meta_keywords_uz'] ?? '') ?></textarea>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Meta Description (RU)</label>
                <textarea name="meta_description_ru" rows="3"><?= e($page['meta_description_ru'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Meta Description (UZ)</label>
                <textarea name="meta_description_uz" rows="3"><?= e($page['meta_description_uz'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    
    <div id="tab-advanced" class="tab-content">
        <p class="help-text">Advanced SEO options for social media and search engines.</p>
        
        <div class="form-row">
            <div class="form-group">
                <label>OG Title (RU) - For Facebook/Social</label>
                <input type="text" name="og_title_ru" value="<?= e($page['og_title_ru'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label>OG Title (UZ)</label>
                <input type="text" name="og_title_uz" value="<?= e($page['og_title_uz'] ?? '') ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>OG Description (RU)</label>
                <textarea name="og_description_ru" rows="2"><?= e($page['og_description_ru'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>OG Description (UZ)</label>
                <textarea name="og_description_uz" rows="2"><?= e($page['og_description_uz'] ?? '') ?></textarea>
            </div>
        </div>
        
        <div class="form-group">
            <label>OG Image URL (Full URL)</label>
            <input type="text" name="og_image" value="<?= e($page['og_image'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>Canonical URL (Leave empty for auto)</label>
            <input type="text" name="canonical_url" value="<?= e($page['canonical_url'] ?? '') ?>">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>JSON-LD Schema (RU)</label>
                <textarea name="jsonld_ru" rows="8" class="code"><?= e($page['jsonld_ru'] ?? '') ?></textarea>
            </div>
            
            <div class="form-group">
                <label>JSON-LD Schema (UZ)</label>
                <textarea name="jsonld_uz" rows="8" class="code"><?= e($page['jsonld_uz'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Page</button>
        <a href="<?= BASE_URL ?>/admin/pages" class="btn btn-secondary">Cancel</a>
    </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.7.0/tinymce.min.js"></script>
<script src="<?= BASE_URL ?>/js/admin/image-tools.js?v=<?= @filemtime(BASE_PATH . '/public/js/admin/image-tools.js') ?: 1 ?>"></script>
<script src="<?= BASE_URL ?>/js/admin/pages-edit.js?v=<?= @filemtime(BASE_PATH . '/public/js/admin/pages-edit.js') ?: 1 ?>"></script>

<?php if ($page): ?>
<script id="ai-boot" type="application/json"><?= json_encode([
    'pageId' => (int)$page['id'],
    'csrf' => $_SESSION['csrf_token'] ?? '',
    'endpoint' => BASE_URL . '/admin/pages/ai-edit',
    'chatEndpoint' => BASE_URL . '/admin/pages/ai-chat',
    'fields' => [
        'content_ru' => ['type' => 'tinymce', 'label' => 'Content (RU)'],
        'content_uz' => ['type' => 'tinymce', 'label' => 'Content (UZ)'],
        'title_ru' => ['type' => 'input', 'selector' => '[name="title_ru"]', 'label' => 'Title (RU)'],
        'title_uz' => ['type' => 'input', 'selector' => '[name="title_uz"]', 'label' => 'Title (UZ)'],
        'meta_title_ru' => ['type' => 'input', 'selector' => '[name="meta_title_ru"]', 'label' => 'Meta title (RU)'],
        'meta_title_uz' => ['type' => 'input', 'selector' => '[name="meta_title_uz"]', 'label' => 'Meta title (UZ)'],
        'meta_description_ru' => ['type' => 'input', 'selector' => '[name="meta_description_ru"]', 'label' => 'Meta description (RU)'],
        'meta_description_uz' => ['type' => 'input', 'selector' => '[name="meta_description_uz"]', 'label' => 'Meta description (UZ)'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>

<style>
.c-slots-panel{margin-top:18px}
.c-slots-label{display:flex;align-items:center;gap:8px}
.c-slots-count{font-weight:400;color:#6b7280;font-size:12px}
.c-slots-list{display:grid;gap:10px;margin-top:8px}
.c-picker-content{max-width:900px;width:92vw;max-height:86vh;display:flex;flex-direction:column}
.c-picker-bar{padding:10px 14px;border-bottom:1px solid #e5e7eb;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.c-picker-filters{display:flex;gap:6px}
.c-picker-search{flex:1;min-width:160px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px}
.c-upload-label{cursor:pointer;margin:0}
.c-hidden-input{display:none!important}
.c-picker-grid{overflow:auto;padding:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));grid-auto-rows:160px;gap:12px;align-content:start;flex:1;background:#f9fafb;isolation:isolate}
.c-picker-status{padding:8px 14px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280}
.c-picker-footer{display:flex;justify-content:space-between;align-items:center}
.c-picker-tabs{display:flex;gap:6px;padding:8px 14px 0;border-bottom:1px solid #e5e7eb}
.c-picker-tab{border:0;background:none;padding:8px 14px;font-size:13px;font-weight:600;color:#6b7280;cursor:pointer;border-bottom:2px solid transparent}
.c-picker-tab.active{color:var(--teal);border-bottom-color:var(--teal)}
.c-stock{display:flex;flex-direction:column;flex:1;min-height:0}
.c-stock[hidden],.c-picker-bar[hidden],#media-picker-grid[hidden],#media-picker-status[hidden],#stock-more[hidden]{display:none}
.c-stock .c-picker-grid{flex:1}
.c-stock-foot{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:8px 14px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280}
.c-stock-foot a{color:var(--teal)}
.c-stock-credit-tag{position:absolute;inset:auto 0 0 0;background:linear-gradient(transparent,rgba(0,0,0,.72));padding:22px 8px 7px;color:#fff;font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;z-index:1}
.c-stock-card-busy{opacity:.55;pointer-events:none}
.c-picker-opts{display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;padding:8px 14px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#6b7280}
.c-picker-opts label{display:flex;align-items:center;gap:6px}
.c-picker-opts select{padding:4px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px}
.c-picker-opts small{flex-basis:100%;color:#9ca3af}
.c-stock-crop{position:absolute;top:7px;left:7px;z-index:3;border:0;border-radius:6px;background:rgba(0,0,0,.6);color:#fff;font-size:13px;line-height:1;padding:4px 7px;cursor:pointer;opacity:0;transition:opacity .16s}
.c-picker-card:hover .c-stock-crop,.c-stock-crop:focus-visible{opacity:1}
.c-stock-alt{display:flex;flex-wrap:wrap;gap:6px;padding:8px 14px 0;font-size:12px}
.c-stock-alt[hidden]{display:none}
.c-stock-alt button{border:1px solid #d1d5db;background:#fff;border-radius:999px;padding:3px 10px;font-size:12px;color:#374151;cursor:pointer}
.c-stock-alt button:hover,.c-stock-alt button.active{border-color:var(--teal);color:var(--teal)}
.c-code{font-family:monospace;font-size:13px}
.c-slot-row{display:flex;align-items:center;gap:12px;padding:10px;border:1px solid var(--teal);border-radius:10px;background:#f9fafb}
.c-slot-thumbs{display:flex;gap:6px;flex-wrap:wrap;min-width:64px;align-items:center}
.c-slot-main{flex:1;min-width:0}
.c-slot-key{font-family:monospace;font-size:13px;font-weight:600}
.c-slot-meta{font-size:12px;color:#6b7280}
.c-slot-actions{display:flex;gap:6px;flex-wrap:wrap}
.c-slot-detach{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.c-slot-thumb{position:relative;width:56px;height:56px;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;flex-shrink:0;background:#fff}
.c-slot-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.c-slot-empty{width:56px;height:56px;display:grid;place-items:center;border:1px dashed #d1d5db;border-radius:6px;color:#9ca3af;font-size:11px;text-align:center}
.c-slot-x{position:absolute;top:3px;right:3px;width:20px;height:20px;border-radius:999px;background:#ef4444;color:#fff;border:1.5px solid #fff;display:grid;place-items:center;font-size:13px;line-height:1;cursor:pointer;opacity:0;transition:opacity .14s,transform .14s;box-shadow:0 2px 8px rgba(0,0,0,.28);transform:scale(.92)}
.c-toast{position:fixed;bottom:18px;right:18px;background:#111827;color:#fff;padding:8px 12px;border-radius:8px;font-size:12px;box-shadow:0 8px 24px rgba(0,0,0,.18);z-index:9999;opacity:0;transition:opacity .2s}
.c-toast-ok{background:#065f46;padding:9px 13px;border-radius:9px}
.c-empty-slots{color:#9ca3af;font-size:13px;padding:10px;border:1px dashed #d1d5db;border-radius:8px}
.c-picker-card{border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fff;position:relative;height:160px;cursor:pointer;isolation:isolate}
.c-picker-card-attached{border-color:var(--teal);outline:2px solid var(--teal);outline-offset:-2px}
.c-picker-img{width:100%;height:100%;object-fit:cover;display:block;background:#f3f4f6}
.c-picker-img-broken{display:none}
.c-picker-fallback{display:none;position:absolute;inset:0;place-items:center;background:#f3f4f6;color:#9ca3af;font-size:11px;padding:8px;text-align:center}
.c-picker-fallback-show{display:grid}
.c-picker-fallback{display:none;position:absolute;inset:0;place-items:center;background:#f3f4f6;color:#9ca3af;font-size:11px;padding:8px;text-align:center}
.c-picker-check{position:absolute;top:7px;right:7px;background:var(--teal);color:#fff;font-size:11px;font-weight:700;padding:3px 7px;border-radius:999px;box-shadow:0 2px 8px rgba(0,0,0,.18);z-index:1}
.c-picker-tag{position:absolute;top:7px;left:7px;background:rgba(0,0,0,.58);color:#fff;font-size:10px;padding:2px 6px;border-radius:999px}
.c-picker-cap{position:absolute;inset:auto 0 0 0;background:linear-gradient(transparent,rgba(0,0,0,.72));padding:28px 8px 8px;color:#fff;z-index:1}
.c-picker-name{font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.c-picker-sub{font-size:10px;opacity:.88}
.c-picker-hover{position:absolute;inset:0;display:grid;place-items:center;background:rgba(0,0,0,.34);opacity:0;transition:opacity .16s;z-index:2}
.c-picker-hover span{background:#fff;color:#111827;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:700;box-shadow:0 4px 16px rgba(0,0,0,.22);transform:translateY(2px);transition:transform .16s}
.c-picker-loading{grid-column:1/-1;padding:20px;text-align:center;color:#6b7280}
.c-picker-empty{grid-column:1/-1;padding:20px;text-align:center;color:#9ca3af}
.c-picker-error{grid-column:1/-1;padding:12px;color:#b91c1c}
</style>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
