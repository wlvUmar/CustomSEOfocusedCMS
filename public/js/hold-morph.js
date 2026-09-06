// path: ./public/js/hold-morph.js  v4 — pointer capture (desktop fix),
// clone fill fixed via hold-morph.css, curtain handoff with head-script
(function () {
  'use strict';

  var HOLD_MS = 300;
  var MORPH_MS = 760;         // slightly longer for smoother beats
  var NAV_AT = 0.82;          // navigate near end so morph completes before unload
  var MOVE_TOL = 14;
  var CIRC = 100.53096;
  var STORAGE_KEY = 'morph-pending';

  var isReduced = false;
  try { isReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch(e){}

  // --- destination curtain: masks snap on arrival ---
  // NOTE: a tiny synchronous script in <head> (see header.php patch) already
  // set html.morph-incoming + body{visibility:hidden} BEFORE first paint, so
  // there is no flash of the real page before this runs. This function's job
  // is just to build the curtain and then hand control back (reveal body).
  function showCurtainIfPending(){
    var revealBody = function(){
      try {
        document.documentElement.classList.remove('morph-incoming');
        if (window.__morphCurtainStyle) { window.__morphCurtainStyle.remove(); window.__morphCurtainStyle = null; }
      } catch(e){}
    };
    try {
      var raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) { revealBody(); return; }
      var data = JSON.parse(raw);
      if (!data || !data.src) { sessionStorage.removeItem(STORAGE_KEY); revealBody(); return; }
      if (Date.now() - (data.ts||0) > 2400) { sessionStorage.removeItem(STORAGE_KEY); revealBody(); return; }
      sessionStorage.removeItem(STORAGE_KEY);
      // don't show if no hero on this page (e.g. 404)
      if (!document.querySelector('.hero, #hero, #hero-bg, .hero__bg')) { revealBody(); return; }

      var curtain = document.createElement('div');
      curtain.className = 'morph-curtain';
      curtain.setAttribute('aria-hidden','true');
      var img = document.createElement('img');
      img.className = 'morph-curtain__img';
      img.alt = '';
      img.decoding = 'async';
      img.src = data.src;
      var scrim = document.createElement('div');
      scrim.className = 'morph-curtain__scrim';
      curtain.appendChild(img);
      curtain.appendChild(scrim);
      document.body.appendChild(curtain);

      // curtain is up and opaque — safe to reveal body now, no visible change
      revealBody();
      document.body.style.overflow = 'hidden';

      var doFade = function(){
        curtain.classList.add('is-fading');
        setTimeout(function(){
          try{ curtain.remove(); }catch(e){}
          document.body.style.overflow = '';
        }, 460);
      };
      // wait for hero image or at least next frame, then fade
      var heroImg = document.querySelector('.hero__bg img, .hero-image, .hero-media img');
      if (heroImg && heroImg.decode) {
        var t = setTimeout(doFade, 520);
        heroImg.decode().then(function(){ clearTimeout(t); requestAnimationFrame(function(){ setTimeout(doFade, 120); }); }).catch(function(){ clearTimeout(t); doFade(); });
      } else {
        // fallback: short delay so curtain covers initial paint snap
        requestAnimationFrame(function(){ setTimeout(doFade, 360); });
      }
    } catch(e){
      revealBody();
    }
  }

  // run curtain ASAP (before DOMContentLoaded if possible)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showCurtainIfPending);
  } else {
    showCurtainIfPending();
  }
  window.addEventListener('pageshow', showCurtainIfPending);

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
      // also preload as image so destination hits cache
      var l = document.createElement('link');
      l.rel = 'preload'; l.as = 'image'; l.href = src;
      document.head.appendChild(l);
      setTimeout(function(){ try{ l.remove(); }catch(e){} }, 8000);
    } catch(e){}
  }

  function makeClone(tile){
    var tileImg = tile.querySelector('.links-tile__img');
    var src = tile.getAttribute('data-cover') || (tileImg ? (tileImg.currentSrc || tileImg.src) : '');
    var title = (tile.querySelector('.links-tile__text') || {}).textContent || '';
    var overlay = document.createElement('div');
    overlay.className = 'morph-overlay is-hero-tint';
    document.body.appendChild(overlay);
    requestAnimationFrame(function(){ overlay.classList.add('is-active'); });

    // tile.is-holding already forces transform:none (see hold-morph.css), so
    // this rect is stable and matches what the user actually saw — no jump
    // caused by the hover float still being mid-transition.
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
    var scrim = document.createElement('span');
    scrim.className = 'morph-clone__scrim';
    scrim.setAttribute('aria-hidden','true');
    clone.appendChild(scrim);
    if (title) {
      var t = document.createElement('span');
      t.className = 'morph-clone__title';
      t.textContent = title.trim();
      clone.appendChild(t);
    }
    document.body.appendChild(clone);
    clone.getBoundingClientRect();
    return { overlay: overlay, clone: clone, cImg: cImg, startRect: rect, src: src, title: title };
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
    // store for destination curtain
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ src: ctx.src, title: ctx.title, ts: Date.now() }));
    } catch(e){}

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
      // two-keyframe FLIP: border-radius anim needs separate handling in some browsers
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
    // beats: scrim/title stagger inside clone
    requestAnimationFrame(function(){ requestAnimationFrame(function(){ clone.classList.add('is-covering'); }); });
    try{ if(navigator.vibrate) navigator.vibrate(18); }catch(e){}

    var href = tile.href;
    var fired = false;
    function nav(){
      if(fired) return; fired=true;
      try{ location.href = href; }catch(e){ location = href; }
      // fallback cleanup if nav blocked
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
        try{ sessionStorage.removeItem(STORAGE_KEY); }catch(e){}
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
    // The real fix for the desktop "pointer race": <a>/<img> are draggable
    // by default, and Chromium/WebKit arm an internal drag-recognizer on
    // pointerdown + the first hint of movement — which can eat or delay the
    // pointermove/timing signals our hold-timer relies on, before our own
    // 'dragstart' handler ever runs. CSS -webkit-user-drag:none doesn't
    // reliably suppress this on every engine; the HTML draggable property
    // does, because it turns off native drag-gesture recognition at the
    // source instead of reacting to it after it's already begun.
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
      if(isHolding) return; // ignore a second pointer starting mid-gesture
      if(e.button!=null && e.button!==0) return;
      if(e.ctrlKey||e.metaKey||e.shiftKey||e.altKey) return;
      // ignore secondary touch (more than one finger = scroll)
      if(e.touches && e.touches.length>1) return;

      // Pointer capture pins ALL subsequent pointer events (move/up/cancel) to
      // this tile for this pointerId, regardless of what's visually under the
      // cursor. This is the desktop fix: without it, the tile's own :hover
      // lift (translateY/scale, frozen below via .is-holding CSS) plus normal
      // mouse jitter could shift hit-testing enough to misfire pointerleave
      // and cancel the hold before 300ms elapses.
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

    function onCancel(){
      clearHold();
      releaseCapture();
    }

    function onClickCapture(e){
      if(Date.now() < suppressUntil){
        e.preventDefault(); e.stopPropagation();
        if(e.stopImmediatePropagation) e.stopImmediatePropagation();
        return false;
      }
    }

    // Use pointer events where available — single path, fixes desktop not triggering
    if (window.PointerEvent) {
      tile.addEventListener('pointerdown', onDown, {passive:true});
      tile.addEventListener('pointermove', onMove, {passive:true});
      tile.addEventListener('pointerup', onUp, {passive:false});
      tile.addEventListener('pointercancel', onCancel, {passive:true});
      // With pointer capture held, pointerleave no longer fires on cursor
      // drift alone — it now only fires for a real cancel/capture-loss, so
      // this stays as a safety net rather than the main cancel path.
      tile.addEventListener('pointerleave', function(){ if(isHolding && !morphStarted && activePointerId==null) clearHold(); }, {passive:true});
    } else {
      // fallback
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
