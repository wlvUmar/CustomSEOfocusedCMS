// Extracted from views/admin/requests/show.php — carousel, lightbox, price grouping.
// Image list arrives via #rdViewer data-images attribute (kept in template).

// Carousel + lightbox
// ── Carousel (swipe only) ─────────────────────────────
var rdCur = 0;
function rdGoTo(n) {
    var slides = document.querySelectorAll('.rd-slide');
    var dots = document.querySelectorAll('#rdDots .rd-dot');
    var counter = document.getElementById('rdCurr');
    if (!slides.length) return;
    slides[rdCur].classList.remove('active');
    if (dots[rdCur]) dots[rdCur].classList.remove('active');
    rdCur = (n + slides.length) % slides.length;
    slides[rdCur].classList.add('active');
    if (dots[rdCur]) dots[rdCur].classList.add('active');
    if (counter) counter.textContent = rdCur + 1;
}
function rdSlide(d) { rdGoTo(rdCur + d); }

(function () {
    var v = document.getElementById('rdViewer'), sx, sy;
    if (!v) return;
    v.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
    v.addEventListener('touchend', function (e) {
        var dx = e.changedTouches[0].clientX - sx;
        var dy = e.changedTouches[0].clientY - sy;
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) rdSlide(dx < 0 ? 1 : -1);
    }, { passive: true });
})();

// ── Force body/html background (fallback for webviews that don't
//    support the CSS :has() selector, e.g. older in-app browsers) ──
(function () {
    document.documentElement.style.background = '#0e0f12';
    document.body.style.background = '#0e0f12';

    // Hide the layout's sidebar toggle on this page (fallback for the
    // same :has() support gap as above).
    var toggle = document.querySelector('.sidebar-toggle');
    if (toggle) toggle.style.display = 'none';
})();

// ── Price input: live thousands-grouping ──
(function () {
    var priceEl = document.getElementById('rd-price');
    if (!priceEl) return;
    priceEl.addEventListener('input', function () {
        var digits = priceEl.value.replace(/[^\d]/g, '');
        priceEl.value = digits ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') : '';
    });
})();

// ── Purely cosmetic: bold any "NUMBER сум/UZS" amounts inside the
//    description so prices pop visually. Display-only — never touches
//    the underlying data the server rendered. Falls back silently
//    (does nothing) if no matches are found.
(function () {
    var el = document.getElementById('rdDescText');
    if (!el) return;
    try {
        el.innerHTML = el.innerHTML.replace(
            /([\d][\d\s]{2,})\s?(сум|UZS|сўм)/gi,
            '<b>$1 $2</b>'
        );
    } catch (e) { /* no-op on failure */ }
})();

document.addEventListener('keydown', function (e) {
    if (document.getElementById('rdLb').classList.contains('open')) {
        if (e.key === 'Escape')     rdLbClose();
        if (e.key === 'ArrowLeft')  rdLbSlide(-1);
        if (e.key === 'ArrowRight') rdLbSlide(1);
        return;
    }
    if (e.key === 'ArrowLeft')  rdSlide(-1);
    if (e.key === 'ArrowRight') rdSlide(1);
});

// ── Lightbox — pinch to zoom, swipe to change image, tap to close ──
var lbScale = 1, lbX = 0, lbY = 0;
var lbDragging = false, lbDragSX, lbDragSY;
var pinchStartDist = 0, pinchStartScale = 1;
var rdLbEl     = document.getElementById('rdLb');
var rdLbImg    = document.getElementById('rdLbImg');
var rdLbWrap   = document.getElementById('rdLbWrap');
var rdLbDotsEl = document.getElementById('rdLbDots');

var rdLbImages = [];
var rdLbCur    = 0;

var lbSwipeSX = 0, lbSwipeSY = 0, lbSwiping = false;
var lbHadPinchOrPan = false;

function lbApply() {
    rdLbImg.style.transform = 'translate(' + lbX + 'px,' + lbY + 'px) scale(' + lbScale + ')';
}

function lbClampPan() {
    if (lbScale <= 1) { lbX = 0; lbY = 0; return; }
    var maxX = Math.max(0, (rdLbImg.offsetWidth  * lbScale - rdLbWrap.offsetWidth)  / 2);
    var maxY = Math.max(0, (rdLbImg.offsetHeight * lbScale - rdLbWrap.offsetHeight) / 2);
    lbX = Math.max(-maxX, Math.min(maxX, lbX));
    lbY = Math.max(-maxY, Math.min(maxY, lbY));
}

function rdLbBuildDots() {
    rdLbDotsEl.innerHTML = '';
    if (rdLbImages.length <= 1) return;
    for (var i = 0; i < rdLbImages.length; i++) {
        var d = document.createElement('span');
        d.className = 'rd-dot' + (i === rdLbCur ? ' active' : '');
        rdLbDotsEl.appendChild(d);
    }
}
function rdLbUpdateDots() {
    var dots = rdLbDotsEl.querySelectorAll('.rd-dot');
    dots.forEach(function (d, i) { d.classList.toggle('active', i === rdLbCur); });
}

