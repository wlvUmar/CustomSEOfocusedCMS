// Extracted from views/admin/pages/edit.php — reads #pages-edit-boot JSON blob (data-boot).
// Sections: (0) boot + parent options, (1) tab delegation, (2) media slots/picker, (3) tinymce init + rotation toggle.

function getPagesEditBoot() {
    let boot = {};
    try {
        const el = document.getElementById('pages-edit-boot');
        if (el && el.textContent.trim()) boot = JSON.parse(el.textContent);
    } catch (e) { /* fall through */ }
    try {
        const parent = document.getElementById('pages-parent-boot');
        if (parent && parent.textContent.trim()) boot = Object.assign({}, JSON.parse(parent.textContent), boot);
    } catch (e) { /* fall through */ }
    if (!boot || !Object.keys(boot).length) boot = window.PagesEditBoot || {};
    return boot;
}

function escBootHtml(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

// (0) parent select options (ported from renderParentPageOptions presenter)
(function(){
    const boot = getPagesEditBoot();
    const sel = document.getElementById('parent_id_select');
    if (!sel) return;
    const pages = Array.isArray(boot.allPages) ? boot.allPages : [];
    if (!pages.length) return;
    const curPageId = boot.currentPageId;
    const cur = (boot.currentParentId !== undefined && boot.currentParentId !== null) ? String(boot.currentParentId) : sel.getAttribute('data-current-parent');
    function kids(parentId, depth) {
        if (depth > 3) return '';
        let out = '';
        const children = pages
            .filter(p => (parseInt(p.parent_id, 10) || 0) === (parseInt(parentId, 10) || 0) && String(p.id) !== String(curPageId))
            .sort((a, b) => (parseInt(a.sort_order, 10) || 0) - (parseInt(b.sort_order, 10) || 0));
        children.forEach(p => {
            const indent = '  '.repeat(depth) + (depth > 0 ? '└ ' : '');
            const selected = (cur !== '' && cur !== null && String(cur) === String(p.id)) ? ' selected' : '';
            out += '<option value="' + p.id + '"' + selected + '>' + indent + escBootHtml(p.title || p.slug) + '</option>';
            out += kids(p.id, depth + 1);
        });
        return out;
    }
    let html = '<option value="">— Root Level (No Parent) —</option>' + kids(0, 0);
    if (cur !== '' && cur !== null && !pages.some(p => String(p.id) === String(cur))) {
        html += '<option value="' + escBootHtml(cur) + '" selected>↳ #' + escBootHtml(cur) + ' (current parent)</option>';
    }
    sel.innerHTML = html;
    if (cur !== null && cur !== '' && sel.value !== cur) {
        const has = Array.prototype.some.call(sel.options, function(o){ return o.value === cur; });
        if (has) sel.value = cur;
    }
})();

// (1) tab + picker delegation (replaces inline onclick/oninput/onchange)
document.addEventListener('click', (e) => {
    const tab = e.target.closest('[data-tab]');
    if (tab) {
        if (typeof window.switchTab === 'function') window.switchTab(tab.getAttribute('data-tab'), e);
        return;
    }
    const pf = e.target.closest('[data-picker-filter]');
    if (pf) {
        if (typeof window.pickerSetFilter === 'function') window.pickerSetFilter(pf.getAttribute('data-picker-filter'));
        return;
    }
    const pc = e.target.closest('[data-picker="close"]');
    if (pc && typeof window.closeMediaPicker === 'function') window.closeMediaPicker();
});
document.addEventListener('input', (e) => {
    if (e.target && e.target.id === 'media-picker-search' && typeof window.pickerSearch === 'function') window.pickerSearch();
});
document.addEventListener('change', (e) => {
    if (e.target && e.target.id === 'media-picker-upload' && typeof window.pickerUpload === 'function') window.pickerUpload(e.target);
});

// (2) media slots + picker
        (function(){
            const boot = getPagesEditBoot();
            const pageId = boot.pageId;
            const csrf = boot.csrf || '';
            const csrfMeta = document.querySelector('meta[name="csrf-token"]')?.content || csrf;
            const getCsrf = ()=> document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken || csrfMeta || csrf;
            const baseUrl = window.baseUrl || boot.baseUrl || '';
            const thumbBase = baseUrl + '/uploads/';
            let slotsData = boot.slotsData || {};
            if (!pageId) return;
            const RESERVED = new Set(['hero','banner']);
            let pickerSlot = null;
            let pickerFilter = 'all';
            let pickerQuery = '';
            let pickerAdded = 0;   // images added during this picker session (multi mode)
            // Gallery slots take many images: same suffix rule as MediaRenderer::renderMediaSlot (_scroll/_carousel/_slider/_hscroll).
            const isMultiSlot = (s)=> /_(scroll|carousel|slider|hscroll)$/i.test(String(s||''));
            function updateDoneLabel(){
                const b = document.getElementById('media-picker-close-btn');
                if(b) b.textContent = isMultiSlot(pickerSlot) ? (pickerAdded ? 'Done ('+pickerAdded+' added)' : 'Done') : 'Close';
            }
            // multi mode: mark a picker card as "in this gallery" without reloading the grid (keeps scroll position)
            function markPicked(card){
                pickerAdded++;
                updateDoneLabel();
                const status=document.getElementById('media-picker-status');
                if(status) status.textContent = 'Added '+pickerAdded+' — pick more, or press Done';
                if(!card || !card.classList) return;
                card._inSlot = true;
                card.classList.add('c-picker-card-attached');
                if(!card.querySelector('.c-picker-check')){ const s=document.createElement('span'); s.className='c-picker-check'; s.textContent='✓'; card.appendChild(s); }
                const h=card.querySelector('.picker-hover span'); if(h) h.textContent='In this gallery';
            }
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
            function escHtml(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
            function render(){
                const slots = scanSlots();
                const list=document.getElementById('media-slots-list');
                const countEl=document.getElementById('media-slots-count');
                if(!list) return;
                countEl.textContent = slots.length ? `(${slots.length} slot${slots.length>1?'s':''})` : '(no slots detected — add {{media.your_key}} to content)';
                if(!slots.length){ list.innerHTML='<div class="c-empty-slots">No {{media.*}} tokens found in content. Example: <code>{{media.card_fridges}}</code> then save and attach.</div>'; return; }
                list.innerHTML='';
                slots.forEach(slot=>{
                    const items = slotsData[slot]||[];
                    const row=document.createElement('div');
                    row.className='c-slot-row';
                    let thumbsHtml='';
                    if(!items.length){
                        thumbsHtml = '<span class="c-slot-empty">⚠<br>empty</span>';
                    } else {
                        thumbsHtml = items.map(it=>{
                            const mid = it.media_id || it.id;
                            const fname = escHtml(it.filename||'');
                            const oname = escHtml(it.original_name||it.filename||'');
                            return `<div class="c-slot-thumb slot-thumb" data-media-id="${mid}" title="${oname} — ID ${mid}">
                                <img src="${baseUrl}/uploads/${fname}" alt="${oname}" loading="lazy" decoding="async">
                                <button type="button" data-detach-single="${mid}" data-slot="${escHtml(slot)}" title="Detach this image" class="c-slot-x slot-thumb-x">×</button>
                            </div>`;
                        }).join('');
                    }
                    const multi = isMultiSlot(slot);
                    const meta = items.length ? `${items.length} image${items.length>1?'s':''} — ${multi ? 'side-scroll gallery' : (items.length===1?'single':'gallery')}` : (multi ? 'gallery slot — attach as many images as you like; invisible until attached' : 'invisible on site until attached');
                    row.innerHTML=`
                        <div class="c-slot-thumbs">${thumbsHtml}</div>
                        <div class="c-slot-main">
                            <div class="c-slot-key">{{media.${escHtml(slot)}}}</div>
                            <div class="c-slot-meta">${escHtml(meta)}</div>
                        </div>
                        <div class="c-slot-actions">
                            <button type="button" class="btn btn-primary btn-sm" data-open-picker="${escHtml(slot)}">${multi ? 'Choose images' : 'Choose image'}</button>
                            <a href="${baseUrl}/admin/media?attach_slot=${encodeURIComponent(slot)}&page_id=${pageId}" class="btn btn-secondary btn-sm" title="Open full library in new tab" target="_blank">Library ↗</a>
                            ${items.length ? `<button type="button" class="btn btn-sm c-slot-detach" data-detach-slot="${escHtml(slot)}">Detach all</button>` : ''}
                        </div>`;
                    list.appendChild(row);
                });
                // hover red X
                list.querySelectorAll('.slot-thumb').forEach(el=>{
                    el.addEventListener('mouseenter', ()=>{ const b=el.querySelector('.slot-thumb-x'); if(b){b.style.opacity='1'; b.style.transform='scale(1)';}});
                    el.addEventListener('mouseleave', ()=>{ const b=el.querySelector('.slot-thumb-x'); if(b){b.style.opacity='0'; b.style.transform='scale(.92)';}});
                    // always show X on touch devices after tap
                    el.addEventListener('click', (e)=>{ if(e.target.closest('.slot-thumb-x')) return; const b=el.querySelector('.slot-thumb-x'); if(b && getComputedStyle(b).opacity==='0'){ b.style.opacity='1'; b.style.transform='scale(1)'; setTimeout(()=>{b.style.opacity='0'; b.style.transform='scale(.92)';}, 2200);}});
                });
                if(!list._slotsDelegated){
                    list._slotsDelegated = true;
                    list.addEventListener('click', (e)=>{
                        const open = e.target.closest('[data-open-picker]');
                        if(open){ window.openMediaPicker(open.getAttribute('data-open-picker')); return; }
                        const one = e.target.closest('[data-detach-single]');
                        if(one){ window.detachSingle(one.getAttribute('data-slot'), one.getAttribute('data-detach-single'), one); return; }
                        const all = e.target.closest('[data-detach-slot]');
                        if(all) window.detachSlot(all.getAttribute('data-detach-slot'));
                    });
                }
            }
            window.detachSingle = async function(slot, mediaId, btnEl){
                if(!mediaId) return;
                // find filename for confirm
                const item = (slotsData[slot]||[]).find(x=> String(x.media_id||x.id)===String(mediaId));
                const name = item ? (item.original_name||item.filename||mediaId) : mediaId;
                if(!confirm(`Detach "${name}" from {{media.${slot}}}?`)) return;
                if(btnEl) { btnEl.disabled=true; btnEl.textContent='…'; }
                try{
                    const fd=new FormData(); fd.append('csrf_token', getCsrf()); fd.append('page_id', pageId); fd.append('media_id', mediaId); fd.append('section', slot);
                    const r=await fetch(baseUrl+'/admin/media/detach', {method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':getCsrf()}});
                    const j=await r.json().catch(()=>({}));
                    if(!r.ok || j.success===false) throw new Error(j.message||('HTTP '+r.status));
                    // update local state without reload
                    if(slotsData[slot]) slotsData[slot] = slotsData[slot].filter(x=> String(x.media_id||x.id)!==String(mediaId));
                    render();
                    if(pickerSlot && document.getElementById('media-picker-modal')?.classList.contains('active')) pickerLoad();
                    // tiny toast
                    const list=document.getElementById('media-slots-list');
                    if(list){
                        const t=document.createElement('div');
                        t.textContent='Detached ✓';
                        t.className='c-toast';
                        document.body.appendChild(t); requestAnimationFrame(()=>t.style.opacity='1'); setTimeout(()=>{t.style.opacity='0'; setTimeout(()=>t.remove(),250);}, 1600);
                    }
                } catch(e){
                    alert('Detach failed: '+(e.message||e));
                    if(btnEl){ btnEl.disabled=false; btnEl.textContent='×'; }
                }
            };
            window.detachSlot = async function(slot){
                if(!confirm(`Detach all ${((slotsData[slot]||[]).length)} image(s) from {{media.${slot}}}?`)) return;
                const items = [...(slotsData[slot]||[])];
                for(const it of items){
                    const fd=new FormData(); fd.append('csrf_token', getCsrf()); fd.append('page_id', pageId); fd.append('media_id', it.media_id); fd.append('section', slot);
                    const r=await fetch(baseUrl+'/admin/media/detach', {method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':getCsrf()}});
                    const j=await r.json().catch(()=>({}));
                    if(!r.ok || j.success===false){ alert('Detach failed for '+it.filename+': '+(j.message||r.status)); return; }
                }
                slotsData[slot]=[];
                render();
                if(pickerSlot && document.getElementById('media-picker-modal')?.classList.contains('active')) pickerLoad();
            };
            // Picker functions
            window.openMediaPicker = async function(slot){
                pickerSlot = slot;
                pickerAdded = 0;
                updateDoneLabel();
                document.getElementById('media-picker-title').textContent = (isMultiSlot(slot) ? 'Choose images (select several) for {{media.' : 'Choose image for {{media.')+slot+'}}';
                document.getElementById('media-picker-open-library').href = baseUrl + '/admin/media?attach_slot='+encodeURIComponent(slot)+'&page_id='+pageId;
                document.getElementById('media-picker-modal').classList.add('active');
                pickerFilter = 'all';
                document.querySelectorAll('.media-picker-filter').forEach(b=>b.classList.toggle('active', b.dataset.pickerFilter===pickerFilter));
                document.getElementById('media-picker-search').value='';
                if (typeof window.pickerSetTab === 'function') window.pickerSetTab('library', true);
                pickerQuery='';
                await pickerLoad();
            };
            window.closeMediaPicker = function(){
                document.getElementById('media-picker-modal').classList.remove('active');
                pickerSlot=null;
                pickerAdded=0;
                updateDoneLabel();
            };
            window.pickerSetFilter = function(f){
                pickerFilter=f;
                document.querySelectorAll('.media-picker-filter').forEach(b=>b.classList.toggle('active', b.dataset.pickerFilter===f));
                pickerLoad();
            };
            window.pickerSearch = function(){
                pickerQuery = document.getElementById('media-picker-search').value.trim().toLowerCase();
                pickerLoad();
            };
            async function pickerLoad(){
                const grid=document.getElementById('media-picker-grid');
                const status=document.getElementById('media-picker-status');
                grid.innerHTML='<div class="c-picker-loading">Loading…</div>';
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
                    if (!items.length) { grid.innerHTML='<div class="c-picker-empty">No images match filter/search. Try “All” or upload.</div>'; return; }
                    grid.innerHTML='';
                    items.forEach(it=>{
                        const multi = isMultiSlot(pickerSlot);
                        // multi mode: "attached" means in THIS gallery; single mode keeps the old page-wide meaning
                        const attached = multi ? (slotsData[pickerSlot]||[]).some(x=> String(x.media_id||x.id)===String(it.id)) : it.is_attached_to_page;
                        const card=document.createElement('div');
                        card._inSlot = multi && attached;
                        card.className='c-picker-card'+(attached?' c-picker-card-attached':'');
                        let imgUrl = it.thumb_url || (thumbBase + it.filename);
                        // thumb_url from server may be absolute (BASE_URL + /uploads/...) or root /uploads/... — ensure it resolves
                        if(imgUrl && imgUrl.startsWith('/uploads/') && baseUrl) imgUrl = baseUrl + imgUrl;
                        const safeName = escHtml(it.original_name||it.filename||'');
                        const kb = Math.round((it.file_size||0)/1024);
                        card.innerHTML=`
                            <img src="${imgUrl}" alt="${safeName.replace(/"/g,'&quot;')}" class="c-picker-img" loading="lazy" decoding="async" onerror="this.classList.add('c-picker-img-broken');this.nextElementSibling.classList.add('c-picker-fallback-show')">
                            <div class="c-picker-fallback">${safeName}</div>
                            ${attached ? `<span class="c-picker-check">✓</span>` : ''}
                            <span class="c-picker-tag">#${it.id} · ${kb}KB</span>
                            <div class="c-picker-cap">
                                <div class="c-picker-name" title="${safeName}">${safeName}</div>
                                <div class="c-picker-sub">${it.usage_count||0} page(s)${it.attached_section ? ' · '+escHtml(it.attached_section) : ''}</div>
                            </div>
                            <div class="c-picker-hover picker-hover">
                                <span>${multi ? (attached ? 'In this gallery' : 'Add to '+escHtml(pickerSlot)) : (attached ? 'Re-attach' : 'Attach to '+escHtml(pickerSlot))}</span>
                            </div>`;
                        card.addEventListener('mouseenter', ()=>{ const h=card.querySelector('.picker-hover'); if(h){h.style.opacity='1'; h.firstElementChild.style.transform='translateY(0)'; }});
                        card.addEventListener('mouseleave', ()=>{ const h=card.querySelector('.picker-hover'); if(h){h.style.opacity='0'; h.firstElementChild.style.transform='translateY(2px)'; }});
                        card.addEventListener('click', ()=>{
                            if(card._inSlot){ status.textContent = 'Already in this gallery — remove it with × on its thumbnail in the slots list.'; return; }
                            pickerAttach(it.id, card);
                        });
                        // also handle touch: first tap shows hover, second attaches? For now single tap attaches directly (as before)
                        const _btn = card; // for pickerAttach btn param compatibility
                        // store for fallback
                        card._pickerId = it.id;
                        grid.appendChild(card);
                    });
                } catch(e){
                    grid.innerHTML='<div class="c-picker-error">Load failed: '+(e.message||e)+'</div>';
                }
            }
            // ── Stock photos (Pexels) tab: search derived from the page, click a photo to import + attach ──
            const byId = (id)=>document.getElementById(id);
            let stockQuery = '';
            let stockSource = 'сток';
            let stockPage = 1;
            let stockLoaded = false;
            window.pickerSetTab = function(tab, skipLoad){
                const stock = tab === 'stock';
                document.querySelectorAll('[data-picker-tab]').forEach(b=>b.classList.toggle('active', b.dataset.pickerTab===tab));
                ['media-picker-library-bar','media-picker-grid','media-picker-status'].forEach(id=>{ const el=byId(id); if(el) el.hidden = stock; });
                const sp = byId('stock-panel'); if(sp) sp.hidden = !stock;
                const cropOpt = byId('picker-crop-opt'); if(cropOpt) cropOpt.hidden = stock;   // "crop before upload" is for file uploads only
                if (stock) { if (!stockLoaded) stockLoad(true); }
                else if (!skipLoad) pickerLoad();
            };
            async function stockLoad(reset){
                const grid=byId('stock-grid'), status=byId('stock-status'), more=byId('stock-more');
                if(!grid) return;
                status.classList.remove('c-picker-status-error');
                if(reset){ stockPage=1; grid.innerHTML='<div class="c-picker-loading">Загрузка…</div>'; more.hidden=true; }
                status.textContent='';
                try{
                    const url = baseUrl+'/admin/media/stock-search?page_id='+encodeURIComponent(pageId)+'&q='+encodeURIComponent(stockQuery)+'&p='+stockPage;
                    const r = await fetch(url, {headers:{'Accept':'application/json'}});
                    const j = await r.json().catch(()=>({success:false,message:'HTTP '+r.status}));
                    if(!j.success) throw new Error(j.message||'не удалось загрузить');
                    stockLoaded = true;
                    stockQuery = j.query;                 // keyword derived by the server when the box was empty
                    byId('stock-search').value = j.query;
                    const srcName = (j.attribution && j.attribution.name) || 'сток';
                    stockSource = srcName;
                    const credit = byId('stock-credit');
                    if(credit && j.attribution){
                        credit.innerHTML = 'Фото: <a href="'+escHtml(j.attribution.url)+'" target="_blank" rel="noopener noreferrer">'+escHtml(j.attribution.name)+'</a> — бесплатно, указывать автора не обязательно. Фото общие, не конкретная модель.';
                    }
                    // alternative queries from config/stock_queries.php as one-click chips
                    const alt = byId('stock-alt');
                    if(alt){
                        const list = (j.alternatives||[]);
                        alt.hidden = list.length < 2;
                        alt.innerHTML = list.map(q=>`<button type="button" data-stock-q="${escHtml(q)}"${q===j.query?' class="active"':''}>${escHtml(q)}</button>`).join('');
                    }
                    if(reset) grid.innerHTML='';
                    if(reset && !j.results.length){ grid.innerHTML='<div class="c-picker-empty">Ничего не найдено. Попробуйте другой запрос (лучше по-английски).</div>'; }
                    j.results.forEach(p=>{
                        const card=document.createElement('div');
                        card.className='c-picker-card';
                        card.innerHTML = `<img src="${escHtml(p.thumb)}" alt="${escHtml(p.alt||'')}" class="c-picker-img" loading="lazy" decoding="async">
                            <button type="button" class="c-stock-crop" title="Обрезать перед добавлением">✂</button>
                            <div class="c-picker-hover picker-hover"><span>Выбрать</span></div>
                            <div class="c-stock-credit-tag">${escHtml(p.photographer)} · ${escHtml(stockSource)}</div>`;
                        card.addEventListener('mouseenter', ()=>{ const h=card.querySelector('.picker-hover'); if(h){h.style.opacity='1'; h.firstElementChild.style.transform='translateY(0)'; }});
                        card.addEventListener('mouseleave', ()=>{ const h=card.querySelector('.picker-hover'); if(h){h.style.opacity='0'; h.firstElementChild.style.transform='translateY(2px)'; }});
                        card.addEventListener('click', ()=> stockImport(p, card));
                        card.querySelector('.c-stock-crop').addEventListener('click', ev=>{ ev.stopPropagation(); stockCropThenImport(p, card); });
                        grid.appendChild(card);
                    });
                    status.textContent = '«'+j.query+'» — найдено '+j.total;
                    more.hidden = !j.has_more;
                } catch(e){
                    if(reset) grid.innerHTML='';
                    status.textContent = 'Ошибка поиска: '+(e.message||e);
                    status.classList.add('c-picker-status-error');
                }
            }
            // picker toolbar: max width / format for uploads and stock photos, "crop before upload" for uploads
            function pickerOpts(){
                return {
                    maxWidth: parseInt((byId('picker-maxw')||{}).value, 10) || 0,
                    format: (byId('picker-format')||{}).value || '',
                    cropFirst: !!(byId('picker-crop-first')||{}).checked
                };
            }
            // Upload & attach: optional crop dialog per file, then canvas resize/convert. Resolves to the files to send, or null if cancelled.
            async function prepareUploads(files){
                const IT = window.ImageTools;
                const opts = pickerOpts();
                const out = [];
                for (const f of Array.from(files)) {
                    let file = f;
                    if (IT) {
                        if (opts.cropFirst && /^image\/(jpeg|png|webp)$/.test(f.type)) {
                            const url = URL.createObjectURL(f);
                            let res;
                            try { res = await IT.cropDialog({src:url, mime:f.type, title:f.name, allowRotate:true, allowSkip:true, wantBlob:true}); }
                            catch(e){ alert(e.message || e); res = {skipped:true}; }
                            URL.revokeObjectURL(url);
                            if (res === null) return null;      // cancelled: upload nothing
                            if (res.blob) file = new File([res.blob], f.name, {type:f.type, lastModified:Date.now()});
                        }
                        file = await IT.resize(file, {maxWidth: opts.maxWidth, format: opts.format});
                    }
                    out.push(file);
                }
                return out;
            }
            // ✂ on a stock photo: crop on the thumbnail, the server applies the same area to the full-size photo
            async function stockCropThenImport(p, card){
                if(!window.ImageTools || !pickerSlot) return;
                const status=byId('stock-status');
                try{
                    const res = await window.ImageTools.cropDialog({src:p.thumb, title:'Обрезка по превью — та же область возьмётся из полного фото', allowRotate:false, allowSkip:false, wantBlob:false});
                    if(res && res.fractions) await stockImport(p, card, res.fractions);
                }catch(e){ status.textContent='Ошибка: '+(e.message||e); }
            }
            async function stockImport(p, card, crop){
                if(!pickerSlot) return;
                if(card._done || card._importing) return;   // multi mode keeps the modal open: no double imports
                card._importing = true;
                const slotName = pickerSlot;
                const status=byId('stock-status');
                card.classList.add('c-stock-card-busy');
                status.classList.remove('c-picker-status-error');
                status.textContent='Загружаю фото на сайт…';
                const fd=new FormData();
                fd.append('csrf_token', getCsrf()); fd.append('page_id', pageId); fd.append('section', pickerSlot); fd.append('photo_id', p.id);
                const po = pickerOpts();
                fd.append('max_width', po.maxWidth); fd.append('format', po.format);
                if(crop) fd.append('crop', [crop.x, crop.y, crop.w, crop.h].map(v=>v.toFixed(5)).join(','));
                try{
                    const r = await fetch(baseUrl+'/admin/media/stock-import', {method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':getCsrf()}});
                    const j = await r.json().catch(()=>({}));
                    if(!r.ok || j.success===false) throw new Error(j.message||('HTTP '+r.status));
                    if(!slotsData[pickerSlot]) slotsData[pickerSlot]=[];
                    slotsData[pickerSlot].push({media_id:j.media_id, id:j.media_id, filename:j.filename, original_name:j.original_name, section:pickerSlot});
                    render();
                    if (isMultiSlot(slotName)) {
                        // gallery slot: stay open, mark the photo as added
                        card._done = true;
                        card.classList.remove('c-stock-card-busy');
                        markPicked(card);
                        const h=card.querySelector('.picker-hover span'); if(h) h.textContent='Добавлено ✓';
                        status.textContent = 'Добавлено: '+pickerAdded+' — выберите ещё или нажмите «Done»';
                    } else {
                        closeMediaPicker();
                    }
                } catch(e){
                    status.textContent='Не удалось добавить фото: '+(e.message||e);
                    status.classList.add('c-picker-status-error');
                    card.classList.remove('c-stock-card-busy');
                } finally {
                    card._importing = false;
                }
            }
            document.querySelectorAll('[data-picker-tab]').forEach(b=>b.addEventListener('click', ()=>window.pickerSetTab(b.dataset.pickerTab)));
            const stockInput = byId('stock-search');
            if(byId('stock-search-btn')) byId('stock-search-btn').addEventListener('click', ()=>{ stockQuery=stockInput.value.trim(); stockLoad(true); });
            if(stockInput) stockInput.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); stockQuery=stockInput.value.trim(); stockLoad(true); }});
            if(byId('stock-more')) byId('stock-more').addEventListener('click', ()=>{ stockPage++; stockLoad(false); });
            if(byId('stock-alt')) byId('stock-alt').addEventListener('click', e=>{
                const b = e.target.closest('[data-stock-q]');
                if(b){ stockQuery = b.getAttribute('data-stock-q'); stockInput.value = stockQuery; stockLoad(true); }
            });

            window.pickerAttach = async function(mediaId, btn){
                if (!pickerSlot) return;
                if (btn && btn._busy) return;   // in multi mode the modal stays open: ignore double clicks while attaching
                if (btn) btn._busy = true;
                const slotName = pickerSlot;
                const isBtn = btn && btn.tagName === 'BUTTON';
                if(isBtn){ btn.disabled=true; const old=btn.textContent; btn.textContent='…'; btn._old=old; }
                else if(btn && btn.classList && btn.classList.contains('picker-hover')){ /* card overlay, ignore */ }
                const fd=new FormData();
                fd.append('csrf_token', getCsrf());
                fd.append('page_id', pageId);
                fd.append('media_id', mediaId);
                fd.append('section', pickerSlot);
                try{
                    const r=await fetch(baseUrl+'/admin/media/attach', {method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':getCsrf()}});
                    const j=await r.json().catch(()=>({}));
                    if(!r.ok || j.success===false) throw new Error(j.message||('HTTP '+r.status));
                    // update local slotsData without reload — fetch attached row for thumb
                    try{
                        const infoR = await fetch(baseUrl+'/admin/media/info?id='+encodeURIComponent(mediaId), {headers:{'Accept':'application/json'}});
                        const infoJ = await infoR.json().catch(()=>null);
                        const mediaRow = infoJ && infoJ.media ? infoJ.media : {id:mediaId, filename:''};
                        // try to get filename from picker grid data if info missing
                        if(!mediaRow.filename){
                            const url = baseUrl + '/admin/media/list?filter=all&page_id='+pageId+'&limit=200';
                            // fallback: find in current grid
                        }
                        // push to slotsData
                        if(!slotsData[pickerSlot]) slotsData[pickerSlot]=[];
                        const exists = slotsData[pickerSlot].some(x=> String(x.media_id||x.id)===String(mediaId));
                        if(!exists){
                            // construct minimal row matching getPageMedia shape
                            slotsData[pickerSlot].push({media_id: mediaId, id: mediaId, filename: mediaRow.filename || '', original_name: mediaRow.original_name || mediaRow.filename || ('#'+mediaId), section: pickerSlot});
                            // if filename empty, fetch again via list
                            if(!mediaRow.filename){
                                const lr = await fetch(baseUrl+'/admin/media/list?filter=all&page_id='+pageId+'&limit=200', {headers:{'Accept':'application/json'}});
                                const lj = await lr.json().catch(()=>({}));
                                const found = (lj.media||[]).find(x=> String(x.id)===String(mediaId));
                                if(found){ const last = slotsData[pickerSlot][slotsData[pickerSlot].length-1]; last.filename = found.filename; last.original_name = found.original_name; }
                            }
                        }
                    }catch(_){ /* ignore, still render with id only */ }
                    render();
                    if (isMultiSlot(slotName)) {
                        markPicked(btn);   // gallery slot: stay open so more images can be picked
                    } else {
                        pickerLoad(); // refresh picker attached badges
                        closeMediaPicker();
                    }
                    const t=document.createElement('div');
                    t.textContent='✅ Attached to {{media.'+slotName+'}}';
                    t.className='c-toast c-toast-ok';
                    document.body.appendChild(t); requestAnimationFrame(()=>t.style.opacity='1'); setTimeout(()=>{t.style.opacity='0'; setTimeout(()=>t.remove(),240);}, 1700);
                }catch(e){
                    alert('Attach failed: '+(e.message||e));
                    if(isBtn && btn){ btn.disabled=false; btn.textContent=btn._old||'Attach'; }
                } finally {
                    if(btn) btn._busy = false;
                    if(isBtn && btn && btn.disabled && btn.textContent==='…'){ btn.disabled=false; btn.textContent=btn._old||'Attach'; }
                }
            };
            window.pickerUpload = async function(input){
                const files=input.files;
                if(!files||!files.length) return;
                if(!pickerSlot){ alert('Pick a slot first'); return; }
                const fd=new FormData();
                const prepared = await prepareUploads(files);   // optional crop dialog + resize/convert (picker toolbar)
                if(!prepared){ input.value=''; return; }        // cancelled in the crop dialog
                prepared.forEach(f=>fd.append('files[]', f, f.name));
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
                    // update slots panel without reload: refetch attached for this slot
                    try{
                        const ar = await fetch(baseUrl+'/admin/media/list?filter=attached&page_id='+pageId, {headers:{'Accept':'application/json'}});
                        const aj = await ar.json().catch(()=>({}));
                        if(aj.success && Array.isArray(aj.media)){
                            const forSlot = aj.media.filter(x=> String(x.section||'')===String(pickerSlot));
                            // map to slotsData shape expected by render()
                            slotsData[pickerSlot] = forSlot.map(x=>({media_id: x.media_id||x.id, id: x.media_id||x.id, filename: x.filename, original_name: x.original_name, section: x.section}));
                            render();
                        }
                    }catch(_){}
                    await pickerLoad();
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

// (3) editor boot
if (window.tinymce) tinymce.init({
    selector: '.tinymce',
    height: 400,
    menubar: false,
    plugins: 'fullscreen code',
    toolbar: 'fullscreen code',
     content_css: [
        (window.baseUrl || '') + '/css/pages.min.css',
        (window.baseUrl || '') + '/css/components.min.css'
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
                rotationSelectionGroup.classList.toggle('hidden', this.value !== 'manual');
            }
        });
    }
});
