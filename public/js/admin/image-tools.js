// path: ./public/js/admin/image-tools.js
// Shared admin helpers for the page editor's image picker: a crop dialog (Cropper.js, loaded on first use) and a
// canvas resize/convert. Exposes window.ImageTools = { ensureCropper, cropDialog, resize }.
//   cropDialog({src, mime, title, allowRotate, allowSkip, wantBlob}) resolves to
//     null                      — cancelled (Esc / backdrop / Cancel)
//     { skipped: true }         — "no crop" chosen (only when allowSkip)
//     { fractions:{x,y,w,h}, blob|null } — crop area as fractions of the displayed image (blob only if wantBlob and the
//                                  canvas is readable; cross-origin previews can't be read, fractions still work)
(function () {
    'use strict';

    var CROPPER_VERSION = '1.6.2';
    var BASE = 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/' + CROPPER_VERSION + '/';
    var loading = null;
    var styled = false;

    function ensureCropper() {
        if (window.Cropper) return Promise.resolve();
        if (loading) return loading;
        loading = new Promise(function (resolve, reject) {
            var css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = BASE + 'cropper.min.css';
            document.head.appendChild(css);
            var js = document.createElement('script');
            js.src = BASE + 'cropper.min.js';
            js.onload = function () { resolve(); };
            js.onerror = function () { loading = null; reject(new Error('Cropper.js не загрузился (нет доступа к cdnjs.cloudflare.com)')); };
            document.head.appendChild(js);
        });
        return loading;
    }

    function injectStyle() {
        if (styled) return;
        styled = true;
        var st = document.createElement('style');
        st.textContent =
            '.it-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.65)}' +
            '.it-card{width:min(900px,100%);max-height:94vh;display:flex;flex-direction:column;border-radius:12px;background:#fff;overflow:hidden}' +
            '.it-title{padding:10px 14px;font-size:13px;color:#475569;border-bottom:1px solid #e5e7eb;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
            '.it-stage{height:min(58vh,520px);background:#0f172a}' +
            '.it-stage img{display:block;max-width:100%}' +
            '.it-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:10px 12px}' +
            '.it-ratios{display:flex;flex-wrap:wrap;gap:4px}' +
            '.it-ratios button{border:1px solid #cbd5e1;background:#f8fafc;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:600;color:#475569;cursor:pointer}' +
            '.it-ratios button.is-active{background:#0e9f8a;border-color:#0e9f8a;color:#fff}' +
            '.it-info{font-size:12px;color:#64748b;font-variant-numeric:tabular-nums}' +
            '.it-spacer{flex:1 1 auto}';
        document.head.appendChild(st);
    }

    function cropDialog(opts) {
        opts = opts || {};
        return ensureCropper().then(function () {
            return new Promise(function (resolve) {
                injectStyle();
                var wrap = document.createElement('div');
                wrap.className = 'it-modal';
                wrap.setAttribute('role', 'dialog');
                wrap.setAttribute('aria-modal', 'true');
                wrap.innerHTML =
                    '<div class="it-card">' +
                    (opts.title ? '<div class="it-title"></div>' : '') +
                    '<div class="it-stage"><img alt=""></div>' +
                    '<div class="it-bar">' +
                    '<div class="it-ratios">' +
                    '<button type="button" data-ratio="free" class="is-active">Свободно</button>' +
                    '<button type="button" data-ratio="1.3333333">4:3</button>' +
                    '<button type="button" data-ratio="1.7777778">16:9</button>' +
                    '<button type="button" data-ratio="1">1:1</button>' +
                    '<button type="button" data-ratio="0.75">3:4</button>' +
                    '</div>' +
                    (opts.allowRotate ? '<button type="button" class="btn btn-secondary" data-rotate="-90" aria-label="Повернуть влево">⟲</button>' +
                        '<button type="button" class="btn btn-secondary" data-rotate="90" aria-label="Повернуть вправо">⟳</button>' : '') +
                    '<span class="it-info"></span><span class="it-spacer"></span>' +
                    '<button type="button" class="btn btn-secondary" data-act="cancel">Отмена</button>' +
                    (opts.allowSkip ? '<button type="button" class="btn btn-secondary" data-act="skip">Без обрезки</button>' : '') +
                    '<button type="button" class="btn btn-primary" data-act="apply">Применить</button>' +
                    '</div></div>';
                if (opts.title) wrap.querySelector('.it-title').textContent = opts.title;
                document.body.appendChild(wrap);

                var img = wrap.querySelector('img');
                var info = wrap.querySelector('.it-info');
                var cropper = null;

                function finish(result) {
                    document.removeEventListener('keydown', onKey);
                    if (cropper) cropper.destroy();
                    wrap.remove();
                    resolve(result);
                }
                function onKey(e) { if (e.key === 'Escape') finish(null); }
                document.addEventListener('keydown', onKey);

                function setRatio(v) {
                    if (!cropper) return;
                    cropper.setAspectRatio(v === 'free' ? NaN : parseFloat(v));
                    Array.prototype.forEach.call(wrap.querySelectorAll('[data-ratio]'), function (b) {
                        b.classList.toggle('is-active', b.getAttribute('data-ratio') === v);
                    });
                }

                function apply() {
                    if (!cropper) return;
                    var nat = cropper.getImageData();
                    var d = cropper.getData();
                    var fractions = {
                        x: Math.max(0, d.x / nat.naturalWidth), y: Math.max(0, d.y / nat.naturalHeight),
                        w: Math.min(1, d.width / nat.naturalWidth), h: Math.min(1, d.height / nat.naturalHeight)
                    };
                    if (!opts.wantBlob) return finish({ fractions: fractions, blob: null });
                    var type = /^image\/(jpeg|png|webp)$/.test(opts.mime || '') ? opts.mime : 'image/png';
                    try {
                        var canvas = cropper.getCroppedCanvas({
                            maxWidth: 4096, maxHeight: 4096, imageSmoothingQuality: 'high',
                            fillColor: type === 'image/jpeg' ? '#fff' : 'transparent'
                        });
                        canvas.toBlob(function (blob) { finish({ fractions: fractions, blob: blob }); }, type, 0.92);
                    } catch (e) {
                        finish({ fractions: fractions, blob: null });   // tainted canvas (cross-origin preview): coordinates only
                    }
                }

                wrap.addEventListener('click', function (e) {
                    if (e.target === wrap) return finish(null);
                    var ratio = e.target.closest('[data-ratio]');
                    if (ratio) return setRatio(ratio.getAttribute('data-ratio'));
                    var rot = e.target.closest('[data-rotate]');
                    if (rot && cropper) return cropper.rotate(parseInt(rot.getAttribute('data-rotate'), 10));
                    var act = e.target.closest('[data-act]');
                    if (!act) return;
                    var a = act.getAttribute('data-act');
                    if (a === 'cancel') finish(null);
                    else if (a === 'skip') finish({ skipped: true });
                    else if (a === 'apply') apply();
                });

                img.onload = function () {
                    cropper = new Cropper(img, {
                        viewMode: 1, autoCropArea: 1, background: false, responsive: true,
                        checkCrossOrigin: false,   // previews may come from a CDN without CORS headers; we only need coordinates
                        crop: function (e) { info.textContent = Math.round(e.detail.width) + ' × ' + Math.round(e.detail.height) + ' px'; }
                    });
                };
                img.onerror = function () { finish(null); };
                img.src = opts.src;
            });
        });
    }

    function loadBitmap(file) {
        if (window.createImageBitmap) return createImageBitmap(file);
        return new Promise(function (resolve, reject) {
            var im = new Image();
            im.onload = function () { resolve(im); };
            im.onerror = reject;
            im.src = URL.createObjectURL(file);
        });
    }

    // Resolves to the File to send: the original when nothing needs changing, or on any failure.
    function resize(file, o) {
        o = o || {};
        var maxW = parseInt(o.maxWidth, 10) || 0;
        var fmt = o.format || '';                              // '' | 'webp' | 'jpeg'
        if ((!maxW && !fmt) || file.type === 'image/gif') return Promise.resolve(file); // GIF may be animated: leave it
        return loadBitmap(file).then(function (bmp) {
            var w = bmp.naturalWidth || bmp.width;
            var h = bmp.naturalHeight || bmp.height;
            var scale = maxW && w > maxW ? maxW / w : 1;
            var target = fmt === 'webp' ? 'image/webp' : (fmt === 'jpeg' ? 'image/jpeg' : file.type);
            if (scale === 1 && target === file.type) return file;
            var cw = Math.max(1, Math.round(w * scale));
            var ch = Math.max(1, Math.round(h * scale));
            var canvas = document.createElement('canvas');
            canvas.width = cw;
            canvas.height = ch;
            var ctx = canvas.getContext('2d');
            if (target === 'image/jpeg') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cw, ch); }
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(bmp, 0, 0, cw, ch);
            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    if (!blob) return resolve(file);
                    var type = blob.type || target;            // browsers without WebP encoding return PNG
                    var ext = type === 'image/jpeg' ? 'jpg' : (type === 'image/webp' ? 'webp' : 'png');
                    resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.' + ext, { type: type, lastModified: Date.now() }));
                }, target, target === 'image/png' ? undefined : 0.85);
            });
        }).catch(function () { return file; });
    }

    window.ImageTools = { ensureCropper: ensureCropper, cropDialog: cropDialog, resize: resize };
})();