function rdLbRenderCurrent() {
    if (!rdLbImages.length) return;
    rdLbImg.src = rdLbImages[rdLbCur];
    lbScale = 1; lbX = 0; lbY = 0;
    lbApply();
    rdLbUpdateDots();
}

function rdLbOpen(index) {
    var viewer = document.getElementById('rdViewer');
    try {
        rdLbImages = JSON.parse(viewer.getAttribute('data-images') || '[]');
    } catch (e) {
        rdLbImages = [];
    }
    rdLbCur = index || 0;
    rdLbBuildDots();
    rdLbRenderCurrent();
    rdLbEl.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function rdLbClose() {
    rdLbEl.classList.remove('open');
    document.body.style.overflow = '';
}
function rdLbSlide(d) {
    if (!rdLbImages.length) return;
    rdLbCur = (rdLbCur + d + rdLbImages.length) % rdLbImages.length;
    rdLbRenderCurrent();
}

// Tap anywhere (outside an active pinch/pan/swipe gesture) closes the lightbox
rdLbWrap.addEventListener('click', function () {
    if (lbHadPinchOrPan) { lbHadPinchOrPan = false; return; }
    rdLbClose();
});

// Double-tap to toggle zoom (desktop dblclick + mobile double-tap both land here via 'dblclick')
rdLbWrap.addEventListener('dblclick', function () {
    lbScale = lbScale > 1.5 ? 1 : 2.5;
    lbX = 0; lbY = 0;
    lbApply();
});

// Touch: pinch to zoom, single-finger pan when zoomed, single-finger swipe to change image when not zoomed, tap to close
rdLbWrap.addEventListener('touchstart', function (e) {
    if (e.touches.length === 1) {
        if (lbScale > 1) {
            lbDragging = true;
            lbSwiping  = false;
            lbDragSX = e.touches[0].clientX - lbX;
            lbDragSY = e.touches[0].clientY - lbY;
        } else {
            lbDragging = false;
            lbSwiping  = true;
            lbSwipeSX = e.touches[0].clientX;
            lbSwipeSY = e.touches[0].clientY;
        }
    } else if (e.touches.length === 2) {
        lbDragging = false;
        lbSwiping  = false;
        lbHadPinchOrPan = true;
        var dx = e.touches[0].clientX - e.touches[1].clientX;
        var dy = e.touches[0].clientY - e.touches[1].clientY;
        pinchStartDist  = Math.sqrt(dx * dx + dy * dy);
        pinchStartScale = lbScale;
    }
}, { passive: true });

rdLbWrap.addEventListener('touchmove', function (e) {
    if (e.touches.length === 2) {
        e.preventDefault();
        lbHadPinchOrPan = true;
        var dx   = e.touches[0].clientX - e.touches[1].clientX;
        var dy   = e.touches[0].clientY - e.touches[1].clientY;
        var dist = Math.sqrt(dx * dx + dy * dy);
        lbScale  = Math.min(6, Math.max(1, pinchStartScale * dist / pinchStartDist));
        lbClampPan();
        lbApply();
    } else if (lbDragging && e.touches.length === 1) {
        e.preventDefault();
        lbHadPinchOrPan = true;
        lbX = e.touches[0].clientX - lbDragSX;
        lbY = e.touches[0].clientY - lbDragSY;
        lbClampPan();
        lbApply();
    }
}, { passive: false });

rdLbWrap.addEventListener('touchend', function (e) {
    if (e.touches.length < 2) pinchStartDist = 0;
    if (e.touches.length === 0) {
        if (lbSwiping && rdLbImages.length > 1) {
            var dx = (e.changedTouches[0] ? e.changedTouches[0].clientX : lbSwipeSX) - lbSwipeSX;
            var dy = (e.changedTouches[0] ? e.changedTouches[0].clientY : lbSwipeSY) - lbSwipeSY;
            if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50) {
                lbHadPinchOrPan = true;
                rdLbSlide(dx < 0 ? 1 : -1);
            }
        }
        lbDragging = false;
        lbSwiping  = false;
    }
}, { passive: true });

// Replaces inline onclick=rdLbOpen in requests/show.php.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-lb-index]').forEach(function (img) {
        img.addEventListener('click', function () {
            rdLbOpen(parseInt(this.dataset.lbIndex, 10) || 0);
        });
    });
});

// Approve-price guard
document.addEventListener('DOMContentLoaded', function(){
    var approveBtn = document.getElementById('rdApproveBtn');
    var form = document.getElementById('rdForm');
    if (approveBtn && form) {
        approveBtn.addEventListener('click', function (e) {
            var priceEl = document.getElementById('rd-price');
            var price = priceEl ? (priceEl.value || '').trim() : '';
            if (price === '') {
                e.preventDefault();
                alert('Илтимос, юборишдан олдин нархни киритинг.');
                priceEl && priceEl.focus();
                return false;
            }
        });
    }
});
