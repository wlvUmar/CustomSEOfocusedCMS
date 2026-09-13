// public/js/admin/component-lib.js — live preview + token chips + fluid stage + tabs + auto demo
(function(){
  var slug = window.COMPONENT_SLUG || '';
  var cssEl = document.getElementById('css_body');
  var htmlEl = document.getElementById('html_demo');
  var frame = document.getElementById('preview-frame');
  var stage = document.getElementById('preview-stage');
  var statusEl = document.getElementById('preview-status');
  var overflowBadge = document.getElementById('overflow-badge');
  var autoBadge = document.getElementById('preview-auto-badge');
  var widthInput = document.getElementById('preview-width');
  var widthLabel = document.getElementById('preview-width-label');
  var sectionWrap = document.getElementById('preview-section-wrap');
  if (!cssEl || !frame) return;

  var lastAutoDemo = '';

  var cm = null;
  var htmlCm = null;
  if (window.CodeMirror) {
    try {
      cm = CodeMirror.fromTextArea(cssEl, { mode:'css', lineNumbers:true, lint:true, gutters:["CodeMirror-lint-markers"] });
      cm.on('change', schedule);
      htmlCm = CodeMirror.fromTextArea(htmlEl, { mode:'htmlmixed', lineNumbers:true });
      htmlCm.on('change', schedule);
    } catch(e) {
      cm = null;
      cssEl.addEventListener('input', schedule);
      htmlEl.addEventListener('input', schedule);
    }
  } else {
    cssEl.addEventListener('input', schedule);
    htmlEl.addEventListener('input', schedule);
  }

  document.querySelectorAll('.token-chip--clickable').forEach(function(btn){
    btn.addEventListener('click', function(){
      var tok = btn.getAttribute('data-token');
      var ins = 'var(' + tok + ')';
      if (cm) { cm.replaceSelection(ins); cm.focus(); schedule(); }
      else { insertAtCursor(cssEl, ins); schedule(); }
    });
  });

  function insertAtCursor(el, text){
    var s = el.selectionStart, e = el.selectionEnd;
    el.value = el.value.slice(0,s) + text + el.value.slice(e);
    el.selectionStart = el.selectionEnd = s + text.length;
    el.focus();
  }

  function getCss(){ return cm ? cm.getValue() : cssEl.value; }
  function getHtml(){ return htmlCm ? htmlCm.getValue() : (htmlEl ? htmlEl.value : ''); }

  var timer = null;
  function schedule(){
    if (statusEl) statusEl.textContent = 'typing…';
    clearTimeout(timer);
    timer = setTimeout(render, 500);
  }

  function wrapDemo(html){
    var inner = html;
    if (sectionWrap && sectionWrap.checked) inner = '<div class="c-section">' + inner + '</div>';
    return inner;
  }

  function render(){
    if (statusEl) statusEl.textContent = 'rendering…';
    var css = getCss();
    var rawHtml = getHtml();
    var isEmpty = rawHtml.trim() === '';
    var htmlDemo = isEmpty ? '' : wrapDemo(rawHtml);
    fetch(window.baseUrl + '/admin/components/preview', {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' },
      body: JSON.stringify({ slug: slug, css_body: css, html_demo: htmlDemo })
    }).then(function(r){ return r.json(); }).then(function(j){
      if (!j.success) { if (statusEl) statusEl.textContent = j.message || 'error'; return; }
      lastAutoDemo = j.demo || '';
      frame.srcdoc = j.html;
      if (statusEl) statusEl.textContent = j.chars + ' chars' + (j.auto ? ' · auto demo' : '');
      if (autoBadge) autoBadge.classList.toggle('hidden', !j.auto);
      frame.onload = function(){
        try {
          var doc = frame.contentDocument;
          if (!doc) return;
          var w = doc.documentElement.scrollWidth, cw = doc.documentElement.clientWidth;
          var over = w > cw + 2;
          if (overflowBadge) overflowBadge.classList.toggle('hidden', !over);
        } catch(e){}
      };
    }).catch(function(){ if (statusEl) statusEl.textContent = 'preview failed'; });
  }

  // width presets — fluid vs fixed stage width
  document.querySelectorAll('.preview-presets [data-w]').forEach(function(b){
    b.addEventListener('click', function(){
      var v = b.getAttribute('data-w');
      if (v === 'fluid') {
        if (widthInput) widthInput.value = widthInput.max;
        updateWidth('fluid');
      } else {
        if (widthInput) widthInput.value = v;
        updateWidth(v);
      }
      schedule();
    });
  });
  if (widthInput) widthInput.addEventListener('input', function(){ updateWidth(widthInput.value); });
  if (sectionWrap) sectionWrap.addEventListener('change', schedule);

  function updateWidth(val){
    var all = document.querySelectorAll('.preview-presets [data-w]');
    all.forEach(function(x){ x.classList.remove('is-active'); });
    var toActivate = null;
    if (val === 'fluid') {
      toActivate = document.querySelector('.preview-presets [data-w="fluid"]');
      if (stage) stage.style.width = '100%';
      if (widthLabel) widthLabel.textContent = 'Fluid';
      if (widthInput) widthInput.value = widthInput.max;
    } else {
      var w = String(val);
      if (stage) stage.style.width = w + 'px';
      if (widthLabel) widthLabel.textContent = w + 'px';
      toActivate = document.querySelector('.preview-presets [data-w="' + w + '"]');
      if (!toActivate && widthInput) {
        // custom slider value — no preset active
      }
    }
    if (toActivate) toActivate.classList.add('is-active');
    setTimeout(function(){
      try {
        var doc = frame.contentDocument; if(!doc) return;
        var over = doc.documentElement.scrollWidth > doc.documentElement.clientWidth + 2;
        if (overflowBadge) overflowBadge.classList.toggle('hidden', !over);
      } catch(e){}
    }, 100);
  }

  // tabs
  document.querySelectorAll('.tab-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var tab = btn.getAttribute('data-tab');
      document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('is-active'); b.setAttribute('aria-selected','false'); });
      btn.classList.add('is-active'); btn.setAttribute('aria-selected','true');
      document.querySelectorAll('.tab-pane').forEach(function(p){
        p.classList.toggle('is-active', p.getAttribute('data-pane') === tab);
      });
      if (cm) setTimeout(function(){ cm.refresh(); }, 30);
      if (htmlCm) setTimeout(function(){ htmlCm.refresh(); }, 30);
    });
  });

  // use auto demo
  var useBtn = document.getElementById('use-auto-demo');
  var autoHint = document.getElementById('auto-demo-hint');
  if (useBtn) useBtn.addEventListener('click', function(){
    if (!lastAutoDemo) { render(); return; }
    // lastAutoDemo is already unwrapped? It is the demo HTML (maybe wrapped in c-section by wrapDemo). Unwrap one c-section layer for clean starting point.
    var toInsert = lastAutoDemo;
    // strip outer c-section wrapper added by wrapDemo for editing convenience
    var m = toInsert.match(/^<div class="c-section">(.*)<\/div>$/s);
    if (m) toInsert = m[1];
    if (htmlCm) { htmlCm.setValue(toInsert); htmlCm.refresh(); }
    else if (htmlEl) htmlEl.value = toInsert;
    // switch to html tab
    var htmlTab = document.querySelector('.tab-btn[data-tab="html"]');
    if (htmlTab) htmlTab.click();
    if (autoHint) { autoHint.classList.remove('hidden'); setTimeout(function(){ autoHint.classList.add('hidden'); }, 4000); }
    schedule();
  });

  // init: fluid full width
  updateWidth('fluid');
  render();
})();
