// path: ./public/js/hold-morph.js  v5 — image-only, header stays, no flash/curtain
(function () {
  'use strict';

  var HOLD_MS = 300;
  var MORPH_MS = 640;
  var NAV_AT = 0.88;
  var MOVE_TOL = 14;
  var CIRC = 100.53096;

  var isReduced = false;
  try { isReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch(e){}

  function headerH(){
    var h = document.querySelector('header');
    return h ? h.getBoundingClientRect().height : 0;
  }
  function heroRect(){
    var hh = headerH();
    var vw = window.innerWidth;
    var h = Math.max(360, Math.min(520, vw * 0.54));
    return { top: hh, left: 0, width: vw, height: h };
  }

  function injectRing(tile){
    if (tile.querySelector('.morph-ring')) return;
    var ring = document.createElement('span');
    ring.className = 'morph-ring';
    ring.setAttribute('aria-hidden','true');
    ring.innerHTML = '<svg class="morph-ring__svg" viewBox="0 0 36 36" aria-hidden="true"><circle class="morph-ring__bg" cx="18" cy="18" r="16"/><circle class="morph-ring__fg" cx="18" cy="18" r="16" stroke-dasharray="'+CIRC+'" stroke-dashoffset="'+CIRC+'"/></svg><span class="morph-ring__label">hold</span>';
    tile.appendChild(ring);
    var hint = document.createElement('span');
    hint.className = 'morph-hint';
    hint.textContent = 'hold to open';
    tile.appendChild(hint);
  }

  function track(tile){
    try {
      var from = tile.getAttribute('data-from') || '';
      var to = tile.getAttribute('data-to') || '';
      var lang = document.documentElement.lang || 'ru';
      var parts = location.pathname.split('/').filter(Boolean);
      var last = parts[parts.length-1];
      if (last === 'uz' || last === 'ru') lang = last;
      if (navigator.sendBeacon) {
        var b = new Blob([new URLSearchParams({from:from,to:to,lang:lang}).toString()], {type:'application/x-www-form-urlencoded'});
        navigator.sendBeacon('/track-internal-link', b);
      } else {
        fetch('/track-internal-link', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({from:from,to:to,lang:lang}).toString(), keepalive:true }).catch(function(){});
      }
    } catch(e){}
  }

  function warmImage(tile){
    try {
      var src = tile.getAttribute('data-cover') || (tile.querySelector('.links-tile__img') || {}).src || '';
      if (!src) return;
      var img = new Image();
      img.decoding = 'async';
      img.src = src;
      if (img.decode) img.decode().catch(function(){});
      var l = document.createElement('link');
      l.rel = 'preload'; l.as = 'image'; l.href = src;
      document.head.appendChild(l);
      setTimeout(function(){ try{ l.remove(); }catch(e){} }, 8000);
    } catch(e){}
  }

  function makeClone(tile){
    var tileImg = tile.querySelector('.links-tile__img');
    var src = tile.getAttribute('data-cover') || (tileImg ? (tileImg.currentSrc || tileImg.src) : '');
    // subtle overlay below header only — header stays visible (z 200 > 150)
    var overlay = document.createElement('div');
    overlay.className = 'morph-overlay is-hero-tint';
    document.body.appendChild(overlay);
    requestAnimationFrame(function(){ overlay.classList.add('is-active'); });

    var rect = tile.getBoundingClientRect();
    if (tileImg) {
      var ir = tileImg.getBoundingClientRect();
      if (ir.width > 4 && ir.height > 4) rect = ir;
    }
    var clone = document.createElement('div');
    clone.className = 'morph-clone';
    clone.style.left = rect.left + 'px';
    clone.style.top = rect.top + 'px';
    clone.style.width = rect.width + 'px';
    clone.style.height = rect.height + 'px';

    var cImg = document.createElement('img');
    cImg.className = 'morph-clone__img';
    cImg.alt = '';
    cImg.decoding = 'async';
    cImg.fetchPriority = 'high';
    cImg.src = src || (tileImg ? tileImg.src : '');
    clone.appendChild(cImg);
    document.body.appendChild(clone);
    clone.getBoundingClientRect();
    return { overlay: overlay, clone: clone, cImg: cImg, startRect: rect };
  }

  function doMorph(tile, ctx){
    var end = heroRect();
    var start = ctx.startRect;
    var clone = ctx.clone;
    var dx = end.left - start.left;
    var dy = end.top - start.top;
    var sx = end.width / Math.max(1, start.width);
    var sy = end.height / Math.max(1, start.height);

    document.body.classList.add('morph-lock');

    var dur = isReduced ? 260 : MORPH_MS;
    var easing = isReduced ? 'ease-out' : 'cubic-bezier(0.22, 1, 0.36, 1)';

    var anim = null;
    var cleanup = function(){
      try{ clearTimeout(t); }catch(e){}
      try{ if(document.body.contains(clone)) clone.remove(); }catch(e){}
      try{ if(document.body.contains(ctx.overlay)) ctx.overlay.remove(); }catch(e){}
      document.body.classList.remove('morph-lock');
    };
    try {
      anim = clone.animate([
        { transform:'translate(0,0) scale(1,1)', borderRadius:'16px', offset:0 },
        { transform:'translate('+dx+'px,'+dy+'px) scale('+sx+','+sy+')', borderRadius:'0px', offset:1 }
      ], { duration: dur, easing: easing, fill:'forwards' });
    } catch(e) {
      clone.style.transition = 'transform '+dur+'ms '+easing+', border-radius '+dur+'ms ease';
      requestAnimationFrame(function(){
        clone.style.transform='translate('+dx+'px,'+dy+'px) scale('+sx+','+sy+')';
        clone.style.borderRadius='0px';
      });
    }
    try{ if(navigator.vibrate) navigator.vibrate(18); }catch(e){}

    var href = tile.href;
    var fired = false;
    function nav(){
      if(fired) return; fired=true;
      try{ location.href = href; }catch(e){ location = href; }
      setTimeout(cleanup, 2000);
    }
    var t = setTimeout(nav, Math.round(dur * NAV_AT));
    if (anim) {
      anim.onfinish = function(){ nav(); };
      anim.oncancel = function(){ clearTimeout(t); cleanup(); };
    } else {
      setTimeout(nav, dur + 40);
    }
    function onEsc(e){
      if(e.key==='Escape'){
        if(anim) try{anim.cancel();}catch(e){}
        cleanup();
        tile.classList.remove('is-holding');
        document.removeEventListener('keydown', onEsc);
      }
    }
    document.addEventListener('keydown', onEsc, {once:true});
  }

  function setup(tile){
    if (tile.dataset.morph) return;
    var hasImg = !!tile.querySelector('.links-tile__img') || !!tile.getAttribute('data-cover');
    if (!hasImg) { tile.dataset.morph='no-image'; return; }
    tile.dataset.morph='ready';
    tile.draggable = false;
    var tileImgEl = tile.querySelector('.links-tile__img');
    if (tileImgEl) tileImgEl.draggable = false;
    injectRing(tile);

    var holdTimer=null, raf=null, startX=0, startY=0, startT=0, isHolding=false, morphStarted=false, suppressUntil=0, activePointerId=null;

    function setProgress(p){
      var fg = tile.querySelector('.morph-ring__fg');
      var lab = tile.querySelector('.morph-ring__label');
      if(!fg) return;
      var off = CIRC * (1 - Math.max(0, Math.min(1, p)));
      fg.style.strokeDashoffset = String(off);
      if(lab){
        if(p<0.28) lab.textContent='hold';
        else if(p<0.82) lab.textContent=Math.round(p*100)+'%';
        else lab.textContent='open';
      }
    }
    function releaseCapture(){
      if (activePointerId != null) {
        try { tile.releasePointerCapture(activePointerId); } catch(e){}
        activePointerId = null;
      }
    }
    function clearHold(){
      if(holdTimer){ clearTimeout(holdTimer); holdTimer=null; }
      if(raf){ cancelAnimationFrame(raf); raf=null; }
      isHolding=false;
      tile.classList.remove('is-holding');
      setProgress(0);
    }
    function getPoint(e){
      if (e.touches && e.touches[0]) return { x:e.touches[0].clientX, y:e.touches[0].clientY };
      if (e.changedTouches && e.changedTouches[0]) return { x:e.changedTouches[0].clientX, y:e.changedTouches[0].clientY };
      return { x: e.clientX, y: e.clientY };
    }
    function onDown(e){
      if(morphStarted) return;
      if(isHolding) return;
      if(e.button!=null && e.button!==0) return;
      if(e.ctrlKey||e.metaKey||e.shiftKey||e.altKey) return;
      if(e.touches && e.touches.length>1) return;
      if (e.pointerId != null) {
        activePointerId = e.pointerId;
        try { tile.setPointerCapture(e.pointerId); } catch(err){}
      }
      var p = getPoint(e);
      startX=p.x; startY=p.y; startT=performance.now(); isHolding=true;
      tile.classList.add('is-holding');
      track(tile); warmImage(tile);
      (function tick(){
        if(!isHolding || morphStarted) return;
        var prog = Math.min(1, (performance.now()-startT)/HOLD_MS);
        setProgress(prog);
        raf = requestAnimationFrame(tick);
      })();
      holdTimer = setTimeout(function(){
        if(!isHolding) return;
        morphStarted=true;
        clearHold();
        releaseCapture();
        suppressUntil = Date.now()+2200;
        tile.classList.remove('is-holding');
        var ctx = makeClone(tile);
        var img = ctx.cImg;
        var done=false;
        function go(){ if(done) return; done=true; doMorph(tile, ctx); }
        if(img && img.decode){
          var to = setTimeout(go, 140);
          img.decode().then(function(){ clearTimeout(to); go(); }).catch(function(){ clearTimeout(to); go(); });
        } else { setTimeout(go, 24); }
      }, HOLD_MS);
    }
    function onMove(e){
      if(!isHolding || morphStarted) return;
      if(activePointerId != null && e.pointerId != null && e.pointerId !== activePointerId) return;
      var p = getPoint(e);
      if(Math.hypot(p.x-startX, p.y-startY) > MOVE_TOL) clearHold();
    }
    function onUp(e){
      var wasMorph = morphStarted;
      var wasHolding = isHolding;
      clearHold();
      releaseCapture();
      if(wasMorph){
        suppressUntil = Date.now()+2200;
        if(e.cancelable) try{ e.preventDefault(); }catch(err){}
        setTimeout(function(){ morphStarted=false; }, 2400);
      } else if(wasHolding){
        setTimeout(function(){ morphStarted=false; }, 60);
      }
    }
    function onCancel(){ clearHold(); releaseCapture(); }
    function onClickCapture(e){
      if(Date.now() < suppressUntil){
        e.preventDefault(); e.stopPropagation();
        if(e.stopImmediatePropagation) e.stopImmediatePropagation();
        return false;
      }
    }
    if (window.PointerEvent) {
      tile.addEventListener('pointerdown', onDown, {passive:true});
      tile.addEventListener('pointermove', onMove, {passive:true});
      tile.addEventListener('pointerup', onUp, {passive:false});
      tile.addEventListener('pointercancel', onCancel, {passive:true});
      tile.addEventListener('pointerleave', function(){ if(isHolding && !morphStarted && activePointerId==null) clearHold(); }, {passive:true});
    } else {
      tile.addEventListener('mousedown', onDown);
      tile.addEventListener('mousemove', onMove);
      tile.addEventListener('mouseup', onUp);
      tile.addEventListener('touchstart', onDown, {passive:true});
      tile.addEventListener('touchmove', onMove, {passive:true});
      tile.addEventListener('touchend', onUp, {passive:false});
      tile.addEventListener('touchcancel', onCancel, {passive:true});
    }
    tile.addEventListener('click', onClickCapture, true);
    tile.addEventListener('contextmenu', function(e){
      if(isHolding || morphStarted || Date.now() < suppressUntil){ e.preventDefault(); return false; }
    });
    tile.addEventListener('dragstart', function(e){ e.preventDefault(); });
  }

  function init(){
    var tiles = document.querySelectorAll('.links-track .links-tile');
    if(!tiles.length) return;
    tiles.forEach(setup);
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  window.addEventListener('pageshow', function(){
    document.querySelectorAll('.morph-overlay,.morph-clone').forEach(function(el){ el.remove(); });
    document.body.classList.remove('morph-lock');
    document.querySelectorAll('.links-tile[data-morph="ready"]').forEach(function(t){ t.classList.remove('is-holding'); });
  });
  document.addEventListener('visibilitychange', function(){
    if(document.visibilityState==='hidden'){
      document.querySelectorAll('.links-tile.is-holding').forEach(function(t){ t.classList.remove('is-holding'); });
    }
  });
})();
