<?php 
// path: ./views/admin/pages/edit.php
require BASE_PATH . '/views/admin/layout/header.php'; 
?>

<h1><?= $page ? 'Edit Page' : 'Create Page' ?></h1>

<?php if ($page): ?>
<div class="ai-panel">
    <button type="button" class="ai-panel__toggle" onclick="toggleAiPanel(this)">
        <span class="ai-panel__toggle-title">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l1.6 4.9L18.5 8.5l-4.9 1.6L12 15l-1.6-4.9L5.5 8.5l4.9-1.6zM18 14l.8 2.4 2.4.8-2.4.8L18 20.4l-.8-2.4-2.4-.8 2.4-.8zM5 16l.6 1.9 1.9.6-1.9.6L5 21l-.6-1.9-1.9-.6 1.9-.6z"/>
            </svg>
            AI Assistant
        </span>
        <span class="ai-panel__badge">OpenRouter</span>
        <svg class="ai-panel__chevron" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div class="ai-panel__body">
        <div class="ai-panel__row">
            <div class="ai-panel__group ai-panel__group--field">
                <label for="ai-field">Target field</label>
                <select id="ai-field">
                    <option value="content_ru">Content (RU)</option>
                    <option value="content_uz">Content (UZ)</option>
                    <option value="title_ru">Title (RU)</option>
                    <option value="title_uz">Title (UZ)</option>
                    <option value="meta_title_ru">Meta title (RU)</option>
                    <option value="meta_title_uz">Meta title (UZ)</option>
                    <option value="meta_description_ru">Meta description (RU)</option>
                    <option value="meta_description_uz">Meta description (UZ)</option>
                </select>
            </div>
            <div class="ai-panel__group ai-panel__group--mode">
                <label for="ai-mode">Edit mode</label>
                <select id="ai-mode">
                    <option value="edits" selected>Chat — targeted line edits</option>
                    <option value="full">Replace entire field</option>
                </select>
            </div>
            <div class="ai-panel__group ai-panel__group--model">
                <label for="ai-model">Model</label>
                <select id="ai-model">
                    <option value="deepseek/deepseek-chat" selected>DeepSeek Chat (fast, cheap)</option>
                    <option value="openai/gpt-4o-mini">GPT-4o Mini (balanced)</option>
                    <option value="anthropic/claude-3.5-haiku">Claude Haiku (fast)</option>
                    <option value="meta-llama/llama-3.3-70b-instruct">Llama 3.3 70B (open)</option>
                </select>
            </div>
        </div>

        <div id="ai-chat-wrap">
            <div id="ai-chat" class="ai-chat">
                <div class="ai-chat__empty">Tell the AI what to change, e.g. "replace the phone number in the hero with +998 90 123 45 67". It will locate the exact text and change only that.</div>
            </div>

            <div class="ai-chat__input">
                <textarea id="ai-chat-prompt" rows="2" placeholder="e.g. Change the second paragraph to say we also pay for broken fridges..."></textarea>
                <button type="button" id="ai-chat-send" class="btn btn-primary" onclick="aiChatSend()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                    </svg>
                    Send
                </button>
            </div>
            <span id="ai-chat-status" class="ai-panel__status"></span>

            <div class="ai-chat__controls">
                <label class="ai-chat__autoapply">
                    <input type="checkbox" id="ai-autoapply" checked>
                    Auto-apply changes to field
                </label>
                <button type="button" class="btn btn-primary btn-sm" onclick="aiChatApply()">Apply all changes to field</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="aiChatUndo()">Undo last</button>
                <button type="button" class="btn btn-sm" onclick="aiChatReset()">Reset</button>
            </div>
        </div>

        <div id="ai-full-wrap" style="display:none;">
            <div class="ai-panel__chips">
                <button type="button" class="ai-chip" data-prompt="Rewrite this content to be clearer, more engaging and persuasive. Keep the same meaning and structure.">✍️ Rewrite</button>
                <button type="button" class="ai-chip" data-prompt="Improve this content for SEO: strengthen keywords, readability and user intent. Do not change the factual claims.">🎯 Improve SEO</button>
                <button type="button" class="ai-chip" data-prompt="Fix all grammar, spelling and punctuation mistakes. Keep the rest exactly the same.">✅ Fix grammar</button>
                <button type="button" class="ai-chip" data-prompt="Shorten this content by about 30% while keeping the key points.">✂️ Shorten</button>
                <button type="button" class="ai-chip" data-prompt="Expand this content with more useful details and examples. Keep the same style.">🚀 Expand</button>
                <button type="button" class="ai-chip" data-prompt="Translate this content into the target language naturally, keeping all template variables and formatting.">🔄 Translate</button>
            </div>

            <div class="ai-panel__group">
                <label for="ai-prompt">What should the AI do?</label>
                <textarea id="ai-prompt" rows="3" placeholder="e.g. Rewrite the intro to highlight that we pay the best prices in Tashkent, add a short paragraph about free pickup..."></textarea>
            </div>

            <div class="ai-panel__actions">
                <button type="button" id="ai-generate" class="btn btn-primary" onclick="aiGenerate()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Generate
                </button>
                <span id="ai-status" class="ai-panel__status"></span>
            </div>

            <div id="ai-result" class="ai-panel__result hidden">
                <div class="ai-panel__result-head">
                    <span>Generated result</span>
                    <span id="ai-result-meta" class="ai-panel__result-meta"></span>
                </div>
                <div id="ai-result-content" class="ai-panel__result-content"></div>
                <div class="ai-panel__result-actions">
                    <button type="button" class="btn btn-primary btn-sm" onclick="aiApply()">Apply to field</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="aiRegenerate()">Regenerate</button>
                    <button type="button" class="btn btn-sm" onclick="aiDiscard()">Discard</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/admin/pages/save" class="admin-form">
    <?= csrfField() ?>
    <?php if ($page): ?>
    <input type="hidden" name="id" value="<?= $page['id'] ?>">
    <?php endif; ?>
    
    <div class="tabs">
        <button type="button" class="tab-btn active" onclick="switchTab('general')">General</button>
        <button type="button" class="tab-btn" onclick="switchTab('seo')">SEO & Meta</button>
        <button type="button" class="tab-btn" onclick="switchTab('advanced')">Advanced SEO</button>
    </div>
    
    <div id="tab-general" class="tab-content active">
        <div class="help-text">
            <strong>Template Variables:</strong> Use {{variable}} syntax. Available: {{page.title}}, {{global.phone}}, {{global.email}}, {{global.address}}, {{global.working_hours}}, {{global.site_name}}, {{date.year}}, {{date.month}}
            <br><strong>Loops:</strong> {% for item in items %}...{% endfor %}
            <br><strong>Conditionals:</strong> {% if variable %}...{% else %}...{% endif %}
        </div>
        
        <div class="form-group">
            <label>Slug (URL)*</label>
            <input type="text" name="slug" value="<?= e($page['slug'] ?? '') ?>" required>
        </div>
        
        <?php
        // Normalize current parent for reliable selected handling (NULL/''/0 = root, otherwise int)
        $currentParentId = $page['parent_id'] ?? null;
        if ($currentParentId === '' || $currentParentId === 0 || $currentParentId === '0') $currentParentId = null;
        $isRootSelected = $currentParentId === null;
        // Fallback: if current parent exists but was filtered from $allPages (e.g. stale descendant exclusion), ensure it is still selectable
        $parentIdsInList = array_column($allPages ?? [], 'id');
        $fallbackParent = null;
        if ($currentParentId !== null && !in_array((int)$currentParentId, array_map('intval', $parentIdsInList), true)) {
            try {
                if (!class_exists('Page', false)) require_once BASE_PATH . '/models/Page.php';
                $fallbackParent = (new Page())->getById((int)$currentParentId);
            } catch (Throwable $e) { $fallbackParent = null; }
        }
        ?>
        <div class="form-group">
            <label>Parent Page (Optional - for hierarchy)</label>
            <select name="parent_id" class="form-control" data-current-parent="<?= $currentParentId !== null ? (int)$currentParentId : '' ?>" id="parent_id_select">
                <option value="" <?= $isRootSelected ? 'selected' : '' ?>>— Root Level (No Parent) —</option>
                <?php if ($fallbackParent): ?>
                    <option value="<?= (int)$fallbackParent['id'] ?>" selected>↳ <?= e($fallbackParent['title_ru'] ?? $fallbackParent['slug']) ?> (current parent)</option>
                <?php endif; ?>
                <?php if (!empty($allPages)): ?>
                    <?php 
                    if (!function_exists('renderParentPageOptions')) {
                    function renderParentPageOptions($pages, $currentPageId = null, $parentId = 0, $depth = 0, $maxDepth = 3, $selectedParentId = null) {
                        $output = '';
                        if ($depth > $maxDepth) return $output;
                        
                        $childPages = array_filter($pages, function($p) use ($parentId, $currentPageId) {
                            if ($currentPageId && (int)$p['id'] === (int)$currentPageId) return false;
                            $pid = $p['parent_id'] ?? null;
                            if ($pid === '' || $pid === null) $pid = 0;
                            return (int)$pid === (int)$parentId;
                        });
                        
                        usort($childPages, function($a, $b) {
                            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
                        });
                        
                        foreach ($childPages as $p) {
                            $indent = str_repeat('  ', $depth) . ($depth > 0 ? '└ ' : '');
                            $isSelected = $selectedParentId !== null && $selectedParentId !== '' && (string)$selectedParentId === (string)$p['id'];
                            $output .= sprintf(
                                '<option value="%d" %s>%s%s</option>' . "\n",
                                $p['id'],
                                $isSelected ? 'selected' : '',
                                $indent,
                                e($p['title_ru'] ?? $p['slug'])
                            );
                            $output .= renderParentPageOptions($pages, $currentPageId, $p['id'], $depth + 1, $maxDepth, $selectedParentId);
                        }
                        return $output;
                    }
                    }
                    echo renderParentPageOptions($allPages, $page['id'] ?? null, 0, 0, 3, $currentParentId);
                    ?>
                <?php endif; ?>
            </select>
            <small class="help-subtext">Create a page hierarchy. URLs remain flat, but breadcrumbs will show the path.</small>
        </div>
        <script>
        // Defensive: ensure the select reflects the current parent even if PHP rendering was stale/cached
        (function(){
            var sel = document.getElementById('parent_id_select');
            if (!sel) return;
            var cur = sel.getAttribute('data-current-parent');
            if (cur !== null && cur !== '' && sel.value !== cur) {
                // Only override if the expected value exists as an option
                var has = Array.prototype.some.call(sel.options, function(o){ return o.value === cur; });
                if (has) sel.value = cur;
            }
        })();
        </script>
        
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
        <div id="rotation_selection_group" class="form-group" style="display: <?= ($page['rotation_mode'] ?? 'auto') == 'manual' ? 'block' : 'none' ?>;">
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
<script>
tinymce.init({
    selector: '.tinymce',
    height: 400,
    menubar: false,
    plugins: 'fullscreen code',
    toolbar: 'fullscreen code',
    content_css: [
        '<?= BASE_URL ?>/css/pages.min.css'
    ],
    content_style: '.hero__content,.content-section,.info-card,.process-step,.brands-list,.faq-item,.condition-item,.review-strip,.links-tile,.faq-section{opacity:1!important;transform:none!important}',
});

