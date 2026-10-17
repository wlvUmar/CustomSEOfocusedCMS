// Extracted from views/admin/media/index.php — reads #media-boot JSON blob (data-boot).
function getBoot() {
  try {
    const el = document.getElementById('media-boot');
    if (el && el.textContent.trim()) return JSON.parse(el.textContent);
  } catch (e) { /* fall through */ }
  return window.MediaBoot || {};
}
function filterMedia() {
    const search = document.getElementById('search').value.toLowerCase();
    const cards = document.querySelectorAll('.media-card');
    
    cards.forEach(card => {
        const name = card.dataset.name.toLowerCase();
        card.style.display = name.includes(search) ? '' : 'none';
    });
}

function getCsrf() {
  return document.getElementById('csrf-token')?.value || document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken || getBoot().csrf || '';
}
function getAttachContext() {
  const p = new URLSearchParams(location.search);
  return { pageId: p.get('page_id'), slot: p.get('attach_slot') };
}
function uploadFiles() {
  const input = document.getElementById('upload-input');
  const files = input?.files;
  if (!files || !files.length) return;

  const formData = new FormData();
  Array.from(files).forEach(file => formData.append('files[]', file));
  formData.append('csrf_token', getCsrf());
  const ctx = getAttachContext();
  if (ctx.pageId) formData.append('page_id', ctx.pageId);
  if (ctx.slot) formData.append('section', ctx.slot);

  fetch((window.baseUrl || '') + '/admin/media/bulk-upload', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-Token': getCsrf()
    }
  })
  .then(async (r) => {
    const ct = r.headers.get('content-type') || '';
    const text = await r.text(); // read once
    if (!r.ok) {
      console.error('Upload failed HTTP', r.status, r.statusText, 'CT:', ct, 'Body:', text);
      throw new Error(`HTTP ${r.status}. Response: ${text.slice(0, 300)}`);
    }
    if (!ct.includes('application/json')) {
      console.error('Expected JSON, got:', ct, text);
      throw new Error(`Expected JSON but got ${ct}. Body: ${text.slice(0, 300)}`);
    }
    let data;
    try { data = JSON.parse(text); } catch (e) { console.error('Invalid JSON:', text); throw new Error(`Invalid JSON. Body: ${text.slice(0, 300)}`); }
    return data;
  })
  .then((data) => {
    input.value = '';
    if (data.success || data.uploaded) {
      location.reload();
    } else {
      alert('Upload: ' + (data.message || 'no files uploaded'));
      input.value = '';
    }
  })
  .catch((err) => {
    console.log(err);
    alert('Upload failed: ' + err.message);
    if (input) input.value = '';
  });
}

function regenerateVariants() {
    const btn = document.getElementById('regen-variants-btn');
    if (!btn) return;
    if (!confirm('Regenerate responsive image variants for all uploads? This can take a few minutes.')) return;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Generating...';
    const formData = new FormData();
    formData.append('csrf_token', getCsrf());

    fetch((window.baseUrl || '') + '/admin/media/regenerate-variants', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-Token': getCsrf()
        }
    })
    .then(async (r) => {
        const ct = r.headers.get('content-type') || '';
        const text = await r.text();

        if (!r.ok) {
            throw new Error(`HTTP ${r.status}. ${text.slice(0, 300)}`);
        }
        if (!ct.includes('application/json')) {
            throw new Error(`Expected JSON but got ${ct}. ${text.slice(0, 300)}`);
        }
        return JSON.parse(text);
    })
    .then((data) => {
        if (!data.success) {
            throw new Error(data.message || 'Variant generation failed.');
        }
        const webpText = data.webp ? 'WebP: enabled' : 'WebP: not available';
        alert(`Variants generated.\nProcessed: ${data.processed}\nSkipped: ${data.skipped}\nErrors: ${data.errors}\n${webpText}`);
    })
    .catch((err) => {
        alert('Regeneration failed: ' + err.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (window.feather && typeof feather.replace === 'function') {
            feather.replace();
        }
    });
}

function resetAttachmentFields() {
    document.getElementById('attach-section').value = 'content';
    document.getElementById('attach-alt-ru').value = '';
    document.getElementById('attach-alt-uz').value = '';
    document.getElementById('attach-alignment').value = 'center';
    document.getElementById('attach-width').value = '';
}

function applyAttachmentFields(attachment) {
    if (!attachment) {
        resetAttachmentFields();
        return;
    }
    document.getElementById('attach-section').value = attachment.section || 'content';
    document.getElementById('attach-alt-ru').value = attachment.alt_text_ru || '';
    document.getElementById('attach-alt-uz').value = attachment.alt_text_uz || '';
    document.getElementById('attach-alignment').value = attachment.alignment || 'center';
    document.getElementById('attach-width').value = attachment.width || '';
}

