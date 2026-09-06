// path: ./public/js/hold-morph.js  v7 — image-only, header stays, curtain + hi-res + ratio-correct FLIP
(function () {
  'use strict';

  var HOLD_MS = 320;
  var MORPH_MS = 920;
  var NAV_AT = 0.96;
  var MOVE_TOL = 14;
  var CIRC = 100.53096;
  var STORAGE_KEY = 'morph-pending';
  var HIRES_ATTR = 'data-cover-hires';

  var isReduced = false;
  try { isReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch(e){}

  // --- helpers: accurate hero rect (measured, not estimated) ---
  function headerH(){
    var h = document.querySelector('header');
    return h ? h.getBoundingClientRect().height : 0;
  }
  function heroRectFallback(){
    var hh = headerH();
    var vw = window.innerWidth;
    var h = Math.max(360, Math.min(520, vw * 0.54));
    return { top: hh, left: 0, width: vw, height: h };
  }
  function heroRect(){
    try {
      var hero = document.querySelector('.hero');
      if (hero) {
        var r = hero.getBoundingClientRect();
        // hero is under sticky header; r.top already accounts for scroll/header.
        // For curtain/morph target we need viewport-space rect of hero as fixed.
        // If hero is scrolled out of view (r.top < headerH - 2), fall back to header-anchored estimate
        // so morph doesn't fly off-screen.
        var hh = headerH();
        if (r.width > 40 && r.height > 80 && r.top >= hh - 24 && r.top < hh + 8) {
          return { top: r.top, left: r.left, width: r.width, height: r.height };
        }
        // hero exists but scrolled: use hero's actual rendered height but anchored at header
        if (r.width > 40 && r.height > 80) {
          return { top: hh, left: r.left, width: r.width, height: r.height };
        }
      }
    } catch(e){}
    return heroRectFallback();
  }

  // --- destination curtain: masks hero loading, decode-aware reveal ---
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
      var hero = document.querySelector('.hero, #hero, #hero-bg, .hero__bg');
      if (!hero) { revealBody(); return; }

      // Use real hero geom for curtain — not the estimate — so edges line up exactly and snap is avoided.
      var r;
      try {
        var hr = hero.closest ? hero.closest('.hero') : null;
        var el = hr || document.querySelector('.hero') || hero;
        var br = el.getBoundingClientRect();
        if (br.width > 40 && br.height > 80) {
          r = { top: br.top, left: br.left, width: br.width, height: br.height };
        } else {
          r = heroRect();
        }
      } catch(e){ r = heroRect(); }

      var curtain = document.createElement('div');
      curtain.className = 'morph-curtain';
      curtain.setAttribute('aria-hidden','true');
      curtain.style.top = r.top + 'px';
      curtain.style.left = r.left + 'px';
      curtain.style.width = r.width + 'px';
      curtain.style.height = r.height + 'px';
      var img = document.createElement('img');
      img.className = 'morph-curtain__img';
      img.alt = '';
      img.decoding = 'async';
      img.src = data.src;
      // match hero's object-fit/position so ratio mismatch is invisible
      img.style.objectPosition = 'center center';
      var scrim = document.createElement('div');
      scrim.className = 'morph-curtain__scrim';
      curtain.appendChild(img);
      curtain.appendChild(scrim);
      document.body.appendChild(curtain);

      // Decode-aware reveal: don't unhide body until curtain image is at least decoded,
      // otherwise the dark bg shows for a frame (the "black snap" you felt).
      var revealed = false;
      var doReveal = function(){
        if (revealed) return; revealed = true;
        revealBody();
      };
      var doFade = function(){
        curtain.classList.add('is-fading');
        setTimeout(function(){ try{ curtain.remove(); }catch(e){} }, 620);
      };
      var heroImg = document.querySelector('.hero__bg img, .hero-image, .hero-media img, .hero img');
      var curtainReady = false;
      var heroReady = false;
      var maybeFade = function(){
        if (curtainReady && heroReady) {
          requestAnimationFrame(function(){ setTimeout(doFade, 80); });
        } else if (curtainReady) {
          // hero not yet decoded but curtain is — fade on timeout so we don't hang
          setTimeout(doFade, 420);
        }
      };
      // curtain image gate
      if (img.decode) {
        img.decode().then(function(){
          curtainReady = true;
          doReveal();
          maybeFade();
        }).catch(function(){
          curtainReady = true;
          doReveal();
          maybeFade();
        });
        // safety: don't keep body hidden longer than 400ms
        setTimeout(function(){
          if (!revealed) doReveal();
          if (!curtainReady) { curtainReady = true; maybeFade(); }
        }, 400);
      } else {
        // no decode API — reveal on next frame, fade slightly later
        requestAnimationFrame(function(){
          curtainReady = true;
          doReveal();
          setTimeout(maybeFade, 80);
        });
      }
      // hero image gate
      if (heroImg && heroImg.decode) {
        var t = setTimeout(function(){ heroReady = true; maybeFade(); }, 900);
        heroImg.decode().then(function(){ clearTimeout(t); heroReady = true; maybeFade(); }).catch(function(){ clearTimeout(t); heroReady = true; maybeFade(); });
      } else if (heroImg && heroImg.complete) {
        heroReady = true;
      } else {
        // no hero img or already complete
        setTimeout(function(){ heroReady = true; maybeFade(); }, 320);
      }
    } catch(e){
      revealBody();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showCurtainIfPending);
  } else {
    showCurtainIfPending();
  }
  window.addEventListener('pageshow', showCurtainIfPending);

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
      var hires = tile.getAttribute(HIRES_ATTR) || '';
      if (hires && hires !== src) {
        var hi = new Image(); hi.decoding = 'async'; hi.src = hires;
        if (hi.decode) hi.decode().catch(function(){});
      }
      var l = document.createElement('link');
      l.rel = 'preload'; l.as = 'image'; l.href = src;
      document.head.appendChild(l);
      setTimeout(function(){ try{ l.remove(); }catch(e){} }, 8000);
    } catch(e){}
  }

  function makeClone(tile){
    var tileImg = tile.querySelector('.links-tile__img');
    var src = tile.getAttribute('data-cover') || (tileImg ? (tileImg.currentSrc || tileImg.src) : '');
    var hiresSrc = tile.getAttribute(HIRES_ATTR) || '';
    // subtle overlay — not full black; avoids the "black flash" on snap
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
    cImg.style.objectPosition = 'center center';
    clone.appendChild(cImg);
    document.body.appendChild(clone);
    clone.getBoundingClientRect();

    var bestSrc = src || (tileImg ? tileImg.src : '');
    if (hiresSrc && hiresSrc !== src) {
      var hi = new Image();
      hi.decoding = 'async';
      hi.src = hiresSrc;
      var swap = function(){ try { cImg.src = hiresSrc; } catch(e){} };
      if (hi.decode) hi.decode().then(swap).catch(swap);
      else hi.onload = swap;
      bestSrc = hiresSrc;
    }

    return { overlay: overlay, clone: clone, cImg: cImg, startRect: rect, bestSrc: bestSrc };
  }

  function doMorph(tile, ctx){
    var end = heroRect();
    var start = ctx.startRect;
    var clone = ctx.clone;

    document.body.classList.add('morph-lock');
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ src: ctx.bestSrc, ts: Date.now() }));
    } catch(e){}

    var dur = isReduced ? 320 : MORPH_MS;
    var easing = isReduced ? 'ease-out' : 'cubic-bezier(0.22, 1, 0.36, 1)';

    var anim = null;
    var cleanup = function(){
      try{ clearTimeout(t); }catch(e){}
      try{ if(document.body.contains(clone)) clone.remove(); }catch(e){}
      try{ if(document.body.contains(ctx.overlay)) ctx.overlay.remove(); }catch(e){}
      document.body.classList.remove('morph-lock');
    };
    // Ratio-correct FLIP: animate geometry (left/top/width/height) instead of scale(sx,sy).
    // This lets the inner <img object-fit:cover> recompute its crop each frame,
    // so the hero's 3.7:1 crop and the tile's 1.37:1 crop blend naturally
    // instead of stretching the image (the "cut vs full image" distortion).
    try {
      anim = clone.animate([
        { left: start.left + 'px', top: start.top + 'px', width: start.width + 'px', height: start.height + 'px', borderRadius:'16px', offset:0 },
        { left: end.left + 'px', top: end.top + 'px', width: end.width + 'px', height: end.height + 'px', borderRadius:'0px', offset:1 }
      ], { duration: dur, easing: easing, fill:'forwards' });
    } catch(e) {
      clone.style.transition = 'left '+dur+'ms '+easing+', top '+dur+'ms '+easing+', width '+dur+'ms '+easing+', height '+dur+'ms '+easing+', border-radius '+dur+'ms ease';
      requestAnimationFrame(function(){
        clone.style.left = end.left + 'px';
        clone.style.top = end.top + 'px';
        clone.style.width = end.width + 'px';
        clone.style.height = end.height + 'px';
        clone.style.borderRadius='0px';
      });
    }
    try{ if(navigator.vibrate) navigator.vibrate(18); }catch(e){}

    var href = tile.href;
    var fired = false;
    function nav(){
      if(fired) return; fired=true;
      try{ location.href = href; }catch(e){ location = href; }
      setTimeout(cleanup, 2400);
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
        suppressUntil = Date.now()+2600;
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
        suppressUntil = Date.now()+2600;
        if(e.cancelable) try{ e.preventDefault(); }catch(err){}
        setTimeout(function(){ morphStarted=false; }, 2600);
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
    document.querySelectorAll('.morph-overlay,.morph-clone,.morph-curtain').forEach(function(el){ el.remove(); });
    document.body.classList.remove('morph-lock');
    document.querySelectorAll('.links-tile[data-morph="ready"]').forEach(function(t){ t.classList.remove('is-holding'); });
  });
  document.addEventListener('visibilitychange', function(){
    if(document.visibilityState==='hidden'){
      document.querySelectorAll('.links-tile.is-holding').forEach(function(t){ t.classList.remove('is-holding'); });
    }
  });
})();
