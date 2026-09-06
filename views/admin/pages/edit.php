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
        <button type="button" class="tab-btn" onclick="switchTab('design')">Design</button>
        <button type="button" class="tab-btn" onclick="switchTab('seo')">SEO & Meta</button>
        <button type="button" class="tab-btn" onclick="switchTab('advanced')">Advanced SEO</button>
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
        <div class="form-group" id="media-slots-panel" style="margin-top:18px">
            <label style="display:flex;align-items:center;gap:8px">Media slots on this page <span id="media-slots-count" style="font-weight:400;color:#6b7280;font-size:12px"></span></label>
            <div id="media-slots-list" style="display:grid;gap:10px;margin-top:8px"></div>
            <small class="help-subtext">Detected from <code>{{media.*}}</code> in RU/UZ content. Save the page first, then attach images. Empty slots stay invisible on the site.</small>
        </div>
        <!-- Inline media picker modal (no navigation away) -->
        <div id="media-picker-modal" class="modal" style="display:none">
            <div class="modal-content" style="max-width:900px;width:92vw;max-height:86vh;display:flex;flex-direction:column">
                <div class="modal-header">
                    <h3 id="media-picker-title">Choose image</h3>
                    <button type="button" onclick="closeMediaPicker()" class="btn-close">&times;</button>
                </div>
                <div style="padding:10px 14px;border-bottom:1px solid #e5e7eb;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <div style="display:flex;gap:6px">
                        <button type="button" class="btn btn-sm media-picker-filter active" data-filter="all" onclick="pickerSetFilter('all')">All</button>
                        <button type="button" class="btn btn-sm media-picker-filter" data-filter="unused" onclick="pickerSetFilter('unused')">Unused</button>
                        <button type="button" class="btn btn-sm media-picker-filter" data-filter="used" onclick="pickerSetFilter('used')">Used</button>
                        <button type="button" class="btn btn-sm media-picker-filter" data-filter="attached" onclick="pickerSetFilter('attached')">Attached to this page</button>
                    </div>
                    <input type="text" id="media-picker-search" placeholder="Search filename..." oninput="pickerSearch()" style="flex:1;min-width:160px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px">
                    <label class="btn btn-secondary btn-sm" style="cursor:pointer;margin:0">Upload & attach<input type="file" id="media-picker-upload" accept="image/*" multiple style="display:none" onchange="pickerUpload(this)"></label>
                </div>
                <div id="media-picker-grid" style="overflow:auto;padding:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;flex:1;background:#f9fafb"></div>
                <div id="media-picker-status" style="padding:8px 14px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280"></div>
                <div class="modal-footer" style="display:flex;justify-content:space-between;align-items:center">
                    <a id="media-picker-open-library" href="#" target="_blank" class="btn btn-sm">Open full library →</a>
                    <button type="button" onclick="closeMediaPicker()" class="btn">Close</button>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const pageId = <?= (int)$page['id'] ?>;
            const csrf = '<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES) ?>';
            const csrfMeta = document.querySelector('meta[name="csrf-token"]')?.content || csrf;
            const getCsrf = ()=> document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken || csrfMeta || csrf;
            const baseUrl = '<?= BASE_URL ?>';
            const thumbBase = baseUrl + '/uploads/';
            let slotsData = <?= json_encode($slotsData, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
            const RESERVED = new Set(['hero','banner']);
            let pickerSlot = null;
            let pickerFilter = 'all';
            let pickerQuery = '';
            function getEditorContent(id){
                try{ const ed = tinymce.get(id); if(ed) return ed.getContent(); }catch(e){}
                const el=document.getElementById(id); return el?el.value:'';
            }
            function scanSlots(){
                const raw = (getEditorContent('content_ru')||'') + '\n' + (getEditorContent('content_uz')||'');
                const re = /\{\{media\.([a-zA-Z0-9_-]+)\}\}/g;
                const found=new Set(); let m;
                while((m=re.exec(raw))){ const k=m[1]; if(!RESERVED.has(k)) found.add(k); }
                return Array.from(found).sort();
            }
            function render(){
                const slots = scanSlots();
                const list=document.getElementById('media-slots-list');
                const countEl=document.getElementById('media-slots-count');
                if(!list) return;
                countEl.textContent = slots.length ? `(${slots.length} slot${slots.length>1?'s':''})` : '(no slots detected — add {{media.your_key}} to content)';
                if(!slots.length){ list.innerHTML='<div style="color:#9ca3af;font-size:13px;padding:10px;border:1px dashed #d1d5db;border-radius:8px">No {{media.*}} tokens found in content. Example: <code>{{media.card_fridges}}</code> then save and attach.</div>'; return; }
                list.innerHTML='';
                slots.forEach(slot=>{
                    const items = slotsData[slot]||[];
                    const row=document.createElement('div');
                    row.style.cssText='display:flex;align-items:center;gap:12px;padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb';
                    const thumbs = items.length
                        ? items.map(it=>`<img src="${baseUrl}/uploads/${it.filename}" style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb" loading="lazy" decoding="async">`).join('')
                        : '<span style="width:56px;height:56px;display:grid;place-items:center;border:1px dashed #d1d5db;border-radius:6px;color:#9ca3af;font-size:11px;text-align:center">⚠<br>empty</span>';
                    const meta = items.length ? `${items.length} image${items.length>1?'s':''} — ${items.length===1?'single':'gallery'}` : 'invisible on site until attached';
                    row.innerHTML=`
                        <div style="display:flex;gap:6px;flex-wrap:wrap;min-width:64px">${thumbs}</div>
                        <div style="flex:1;min-width:0">
                            <div style="font-family:monospace;font-size:13px;font-weight:600">{{media.${slot}}}</div>
                            <div style="font-size:12px;color:#6b7280">${meta}</div>
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button type="button" class="btn btn-primary btn-sm" onclick="openMediaPicker('${slot}')">Choose image</button>
                            <a href="${baseUrl}/admin/media?attach_slot=${encodeURIComponent(slot)}&page_id=${pageId}" class="btn btn-secondary btn-sm" title="Open full library in new tab" target="_blank">Library ↗</a>
                            ${items.length ? `<button type="button" class="btn btn-sm" style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca" data-slot="${slot}" onclick="detachSlot('${slot}')">Detach all</button>` : ''}
                        </div>`;
                    list.appendChild(row);
                });
            }
            window.detachSlot = async function(slot){
                if(!confirm(`Detach all images from {{media.${slot}}}?`)) return;
                const items = slotsData[slot]||[];
                for(const it of items){
                    const fd=new FormData(); fd.append('csrf_token', getCsrf()); fd.append('page_id', pageId); fd.append('media_id', it.media_id); fd.append('section', slot);
                    await fetch(baseUrl+'/admin/media/detach', {method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':getCsrf()}});
                }
                // refresh local state without full reload
                try {
                    const r = await fetch(baseUrl + '/admin/media/list?filter=attached&page_id='+pageId, {headers:{'Accept':'application/json'}});
                    const j = await r.json();
                    // rebuild slotsData from attached list not needed - just reload for correctness
                    location.reload();
                } catch(e){ location.reload(); }
            };
            // Picker functions
            window.openMediaPicker = async function(slot){
                pickerSlot = slot;
                document.getElementById('media-picker-title').textContent = 'Choose image for {{media.'+slot+'}}';
                document.getElementById('media-picker-open-library').href = baseUrl + '/admin/media?attach_slot='+encodeURIComponent(slot)+'&page_id='+pageId;
                document.getElementById('media-picker-modal').style.display='flex';
                document.getElementById('media-picker-modal').classList.add('active');
                pickerFilter = 'all';
                document.querySelectorAll('.media-picker-filter').forEach(b=>b.classList.toggle('active', b.dataset.filter===pickerFilter));
                document.getElementById('media-picker-search').value='';
                pickerQuery='';
                await pickerLoad();
            };
            window.closeMediaPicker = function(){
                document.getElementById('media-picker-modal').style.display='none';
                document.getElementById('media-picker-modal').classList.remove('active');
                pickerSlot=null;
            };
            window.pickerSetFilter = function(f){
                pickerFilter=f;
                document.querySelectorAll('.media-picker-filter').forEach(b=>b.classList.toggle('active', b.dataset.filter===f));
                pickerLoad();
            };
            window.pickerSearch = function(){
                pickerQuery = document.getElementById('media-picker-search').value.trim().toLowerCase();
                pickerLoad();
            };
            async function pickerLoad(){
                const grid=document.getElementById('media-picker-grid');
                const status=document.getElementById('media-picker-status');
                grid.innerHTML='<div style="grid-column:1/-1;padding:20px;text-align:center;color:#6b7280">Loading…</div>';
                status.textContent='';
                try {
                    const url = baseUrl + '/admin/media/list?filter='+encodeURIComponent(pickerFilter)+'&page_id='+pageId+'&q='+encodeURIComponent(pickerQuery)+'&limit=100';
                    const r = await fetch(url, {headers:{'Accept':'application/json'}});
                    const j = await r.json();
                    if (!j.success) throw new Error(j.message||'load failed');
                    let items = j.media || [];
                    // client-side search fallback (filename/original_name) if server didn't filter
                    if (pickerQuery) {
                        const q = pickerQuery;
                        items = items.filter(it => (it.original_name||'').toLowerCase().includes(q) || (it.filename||'').toLowerCase().includes(q));
                    }
                    status.textContent = items.length + ' image(s) — filter: '+pickerFilter+' (including unused & used)';
                    if (!items.length) { grid.innerHTML='<div style="grid-column:1/-1;padding:20px;text-align:center;color:#9ca3af">No images match filter/search. Try “All” or upload.</div>'; return; }
                    grid.innerHTML='';
                    items.forEach(it=>{
                        const attached = it.is_attached_to_page;
                        const card=document.createElement('div');
                        card.style.cssText='border:1px solid '+(attached?'#22c55e':'#e5e7eb')+';border-radius:8px;overflow:hidden;background:#fff;display:flex;flex-direction:column;cursor:pointer;'+(attached?'outline:2px solid #22c55e;outline-offset:-2px':'');
                        const imgUrl = it.thumb_url || (thumbBase + it.filename);
                        const dims = it.width && it.height ? ` width="${it.width}" height="${it.height}"` : '';
                        card.innerHTML=`
                            <div style="position:relative;height:110px;background:#f3f4f6;overflow:hidden">
                                <img src="${imgUrl}" alt="${(it.original_name||'').replace(/"/g,'&quot;')}" style="width:100%;height:100%;object-fit:cover;display:block" loading="lazy" decoding="async"${dims}>
                                ${attached ? `<span style="position:absolute;top:6px;right:6px;background:#22c55e;color:#fff;font-size:11px;padding:2px 6px;border-radius:999px">✓ attached${it.attached_section ? ' ('+it.attached_section+')':''}</span>` : ''}
                                <span style="position:absolute;bottom:4px;left:4px;background:rgba(0,0,0,0.6);color:#fff;font-size:10px;padding:1px 5px;border-radius:4px">${it.usage_count} page(s)</span>
                            </div>
                            <div style="padding:6px 8px;flex:1">
                                <div style="font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${(it.original_name||'').replace(/"/g,'&quot;')}">${(it.original_name||it.filename).replace(/</g,'&lt;')}</div>
                                <div style="font-size:10px;color:#6b7280">${Math.round((it.file_size||0)/1024)} KB • ID ${it.id}</div>
                            </div>
                            <div style="padding:6px 8px;border-top:1px solid #f3f4f6">
                                <button type="button" class="btn btn-primary btn-sm" style="width:100%">${attached ? 'Attach again / move' : 'Attach to {{media.'+pickerSlot+'}}'}</button>
                            </div>`;
                        card.querySelector('button').addEventListener('click', (e)=>{ e.stopPropagation(); pickerAttach(it.id); });
                        card.addEventListener('click', ()=> pickerAttach(it.id));
                        grid.appendChild(card);
                    });
                } catch(e){
                    grid.innerHTML='<div style="grid-column:1/-1;padding:12px;color:#b91c1c">Load failed: '+(e.message||e)+'</div>';
                }
            }
            window.pickerAttach = async function(mediaId){
                if (!pickerSlot) return;
                const fd=new FormData();
                fd.append('csrf_token', getCsrf());
                fd.append('page_id', pageId);
                fd.append('media_id', mediaId);
                fd.append('section', pickerSlot);
                try{
                    const r=await fetch(baseUrl+'/admin/media/attach', {method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':getCsrf()}});
                    const j=await r.json();
                    if(!j.success) throw new Error(j.message||'attach failed');
                    // update local slotsData optimistically then reload
                    const thumb = await fetch(baseUrl+'/admin/media/attachment?media_id='+mediaId+'&page_id='+pageId, {headers:{'Accept':'application/json'}}).then(x=>x.json()).catch(()=>null);
                    alert('✅ Attached to {{media.'+pickerSlot+'}}');
                    closeMediaPicker();
                    location.reload();
                }catch(e){ alert('Attach failed: '+(e.message||e)); }
            };
            window.pickerUpload = async function(input){
                const files=input.files;
                if(!files||!files.length) return;
                if(!pickerSlot){ alert('Pick a slot first'); return; }
                const fd=new FormData();
                Array.from(files).forEach(f=>fd.append('files[]', f));
                fd.append('csrf_token', getCsrf());
                fd.append('page_id', pageId);
                fd.append('section', pickerSlot);
                const status=document.getElementById('media-picker-status');
                status.textContent='Uploading '+files.length+' file(s)…';
                try{
                    const r=await fetch(baseUrl+'/admin/media/bulk-upload', {method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':getCsrf()}});
                    const ct=r.headers.get('content-type')||'';
                    const txt=await r.text();
                    if(!r.ok) throw new Error('HTTP '+r.status+': '+txt.slice(0,200));
                    if(!ct.includes('application/json')) throw new Error('Expected JSON got '+ct);
                    const j=JSON.parse(txt);
                    if(!j.success && !j.uploaded) throw new Error(j.message||'upload failed');
                    status.textContent='Uploaded '+j.uploaded+' file(s) and attached to {{media.'+pickerSlot+'}}';
                    input.value='';
                    await pickerLoad();
                    // reload page slots panel after short delay to show new thumbs
                    setTimeout(()=>location.reload(), 900);
                }catch(e){
                    status.textContent='Upload failed: '+e.message;
                    alert('Upload failed: '+e.message);
                    input.value='';
                }
            };
            // close on overlay click / ESC
            document.getElementById('media-picker-modal').addEventListener('click', (e)=>{ if(e.target.id==='media-picker-modal') closeMediaPicker(); });
            document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeMediaPicker(); });
            let t=null;
            function schedule(){ clearTimeout(t); t=setTimeout(render, 600); }
            document.addEventListener('DOMContentLoaded', render);
            setInterval(()=>{ const cur=scanSlots().join(','); if(window._lastSlots!==cur){window._lastSlots=cur; render();}}, 1500);
            setTimeout(()=>{ try{ tinymce.on('AddEditor', e=>{ e.editor.on('change keyup', schedule); }); ['content_ru','content_uz'].forEach(id=>{ const ed=tinymce.get(id); if(ed) ed.on('change keyup', schedule); }); }catch(e){}}, 1200);
            // listen for cross-tab attach from library (if user used Library ↗)
            window.addEventListener('message', (e)=>{ if(e.data && e.data.type==='media-attached' && String(e.data.pageId)===String(pageId)){ setTimeout(()=>location.reload(), 300); }});
        })();
        </script>
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

    <div id="tab-design" class="tab-content">
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
" spellcheck="false" style="font-family: monospace; font-size: 13px;"><?= e($page['custom_css'] ?? '') ?></textarea>
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
<script>
tinymce.init({
    selector: '.tinymce',
    height: 400,
    menubar: false,
    plugins: 'fullscreen code',
    toolbar: 'fullscreen code',
     content_css: [
        '<?= BASE_URL ?>/css/pages.min.css',
        '<?= BASE_URL ?>/css/components.min.css'
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