function fetchAttachment(mediaId, pageId) {
    let url = '/admin/media/attachment?media_id=' + encodeURIComponent(mediaId);
    if (pageId) {
        url += '&page_id=' + encodeURIComponent(pageId);
    }
    return fetch(url, {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .catch(() => null);
}

function showAttachModal(mediaId) {
    document.getElementById('attach-media-id').value = mediaId;
    document.getElementById('attach-page-id').value = '';
    document.getElementById('attach-page-selected').textContent = 'No page selected';
    resetAttachmentFields();
    document.getElementById('attach-modal').classList.add('active');
    
    fetchAttachment(mediaId)
        .then((data) => {
            if (!data || !data.success || !data.attachment) return;
            const attachment = data.attachment;
            applyAttachmentFields(attachment);
            if (attachment.page_id) {
                document.getElementById('attach-page-id').value = attachment.page_id;
                document.getElementById('attach-page-selected').textContent = attachment.page_slug || 'Selected page';
            }
        });
}

function closeAttachModal() {
    document.getElementById('attach-modal').classList.remove('active');
}

function attachMedia() {
    const mediaId = document.getElementById('attach-media-id').value;
    const pageId = document.getElementById('attach-page-id').value;
    const section = document.getElementById('attach-section').value;
    const altRu = document.getElementById('attach-alt-ru').value;
    const altUz = document.getElementById('attach-alt-uz').value;
    const alignment = document.getElementById('attach-alignment').value;
    const width = document.getElementById('attach-width').value;
    if (!pageId) { alert('Please select a page'); return; }
    const formData = new FormData();
    formData.append('csrf_token', getCsrf());
    formData.append('media_id', mediaId);
    formData.append('page_id', pageId);
    formData.append('section', section);
    formData.append('alt_text_ru', altRu);
    formData.append('alt_text_uz', altUz);
    formData.append('alignment', alignment);
    if (width) formData.append('width', width);
    fetch((window.baseUrl || '') + '/admin/media/attach', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-Token': getCsrf()
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeAttachModal();
            const sectionMessages = {
                'hero': '✅ Media attached to HERO section!\n\nIt will automatically appear at the top of the page.',
                'gallery': '✅ Media attached to GALLERY section!\n\nIt will automatically appear in the gallery grid.',
                'banner': '✅ Media attached to BANNER section!\n\nIt will automatically appear as a banner.',
                'content': '✅ Media attached to CONTENT section!\n\nYou can now use:\n{{media:' + mediaId + '}}\n\nOr it will appear with other content media.'
            };
            alert(sectionMessages[section] || 'Media attached successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}

function copyMediaId(id) {
    navigator.clipboard.writeText(id);
    alert('Media ID copied: ' + id);
}

function copyPlaceholder(id) {
    const placeholder = '{{media:' + id + '}}';
    navigator.clipboard.writeText(placeholder);
    alert('Copied: ' + placeholder);
}

function copyMediaLink(button) {
    const link = button?.dataset?.link;
    if (!link) return;
    navigator.clipboard.writeText(link);
    alert('Copied media link:\n' + link);
}

function deleteMedia(id, usageCount) {
    // Debug: log what we're receiving
    console.log('Raw id:', id, 'Raw usageCount:', usageCount);
    
    // Parse ID safely
    const mediaId = parseInt(id, 10);
    
    console.log('Parsed mediaId:', mediaId, 'Type:', typeof mediaId);
    
    if (isNaN(mediaId) || mediaId <= 0) {
        console.error('Invalid mediaId detected:', mediaId);
        alert('Invalid media ID: ' + id);
        return;
    }
    
    const usage = parseInt(usageCount, 10) || 0;
    
    if (usage > 0) {
        if (!confirm(`This media is used on ${usage} page(s). Delete anyway?`)) {
            return;
        }
    } else {
        if (!confirm('Delete this media?')) return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', getCsrf());
    formData.append('id', mediaId);
    if (usage > 0) formData.append('force', '1');
    fetch((window.baseUrl || '') + '/admin/media/delete', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-Token': getCsrf()
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}

document.addEventListener('click', (event) => {
    const el = event.target.closest('[data-action]');
    if (!el) return;
    const action = el.getAttribute('data-action');
    if (action === 'open-upload') {
        event.preventDefault();
        document.getElementById('upload-input')?.click();
    } else if (action === 'regen-variants') {
        event.preventDefault();
        regenerateVariants();
    } else if (action === 'quick-attach') {
        event.preventDefault();
        quickAttach(parseInt(el.getAttribute('data-media-id'), 10));
    } else if (action === 'show-attach') {
        event.preventDefault();
        showAttachModal(parseInt(el.getAttribute('data-media-id'), 10));
    } else if (action === 'copy-link') {
        event.preventDefault();
        copyMediaLink(el);
    } else if (action === 'delete-media') {
        event.preventDefault();
        deleteMedia(el.getAttribute('data-media-id'), el.getAttribute('data-usage') || 0);
    } else if (action === 'close-attach') {
        event.preventDefault();
        closeAttachModal();
    } else if (action === 'attach-media') {
        event.preventDefault();
        attachMedia();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('search')?.addEventListener('keyup', filterMedia);
    document.getElementById('upload-input')?.addEventListener('change', uploadFiles);
});

document.addEventListener('click', (event) => {
    const target = event.target.closest('.page-pick');
    if (!target) return;
    event.preventDefault();
    event.stopPropagation();
    const pageId = target.getAttribute('data-page-id');
    const label = target.getAttribute('data-page-label') || '';
    document.getElementById('attach-page-id').value = pageId;
    document.getElementById('attach-page-selected').textContent = label || 'No page selected';
    const mediaId = document.getElementById('attach-media-id').value;
    if (mediaId && pageId) {
        fetchAttachment(mediaId, pageId)
            .then((data) => {
                if (!data || !data.success || !data.attachment) {
                    resetAttachmentFields();
                    return;
                }
                applyAttachmentFields(data.attachment);
            });
    }
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('.media-detach');
    if (!button) return;
    event.preventDefault();
    const mediaId = button.getAttribute('data-media-id');
    const pageId = button.getAttribute('data-page-id');
    const section = button.getAttribute('data-section');
    if (!mediaId || !pageId) return;
    if (!confirm('Detach this media from the page?')) return;
    
    const formData = new FormData();
    formData.append('csrf_token', getCsrf());
    formData.append('media_id', mediaId);
    formData.append('page_id', pageId);
    if (section) formData.append('section', section);
    fetch((window.baseUrl || '') + '/admin/media/detach', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-Token': getCsrf()
        }
    })
    .then(async (r) => {
        const ct = r.headers.get('content-type') || '';
        const text = await r.text();
        if (!r.ok) {
            throw new Error(`HTTP ${r.status}. ${text.slice(0, 300)}`);
        }
        if (!ct.includes('application/json')) {
            throw new Error(`Expected JSON but got ${ct}. ${text.slice(0, 300)}`);
        }
        return JSON.parse(text);
    })
    .then((data) => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
});

function quickAttach(mediaId) {
    const ctx = getAttachContext();
    const pageId = ctx.pageId;
    const slot = ctx.slot || 'content';
    if (!pageId) { showAttachModal(mediaId); return; }
    if (!confirm(`Attach image #${mediaId} to page #${pageId} slot {{media.${slot}}}?`)) return;
    const fd = new FormData();
    fd.append('csrf_token', getCsrf());
    fd.append('media_id', mediaId);
    fd.append('page_id', pageId);
    fd.append('section', slot);
    fetch((window.baseUrl || '') + '/admin/media/attach', { method:'POST', body:fd, credentials:'same-origin', headers:{'Accept':'application/json','X-CSRF-Token':getCsrf()} })
    .then(r=>r.json()).then(d=>{
        if (d.success) {
            alert('✅ Attached to {{media.'+slot+'}}');
            if (window.opener) { try{ window.opener.postMessage({type:'media-attached', mediaId, pageId, slot}, '*'); }catch(e){} }
            location.reload();
        } else alert('Attach failed: '+(d.message||'unknown'));
    }).catch(e=>alert('Attach failed: '+e.message));
}
// Prefill from page editor: ?page_id=123&attach_slot=card_fridges (banner already server-rendered)
(function(){
    const params = new URLSearchParams(location.search);
    const slot = params.get('attach_slot');
    const pageId = params.get('page_id');
    if(!slot && !pageId) return;
    const origShow = window.showAttachModal;
    window.showAttachModal = function(mediaId){
        origShow(mediaId);
        try{
            if(slot) document.getElementById('attach-section').value = slot;
            if(pageId){
                document.getElementById('attach-page-id').value = pageId;
                fetch((window.baseUrl || '') + '/admin/media/attachment?media_id='+encodeURIComponent(mediaId)+'&page_id='+encodeURIComponent(pageId), {headers:{'Accept':'application/json'}})
                    .then(r=>r.json()).then(d=>{
                        if(d && d.attachment && d.attachment.page_slug){
                            document.getElementById('attach-page-selected').textContent = d.attachment.page_slug + ' (#'+pageId+')';
                        } else {
                            document.getElementById('attach-page-selected').textContent = 'Page #'+pageId+' (slot: '+slot+')';
                        }
                    }).catch(()=>{
                        document.getElementById('attach-page-selected').textContent = 'Page #'+pageId+' (slot: '+slot+')';
                    });
            }
        }catch(e){}
    };
    // Ensure feather icons in new quick-attach buttons render
    if (window.feather) try{ feather.replace(); }catch(e){}
})();
