// path: ./public/js/hold-morph.js
// Hold-to-morph: press-and-hold (desktop) / long-press (mobile) on .links-track tiles
// clones the tile image and FLIPs it to the predicted hero geometry, then navigates.
// Only for tiles that have a cover image. Respects prefers-reduced-motion.
// Compositor-only animation (transform/opacity/border-radius). No freezes.
(function () {
  'use strict';

  var HOLD_MS = 420;            // time to hold before morph starts
  var MORPH_MS = 620;            // FLIP duration
  var NAV_AT = 0.62;             // navigate when this fraction of morph elapsed (prevents white flash)
  var MOVE_TOL = 12;             // px movement that cancels hold (scroll/drag)
  var CIRCUMFERENCE = 100.53096; // 2*PI*16 for progress ring

  var prefersReduced = false;
  try { prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch(e){}

  function qs(sel, ctx){ return (ctx||document).querySelector(sel); }
  function headerHeight(){
    var h = qs('header');
    return h ? h.getBoundingClientRect().height : 0;
  }
  function heroTargetRect(){
    // Predicted destination hero rect: full-width, top anchored under header, height clamp
    var hh = headerHeight();
    var vw = window.innerWidth;
    var heroH = Math.max(360, Math.min(520, vw * 0.54));
    // On very tall mobile, hero may be 360; keep it
    return { top: hh, left: 0, width: vw, height: heroH };
  }

  function injectRing(tile){
    if (tile.querySelector('.morph-ring')) return;
    var ring = document.createElement('span');
    ring.className = 'morph-ring';
    ring.setAttribute('aria-hidden','true');
    ring.innerHTML = '<svg class="morph-ring__svg" viewBox="0 0 36 36" aria-hidden="true">'
      + '<circle class="morph-ring__bg" cx="18" cy="18" r="16"/>'
      + '<circle class="morph-ring__fg" cx="18" cy="18" r="16" stroke-dasharray="'+CIRCUMFERENCE+'" stroke-dashoffset="'+CIRCUMFERENCE+'"/>'
      + '</svg><span class="morph-ring__label">hold</span>';
    tile.appendChild(ring);
    var hint = document.createElement('span');
    hint.className = 'morph-hint';
    hint.textContent = 'hold to open';
    tile.appendChild(hint);
  }

  function trackInternalLink(tile){
    // Reuse same endpoint as link-tracking.js but fire early (hold start) with keepalive
    try {
      var from = tile.getAttribute('data-from') || '';
      var to = tile.getAttribute('data-to') || '';
      var lang = document.documentElement.lang || 'ru';
      // try to infer lang from url like link-tracking does
      var parts = location.pathname.split('/').filter(Boolean);
      var last = parts[parts.length-1];
      if (last === 'uz' || last === 'ru') lang = last;
      fetch('/track-internal-link', {
        method: 'POST',
        headers: { 'Content-Type':'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ from: from, to: to, lang: lang }).toString(),
        keepalive: true
      }).catch(function(){});
    } catch(e){}
  }

  function prefetch(tile){
    var href = tile.href;
    if (!href) return;
    try {
      // Speculation/prefetch hint
      var link = document.createElement('link');
      link.rel = 'prefetch';
      link.href = href;
      link.as = 'document';
      document.head.appendChild(link);
      // High-priority fetch to warm cache / DNS (no-cors not needed same-origin)
      // We don't await it; animation must not block on network.
      fetch(href, { priority: 'high', headers: { 'X-Purpose':'prefetch' } }).catch(function(){});
      // Also warm the cover image at high res if data-cover present
      var cover = tile.getAttribute('data-cover') || '';
      if (cover) {
        var img = new Image();
        img.decoding = 'async';
        img.src = cover;
        if (img.decode) img.decode().catch(function(){});
      } else {
        var tileImg = tile.querySelector('.links-tile__img');
        if (tileImg && tileImg.currentSrc) {
          var w = new Image();
          w.src = tileImg.currentSrc;
          if (w.decode) w.decode().catch(function(){});
        }
      }
    } catch(e){}
  }

  function createOverlayAndClone(tile){
    var tileImg = tile.querySelector('.links-tile__img');
    var src = tile.getAttribute('data-cover') || (tileImg ? (tileImg.currentSrc || tileImg.src) : '');
    var title = (tile.querySelector('.links-tile__text') || {}).textContent || '';
    // Respect header height for overlay top
    var hH = headerHeight();
    var overlay = document.createElement('div');
    overlay.className = 'morph-overlay';
    // Leave header visible? We cover everything but keep header above clone for realism.
    // Overlay below clone.
    document.body.appendChild(overlay);
    requestAnimationFrame(function(){ overlay.classList.add('is-active'); });

    var rect = tile.getBoundingClientRect();
    // Use tile rect as source (img is absolute inset:0 so same). Fallback to img rect.
    if (tileImg) {
      var ir = tileImg.getBoundingClientRect();
      // If img rect is zero (not loaded), use tile rect
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
    // Use cover src; if fails, clone will show broken — fallback to tileImg src
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
    // Force layout so transition starts from correct rect
    clone.getBoundingClientRect();
    return { overlay: overlay, clone: clone, cImg: cImg, startRect: rect, title: title };
  }

  function animateMorph(tile, ctx){
    var end = heroTargetRect();
    var start = ctx.startRect;
    var clone = ctx.clone;

    // Compute FLIP deltas
    var dx = end.left - start.left;
    var dy = end.top - start.top;
    var sx = end.width / Math.max(1, start.width);
    var sy = end.height / Math.max(1, start.height);

    // Lock scroll
    document.body.classList.add('morph-lock');
    document.documentElement.style.scrollbarGutter = 'stable';

    // Use Web Animations API for compositor thread
    var anim = null;
    try {
      anim = clone.animate([
        { transform: 'translate(0px, 0px) scale(1, 1)', borderRadius: '16px', offset: 0 },
        { transform: 'translate('+dx+'px, '+dy+'px) scale('+sx+', '+sy+')', borderRadius: '0px', offset: 1 }
      ], {
        duration: MORPH_MS,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        fill: 'forwards'
      });
    } catch(e) {
      // Fallback CSS transition
      clone.style.transition = 'transform '+MORPH_MS+'ms cubic-bezier(0.16,1,0.3,1), border-radius '+MORPH_MS+'ms ease';
      requestAnimationFrame(function(){
        clone.style.transform = 'translate('+dx+'px,'+dy+'px) scale('+sx+','+sy+')';
        clone.style.borderRadius = '0px';
      });
    }

    // Reveal scrim/title mid-flight for hero feel
    requestAnimationFrame(function(){
      requestAnimationFrame(function(){ clone.classList.add('is-covering'); });
    });

    // Haptic
    try { if (navigator.vibrate) navigator.vibrate(18); } catch(e){}

    var navFired = false;
    var href = tile.href;

    function doNavigate(){
      if (navFired) return;
      navFired = true;
      // Keep overlay; browser will unload. Fallback cleanup if navigation blocked (e.g., popup)
      try { window.location.href = href; } catch(e){ window.location = href; }
      // If still here after 1200ms (e.g., blocked), cleanup
      setTimeout(function(){
        if (!navFired) return;
        // If we didn't actually leave, remove overlay
        try { if (document.body.contains(clone)) clone.remove(); } catch(e){}
        try { if (document.body.contains(ctx.overlay)) ctx.overlay.remove(); } catch(e){}
        document.body.classList.remove('morph-lock');
        document.documentElement.style.scrollbarGutter = '';
      }, 1800);
    }

    // Navigate at NAV_AT fraction so user sees morph covering hero before unload
    var navDelay = Math.round(MORPH_MS * NAV_AT);
    var navTimer = setTimeout(doNavigate, navDelay);

    if (anim) {
      anim.onfinish = function(){
        // If nav hasn't fired yet (e.g., custom delay), fire now
        if (!navFired) doNavigate();
      };
      anim.oncancel = function(){ clearTimeout(navTimer); };
    } else {
      setTimeout(function(){ if (!navFired) doNavigate(); }, MORPH_MS + 40);
    }

    // Allow abort via Escape
    function onKey(e){
      if (e.key === 'Escape') {
        clearTimeout(navTimer);
        if (anim) try{ anim.cancel(); }catch(e){}
        try { clone.remove(); } catch(e){}
        try { ctx.overlay.remove(); } catch(e){}
        document.body.classList.remove('morph-lock');
        document.documentElement.style.scrollbarGutter = '';
        tile.classList.remove('is-holding');
        document.removeEventListener('keydown', onKey);
      }
    }
    document.addEventListener('keydown', onKey, { once: true });
  }

  function setupTile(tile){
    if (tile.dataset.morph) return;
    // Only morph tiles that have an image (or data-cover)
    var hasImg = !!tile.querySelector('.links-tile__img') || !!tile.getAttribute('data-cover');
    if (!hasImg) {
      tile.dataset.morph = 'no-image';
      return;
    }
    tile.dataset.morph = 'ready';
    injectRing(tile);

    var holdTimer = null;
    var holdRaf = null;
    var startX = 0, startY = 0;
    var pointerId = null;
    var isHolding = false;
    var morphStarted = false;
    var suppressClickUntil = 0;
    var startTime = 0;

    function updateRing(progress){
      var fg = tile.querySelector('.morph-ring__fg');
      var label = tile.querySelector('.morph-ring__label');
      if (!fg) return;
      var offset = CIRCUMFERENCE * (1 - Math.min(1, Math.max(0, progress)));
      fg.style.strokeDashoffset = String(offset);
      if (label) {
        if (progress < 0.35) label.textContent = 'hold';
        else if (progress < 0.85) label.textContent = Math.round(progress*100) + '%';
        else label.textContent = 'open';
      }
    }

    function clearHold(){
      if (holdTimer) { clearTimeout(holdTimer); holdTimer = null; }
      if (holdRaf) { cancelAnimationFrame(holdRaf); holdRaf = null; }
      isHolding = false;
      tile.classList.remove('is-holding');
      updateRing(0);
    }

    function onPointerDown(e){
      if (prefersReduced) return;
      // Only primary button / touch
      if (e.button != null && e.button !== 0) return;
      if (morphStarted) return;
      // Ignore if modifier keys
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      // Ignore right-click / context menu trigger
      if (e.pointerType === 'mouse' && e.button !== 0) return;

      startX = e.clientX;
      startY = e.clientY;
      pointerId = e.pointerId;
      startTime = performance.now();
      isHolding = true;

      // Capture to get consistent move/up even if pointer leaves
      try { tile.setPointerCapture(pointerId); } catch(err){}

      tile.classList.add('is-holding');
      trackInternalLink(tile);
      prefetch(tile);

      // Progress tick
      (function tick(){
        if (!isHolding || morphStarted) return;
        var elapsed = performance.now() - startTime;
        var p = Math.min(1, elapsed / HOLD_MS);
        updateRing(p);
        holdRaf = requestAnimationFrame(tick);
      })();

      holdTimer = setTimeout(function(){
        if (!isHolding) return;
        morphStarted = true;
        clearHold(); // stop ring tick but keep visual? We'll keep ring hidden now
        suppressClickUntil = Date.now() + 1800;
        // Create overlay+clone and animate
        var ctx = createOverlayAndClone(tile);
        // Wait for image decode if possible, but don't block more than 120ms
        var img = ctx.cImg;
        var decoded = false;
        function go(){ if (decoded) return; decoded = true; animateMorph(tile, ctx); }
        if (img && img.decode) {
          var t = setTimeout(go, 140);
          img.decode().then(function(){ clearTimeout(t); go(); }).catch(function(){ clearTimeout(t); go(); });
        } else {
          // No decode API — small delay for layout then go
          setTimeout(go, 32);
        }
      }, HOLD_MS);

      // Prevent text selection / image drag on hold
      e.preventDefault && e.preventDefault();
    }

    function onPointerMove(e){
      if (!isHolding || morphStarted) return;
      var dx = e.clientX - startX;
      var dy = e.clientY - startY;
      if (Math.hypot(dx, dy) > MOVE_TOL) {
        clearHold();
        try { if (pointerId != null) tile.releasePointerCapture(pointerId); } catch(err){}
      }
    }

    function onPointerUp(e){
      if (pointerId != null && e.pointerId !== pointerId) return;
      var wasHolding = isHolding;
      var wasMorph = morphStarted;
      clearHold();
      if (wasMorph) {
        // Morph already started — suppress the synthetic click that will follow
        suppressClickUntil = Date.now() + 1800;
        if (e.cancelable) e.preventDefault();
      } else if (wasHolding) {
        // Released early — let normal click happen (no morph, no suppression)
      }
      pointerId = null;
      // Ring reset
      updateRing(0);
    }

    function onPointerCancel(e){
      clearHold();
      morphStarted = false;
      pointerId = null;
      updateRing(0);
    }

    function onClickCapture(e){
      if (Date.now() < suppressClickUntil) {
        e.preventDefault();
        e.stopPropagation();
        // stopImmediate so other listeners (link-tracking) don't double-fire
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        return false;
      }
      // If reduced motion or no morph, allow normal navigation
    }

    tile.addEventListener('pointerdown', onPointerDown, { passive: false });
    tile.addEventListener('pointermove', onPointerMove, { passive: true });
    tile.addEventListener('pointerup', onPointerUp, { passive: false });
    tile.addEventListener('pointercancel', onPointerCancel, { passive: true });
    tile.addEventListener('lostpointercapture', onPointerCancel, { passive: true });
    // Capture-phase click suppression (must be before link-tracking bubble)
    tile.addEventListener('click', onClickCapture, true);

    // Also handle contextmenu suppression while holding (mobile long-press menu)
    tile.addEventListener('contextmenu', function(e){
      if (isHolding || morphStarted || Date.now() < suppressClickUntil) {
        e.preventDefault();
        return false;
      }
    });

    // Cancel hold if tile scrolls out of view or track scrolls
    var track = tile.closest('.links-track');
    if (track) {
      track.addEventListener('scroll', function(){ if (isHolding && !morphStarted) clearHold(); }, { passive: true });
    }
    // Abort on visibility change
    document.addEventListener('visibilitychange', function(){
      if (document.visibilityState === 'hidden' && isHolding && !morphStarted) clearHold();
    });
  }

  function init(){
    if (prefersReduced) return;
    var tiles = document.querySelectorAll('.links-track .links-tile');
    if (!tiles.length) return;
    tiles.forEach(setupTile);
    // Re-init if tiles are added later (admin preview, etc.)
    try {
      var obs = new MutationObserver(function(muts){
        muts.forEach(function(m){
          m.addedNodes.forEach(function(n){
            if (n.nodeType !== 1) return;
            if (n.matches && n.matches('.links-tile')) setupTile(n);
            if (n.querySelectorAll) n.querySelectorAll('.links-tile').forEach(setupTile);
          });
        });
      });
      var track = qs('#links-track');
      if (track) obs.observe(track, { childList: true });
    } catch(e){}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  // Also init on pageshow (bfcache)
  window.addEventListener('pageshow', function(){
    // Reset any leftover overlay (bfcache restore)
    document.querySelectorAll('.morph-overlay, .morph-clone').forEach(function(el){ el.remove(); });
    document.body.classList.remove('morph-lock');
    document.documentElement.style.scrollbarGutter = '';
  });
})();
