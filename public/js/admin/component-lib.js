// public/js/admin/component-lib.js — live preview + token chips + responsive lab
(function(){
  var slug = window.COMPONENT_SLUG || '';
  var cssEl = document.getElementById('css_body');
  var htmlEl = document.getElementById('html_demo');
  var frame = document.getElementById('preview-frame');
  var statusEl = document.getElementById('preview-status');
  var wrap = document.getElementById('preview-frame-wrap');
  var widthInput = document.getElementById('preview-width');
  var widthLabel = document.getElementById('preview-width-label');
  var overflowBadge = document.getElementById('overflow-badge');
  var sectionWrap = document.getElementById('preview-section-wrap');
  var neighbors = document.getElementById('preview-neighbors');
  if (!cssEl || !frame) return;

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
    if (neighbors && neighbors.checked) inner = '<div class="c-section"><div class="c-card">Prev neighbor</div></div>' + inner + '<div class="c-section"><div class="c-card">Next neighbor</div></div>';
    return inner;
  }

  function render(){
    if (statusEl) statusEl.textContent = 'rendering…';
    var css = getCss();
    var htmlDemo = wrapDemo(getHtml());
    fetch(window.baseUrl + '/admin/components/preview', {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': window.csrfToken || '' },
      body: JSON.stringify({ slug: slug, css_body: css, html_demo: htmlDemo })
    }).then(function(r){ return r.json(); }).then(function(j){
      if (!j.success) { if (statusEl) statusEl.textContent = j.message || 'error'; return; }
      frame.srcdoc = j.html;
      if (statusEl) statusEl.textContent = j.chars + ' chars';
      // overflow check after load
      frame.onload = function(){
        try {
          var doc = frame.contentDocument;
          if (!doc) return;
          var w = doc.documentElement.scrollWidth, cw = doc.documentElement.clientWidth;
          var over = w > cw + 2;
          if (overflowBadge) overflowBadge.classList.toggle('hidden', !over);
        } catch(e){}
      };
    }).catch(function(e){ if (statusEl) statusEl.textContent = 'preview failed'; });
  }

  // presets
  document.querySelectorAll('.preview-presets [data-w]').forEach(function(b){
    b.addEventListener('click', function(){ if (widthInput) { widthInput.value = b.getAttribute('data-w'); updateWidth(); schedule(); }});
  });
  if (widthInput) widthInput.addEventListener('input', function(){ updateWidth(); });
  if (sectionWrap) sectionWrap.addEventListener('change', schedule);
  if (neighbors) neighbors.addEventListener('change', schedule);
  function updateWidth(){
    var w = widthInput.value;
    if (widthLabel) widthLabel.textContent = w + 'px';
    frame.style.width = w + 'px';
    // check overflow after resize
    setTimeout(function(){
      try {
        var doc = frame.contentDocument; if(!doc) return;
        var over = doc.documentElement.scrollWidth > doc.documentElement.clientWidth + 2;
        if (overflowBadge) overflowBadge.classList.toggle('hidden', !over);
      } catch(e){}
    }, 100);
  }
  updateWidth();
  render();
})();