// Handle rotation mode toggle
document.addEventListener('DOMContentLoaded', function() {
    const rotationModeSelect = document.getElementById('rotation_mode');
    const rotationSelectionGroup = document.getElementById('rotation_selection_group');
    
    if (rotationModeSelect) {
        rotationModeSelect.addEventListener('change', function() {
            if (rotationSelectionGroup) {
                rotationSelectionGroup.style.display = this.value === 'manual' ? 'block' : 'none';
            }
        });
    }
});
</script>

<?php if ($page): ?>
<script>
    window.AI_CONFIG = {
        pageId: <?= (int)$page['id'] ?>,
        csrf: '<?= $_SESSION['csrf_token'] ?? '' ?>',
        endpoint: '<?= BASE_URL ?>/admin/pages/ai-edit',
        chatEndpoint: '<?= BASE_URL ?>/admin/pages/ai-chat',
        fields: {
            content_ru: { type: 'tinymce', label: 'Content (RU)' },
            content_uz: { type: 'tinymce', label: 'Content (UZ)' },
            title_ru: { type: 'input', selector: '[name="title_ru"]', label: 'Title (RU)' },
            title_uz: { type: 'input', selector: '[name="title_uz"]', label: 'Title (UZ)' },
            meta_title_ru: { type: 'input', selector: '[name="meta_title_ru"]', label: 'Meta title (RU)' },
            meta_title_uz: { type: 'input', selector: '[name="meta_title_uz"]', label: 'Meta title (UZ)' },
            meta_description_ru: { type: 'input', selector: '[name="meta_description_ru"]', label: 'Meta description (RU)' },
            meta_description_uz: { type: 'input', selector: '[name="meta_description_uz"]', label: 'Meta description (UZ)' },
        }
    };
</script>
<script src="<?= BASE_URL ?>/js/admin/pages-ai.js"></script>
<?php endif; ?>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>