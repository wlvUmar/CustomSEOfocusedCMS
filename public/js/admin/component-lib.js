(function(){
  var slug = window.COMPONENT_SLUG || '';
  var category = window.COMPONENT_CATEGORY || 'utilities';
  var cssEl = document.getElementById('css_body');
  var htmlEl = document.getElementById('html_demo');
  var frame = document.getElementById('preview-frame');
  var stage = document.getElementById('preview-stage');
  var wrap = document.querySelector('.preview-frame-wrap');
  var statusEl = document.getElementById('preview-status');
  var overflowBadge = document.getElementById('overflow-badge');
  var autoBadge = document.getElementById('preview-auto-badge');
  var issuesEl = document.getElementById('css-issues');
  var sizeEl = document.getElementById('css-size');
  var widthInput = document.getElementById('preview-width');
  var widthLabel = document.getElementById('preview-width-label');
  var sectionWrap = document.getElementById('preview-section-wrap');
  var dirtyDot = document.getElementById('dirty-dot');
  var modBar = document.getElementById('mod-bar');
  var modPills = document.getElementById('mod-pills');
  var partChips = document.getElementById('part-chips');
  var form = document.getElementById('component-form');
  if (!cssEl || !frame) return;

  var initCss = cssEl.value;
  var initHtml = htmlEl ? htmlEl.value : '';
  var lastDoc = '';
  var lastDemo = '';
  var lastAutoDemo = '';
  var activeMods = {};
  var bg = 'light';
  var draftKey = 'cmp-draft-' + slug;

  function mkEditor(el, opts){
    if (!window.CodeMirror || !el) return null;
    try { return CodeMirror.fromTextArea(el, opts); } catch(e){ return null; }
  }
  var cm = mkEditor(cssEl, { mode:'css', lineNumbers:true, lint:true, gutters:['CodeMirror-lint-markers'],
    styleActiveLine:true, matchBrackets:true, autoCloseBrackets:true,
    extraKeys:{ 'Ctrl-Space':'autocomplete', 'Ctrl-/':'toggleComment' } });
  var htmlCm = mkEditor(htmlEl, { mode:'htmlmixed', lineNumbers:true,
    styleActiveLine:true, matchBrackets:true, autoCloseBrackets:true, autoCloseTags:true,
    extraKeys:{ 'Ctrl-Space':'autocomplete', 'Ctrl-/':'toggleComment' } });

  function getCss(){ return cm ? cm.getValue() : cssEl.value; }
  function getHtml(){ return htmlCm ? htmlCm.getValue() : (htmlEl ? htmlEl.value : ''); }
  function setCss(v){ if (cm) cm.setValue(v); else cssEl.value = v; }
  function setHtml(v){ if (htmlCm) htmlCm.setValue(v); else if (htmlEl) htmlEl.value = v; }
  function insertCss(t){ if (cm){ cm.replaceSelection(t); cm.focus(); } else insertAt(cssEl, t); schedule(); }
  function insertHtml(t){ if (htmlCm){ htmlCm.replaceSelection(t); htmlCm.focus(); } else insertAt(htmlEl, t); schedule(); }
  function insertAt(el, t){
    if (!el) return;
    var s = el.selectionStart || 0, e = el.selectionEnd || 0;
    el.value = el.value.slice(0, s) + t + el.value.slice(e);
    el.selectionStart = el.selectionEnd = s + t.length;
    el.focus();
  }
  function copyText(t, btn){
    function done(){ if (!btn) return; var o = btn.textContent; btn.textContent = 'Copied'; setTimeout(function(){ btn.textContent = o; }, 1200); }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done).catch(done);
    else { var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch(e){} ta.remove(); done(); }
  }

  document.querySelectorAll('.token-chip--clickable').forEach(function(b){
    b.addEventListener('click', function(){ insertCss('var(' + b.getAttribute('data-token') + ')'); });
  });

  var SNIPPETS = [
    { label:'Root shell', body:function(s){ return '<div class="' + s + '">\n  <p class="' + s + '__kicker">Eyebrow</p>\n  <h2 class="' + s + '__title">Title goes here</h2>\n  <p class="' + s + '__text">Short supporting copy.</p>\n</div>'; } },
    { label:'Buttons row', body:function(s){ return '<div class="' + s + '__actions">\n  <a class="c-btn" href="#">Primary action</a>\n  <a class="c-btn c-btn--ghost" href="#">Secondary</a>\n</div>'; } },
    { label:'Media placeholder', body:function(s){ return '<div class="' + s + '__media">\n  <img src="https://picsum.photos/seed/' + s + '/800/450" alt="Demo image" loading="lazy">\n</div>'; } },
    { label:'Card trio', body:function(s){ return '<div class="' + s + '__grid">\n  <div class="' + s + '__card"><strong>One</strong><p>Sample body text.</p></div>\n  <div class="' + s + '__card"><strong>Two</strong><p>Sample body text.</p></div>\n  <div class="' + s + '__card"><strong>Three</strong><p>Sample body text.</p></div>\n</div>'; } },
    { label:'Kicker + title + lead', body:function(s){ return '<p class="' + s + '__kicker">Eyebrow</p>\n<h2 class="' + s + '__title">Sample heading</h2>\n<p class="' + s + '__text">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>'; } }
  ];
  var snippetSel = document.getElementById('snippet-select');
  if (snippetSel) {
    SNIPPETS.forEach(function(sn, i){
      var o = document.createElement('option');
      o.value = String(i); o.textContent = sn.label;
      snippetSel.appendChild(o);
    });
    snippetSel.addEventListener('change', function(){
      if (snippetSel.value === '') return;
      insertHtml(SNIPPETS[+snippetSel.value].body(slug) + '\n');
      snippetSel.value = '';
    });
  }

  function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;'); }
  function refreshOutline(){
    var css = getCss();
    var q = slug.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    var parts = [], mods = [];
    var re1 = new RegExp('\\.' + q + '__([a-z0-9\\-]+)', 'gi'), m;
    while ((m = re1.exec(css))) { var p = m[1].split('--')[0].toLowerCase(); if (parts.indexOf(p) < 0) parts.push(p); }
    var re2 = new RegExp('\\.' + q + '--([a-z0-9\\-]+)', 'gi');
    while ((m = re2.exec(css))) { var v = m[1].toLowerCase(); if (mods.indexOf(v) < 0) mods.push(v); }
    if (partChips) {
      partChips.innerHTML = '';
      if (!parts.length) partChips.textContent = '—';
      parts.slice(0, 14).forEach(function(p){
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'part-chip'; b.textContent = '__' + p;
        b.title = 'Insert <div class="' + slug + '__' + p + '">';
        b.addEventListener('click', function(){ insertHtml('<div class="' + slug + '__' + p + '">Sample ' + p + '</div>'); });
        partChips.appendChild(b);
        partChips.appendChild(document.createTextNode(' '));
      });
    }
    if (modBar && modPills) {
      modPills.innerHTML = '';
      mods.slice(0, 12).forEach(function(v){
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'mod-pill' + (activeMods[v] ? ' is-active' : '');
        b.textContent = '--' + v; b.setAttribute('data-mod', v);
        b.addEventListener('click', function(){
          activeMods[v] = !activeMods[v];
          b.classList.toggle('is-active', !!activeMods[v]);
          schedule();
        });
        modPills.appendChild(b);
        modPills.appendChild(document.createTextNode(' '));
      });
      Object.keys(activeMods).forEach(function(k){ if (mods.indexOf(k) < 0) delete activeMods[k]; });
      modBar.classList.toggle('hidden', !mods.length);
    }
  }

  var timer = null, draftTimer = null;
  function schedule(){
    if (statusEl) statusEl.textContent = 'typing…';
    markDirty();
    clearTimeout(timer);
    timer = setTimeout(render, 500);
    clearTimeout(draftTimer);
    draftTimer = setTimeout(saveDraft, 1000);
  }
  if (cm) cm.on('change', function(){ schedule(); refreshOutline(); updateCssMeta(); });
  else cssEl.addEventListener('input', function(){ schedule(); refreshOutline(); updateCssMeta(); });
  if (htmlCm) htmlCm.on('change', schedule);
  else if (htmlEl) htmlEl.addEventListener('input', schedule);

  function markDirty(){
    var dirty = getCss() !== initCss || getHtml() !== initHtml;
    if (dirtyDot) dirtyDot.classList.toggle('hidden', !dirty);
  }
  function updateCssMeta(){
    var n = getCss().length;
    if (sizeEl) sizeEl.textContent = (n / 1024).toFixed(1) + ' KB';
    setTimeout(function(){
      var errs = document.querySelectorAll('.CodeMirror-lint-marker-error').length;
      if (issuesEl) issuesEl.textContent = errs ? 'CSS: ' + errs + ' issue' + (errs > 1 ? 's' : '') : '';
    }, 600);
  }

  function saveDraft(){
    try {
      var c = getCss(), h = getHtml();
      if (c === initCss && h === initHtml) return;
      localStorage.setItem(draftKey, JSON.stringify({ css:c, html:h, ts:Date.now() }));
    } catch(e){}
  }
  (function checkDraft(){
    var banner = document.getElementById('draft-banner');
    if (!banner) return;
    try {
      var raw = localStorage.getItem(draftKey);
      if (!raw) return;
      var d = JSON.parse(raw);
      if (!d || (d.css === initCss && d.html === initHtml)) return;
      if (d.css === getCss() && d.html === getHtml()) { localStorage.removeItem(draftKey); return; }
      document.getElementById('draft-ts').textContent = new Date(d.ts).toLocaleString();
      banner.classList.remove('hidden');
      document.getElementById('draft-restore').addEventListener('click', function(){
        setCss(d.css || ''); setHtml(d.html || '');
        if (cm) cm.refresh(); if (htmlCm) htmlCm.refresh();
        refreshOutline(); updateCssMeta(); schedule(); banner.classList.add('hidden');
      });
      document.getElementById('draft-discard').addEventListener('click', function(){
        localStorage.removeItem(draftKey); banner.classList.add('hidden');
      });
    } catch(e){}
  })();

  function wrapDemo(html){
    return (sectionWrap && sectionWrap.checked) ? '<div class="c-section">' + html + '</div>' : html;
  }
  function render(){
    if (statusEl) statusEl.textContent = 'rendering…';
    var css = getCss();
    var rawHtml = getHtml();
    var mods = Object.keys(activeMods).filter(function(k){ return activeMods[k]; });
    fetch(window.baseUrl + '/admin/components/preview', {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' },
      body: JSON.stringify({ slug: slug, css_body: css, html_demo: rawHtml.trim() === '' ? '' : wrapDemo(rawHtml), mods: mods, bg: bg })
    }).then(function(r){ return r.json(); }).then(function(j){
      if (!j.success) { if (statusEl) statusEl.textContent = j.message || 'error'; return; }
      lastDoc = j.html; lastDemo = j.demo || ''; lastAutoDemo = j.auto ? (j.demo || '') : lastAutoDemo;
      frame.srcdoc = j.html;
      if (statusEl) statusEl.textContent = j.chars + ' chars' + (j.auto ? ' · auto demo' : '');
      if (autoBadge) autoBadge.classList.toggle('hidden', !j.auto);
      frame.onload = checkOverflow;
    }).catch(function(){ if (statusEl) statusEl.textContent = 'preview failed'; });
  }
  function checkOverflow(){
    try {
      var doc = frame.contentDocument;
      if (!doc) return;
      var over = doc.documentElement.scrollWidth > doc.documentElement.clientWidth + 2;
      if (overflowBadge) overflowBadge.classList.toggle('hidden', !over);
    } catch(e){}
  }

  document.querySelectorAll('.preview-presets [data-w]').forEach(function(b){
    b.addEventListener('click', function(){
      var v = b.getAttribute('data-w');
      if (v === 'fluid') updateWidth('fluid');
      else { if (widthInput) widthInput.value = v; updateWidth(v); }
    });
  });
  if (widthInput) widthInput.addEventListener('input', function(){ updateWidth(widthInput.value); });
  if (sectionWrap) sectionWrap.addEventListener('change', schedule);
  function updateWidth(val){
    document.querySelectorAll('.preview-presets [data-w]').forEach(function(x){ x.classList.remove('is-active'); });
    var toActivate = null;
    if (val === 'fluid') {
      toActivate = document.querySelector('.preview-presets [data-w="fluid"]');
      if (stage) stage.style.width = '100%';
      if (widthLabel) widthLabel.textContent = 'Fluid';
      if (widthInput) widthInput.value = widthInput.max;
    } else {
      if (stage) stage.style.width = String(val) + 'px';
      if (widthLabel) widthLabel.textContent = String(val) + 'px';
      toActivate = document.querySelector('.preview-presets [data-w="' + val + '"]');
    }
    if (toActivate) toActivate.classList.add('is-active');
    setTimeout(checkOverflow, 100);
  }

  document.querySelectorAll('.pg-bgseg [data-bg]').forEach(function(b){
    b.addEventListener('click', function(){
      bg = b.getAttribute('data-bg');
      document.querySelectorAll('.pg-bgseg [data-bg]').forEach(function(x){ x.classList.remove('is-active'); });
      b.classList.add('is-active');
      if (wrap) wrap.classList.toggle('is-dark', bg === 'dark');
      schedule();
    });
  });

  var openBtn = document.getElementById('preview-open');
  if (openBtn) openBtn.addEventListener('click', function(){
    if (!lastDoc) { render(); return; }
    var blob = new Blob([lastDoc], { type:'text/html' });
    window.open(URL.createObjectURL(blob), '_blank');
  });
  var copyPrevBtn = document.getElementById('preview-copy');
  if (copyPrevBtn) copyPrevBtn.addEventListener('click', function(){
    var m = lastDemo.match(/^<div class="c-section">(.*)<\/div>$/s);
    copyText(m ? m[1] : (lastDemo || getHtml()), copyPrevBtn);
  });

  function fmtCss(){
    var v = getCss();
    try {
      if (window.css_beautify) v = window.css_beautify(v, { indent:'  ', end_with_newline:true });
    } catch(e){}
    setCss(v); if (cm) cm.refresh(); schedule(); refreshOutline();
  }
  function fmtHtml(){
    var v = getHtml();
    try {
      if (window.html_beautify) v = window.html_beautify(v, { indent_size:2, wrap_line_length:120, end_with_newline:true });
    } catch(e){}
    setHtml(v); if (htmlCm) htmlCm.refresh(); schedule();
  }
  var cf = document.getElementById('css-format'); if (cf) cf.addEventListener('click', fmtCss);
  var hf = document.getElementById('html-format'); if (hf) hf.addEventListener('click', fmtHtml);
  var cc = document.getElementById('css-copy'); if (cc) cc.addEventListener('click', function(){ copyText(getCss(), cc); });
  var hc = document.getElementById('html-copy'); if (hc) hc.addEventListener('click', function(){ copyText(getHtml(), hc); });
  var hclear = document.getElementById('html-clear');
  if (hclear) hclear.addEventListener('click', function(){ setHtml(''); if (htmlCm) htmlCm.refresh(); schedule(); });
  var useBtn = document.getElementById('use-auto-demo');
  if (useBtn) useBtn.addEventListener('click', function(){
    if (!lastAutoDemo) { render(); return; }
    var m = lastAutoDemo.match(/^<div class="c-section">(.*)<\/div>$/s);
    setHtml(m ? m[1] : lastAutoDemo);
    if (htmlCm) htmlCm.refresh();
    schedule();
  });

  document.querySelectorAll('[data-rev-load]').forEach(function(b){
    b.addEventListener('click', function(){
      var id = b.getAttribute('data-rev-load');
      b.textContent = 'Loading…';
      fetch(window.baseUrl + '/admin/components/revision?id=' + encodeURIComponent(id), { headers:{ 'X-CSRF-TOKEN': window.csrfToken || '' } })
        .then(function(r){ return r.json(); })
        .then(function(j){
          b.textContent = 'Load';
          if (!j.success) return;
          setCss(j.css_body || ''); setHtml(j.html_demo || '');
          if (cm) cm.refresh(); if (htmlCm) htmlCm.refresh();
          refreshOutline(); updateCssMeta(); schedule();
          frame.scrollIntoView({ behavior:'smooth', block:'nearest' });
        }).catch(function(){ b.textContent = 'Load'; });
    });
  });
  document.querySelectorAll('[data-rev-restore]').forEach(function(b){
    b.addEventListener('click', function(){
      var id = b.getAttribute('data-rev-restore');
      if (!confirm('Restore revision ' + id + '? Current state is snapshotted first.')) return;
      document.getElementById('restore-revision-id').value = id;
      document.getElementById('restore-form').submit();
    });
  });

  if (form) form.addEventListener('submit', function(){
    if (cm) cm.save(); if (htmlCm) htmlCm.save();
    try { localStorage.removeItem(draftKey); } catch(e){}
  });
  document.addEventListener('keydown', function(e){
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (cm) cm.save(); if (htmlCm) htmlCm.save();
      if (form) form.requestSubmit();
    }
  });

  if (window.CodeMirror && CodeMirror.commands && CodeMirror.showHint) {
    CodeMirror.commands.autocomplete = function(c){
      var hint = c.getMode() === 'css' ? CodeMirror.hint.css : CodeMirror.hint.html;
      if (hint) CodeMirror.showHint(c, hint);
    };
  }
  updateWidth('fluid');
  refreshOutline();
  updateCssMeta();
  render();
})();
