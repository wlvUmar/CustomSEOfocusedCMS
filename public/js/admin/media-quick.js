// path: ./public/js/admin/media-quick.js
// Admin "Quick add photos" (/admin/media/quick): drop/paste many images, match them to pages by file name,
// prefill RU/UZ alt text from the page title, then upload + attach one by one via POST /admin/media/quick-add.
(function () {
    'use strict';

    var root = document.getElementById('mq');
    if (!root) return;

    var BASE = window.baseUrl || '';
    var pages = window.MQ_PAGES || [];
    var bySlug = {};
    pages.forEach(function (p) { bySlug[p.slug] = p; });
    var SECTIONS = [['gallery', 'Галерея'], ['content', 'Контент'], ['hero', 'Hero'], ['banner', 'Баннер']];
    var MAX_FILES = 100;

    var rows = [];
    var nextId = 1;
    var uploading = false;

    function $(sel, el) { return (el || document).querySelector(sel); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function sizeLabel(bytes) { return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' МБ' : Math.round(bytes / 1024) + ' КБ'; }

    // ── page datalist ────────────────────────────────────────
    $('#mqPages').innerHTML = pages.map(function (p) {
        return '<option value="' + esc(p.slug) + '">' + esc(p.title_ru) + '</option>';
    }).join('');

    // ── filename → page / section ────────────────────────────
    function prefixMatch(slug) {
        var best = null;
        pages.forEach(function (p) {
            if (slug.indexOf(p.slug + '-') === 0 && (!best || p.slug.length > best.slug.length)) best = p;
        });
        return best;
    }

    function parseName(name) {
        var base = name.replace(/\.[^.]+$/, '').toLowerCase().replace(/[\s_]+/g, '-').replace(/-+/g, '-');
        var section = null;
        for (var i = 0; i < 5; i++) {
            if (bySlug[base]) return { page: bySlug[base], section: section };
            var m = base.match(/^(.*)-(hero|banner|content|gallery|main)$/);
            if (m) { section = section || (m[2] === 'main' ? 'hero' : m[2]); base = m[1]; continue; }
            m = base.match(/^(.*)-(\d+)$/);
            if (m) { base = m[1]; continue; }
            break;
        }
        return { page: bySlug[base] || prefixMatch(base) || null, section: section };
    }

    // ── alt text templates (a page title, not a description of the image) ──
    function ordinalFor(row) {
        var n = 0;
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].page && row.page && rows[i].page.id === row.page.id) n++;
            if (rows[i] === row) break;
        }
        return n || 1;
    }
    function fillAlt(row) {
        if (!row.page) return;
        var n = ordinalFor(row);
        if (!row.altRuTouched) row.altRu = (row.page.title_ru || row.page.slug) + ' — фото ' + n;
        if (!row.altUzTouched) row.altUz = (row.page.title_uz || row.page.title_ru || row.page.slug) + ' — rasm ' + n;
    }

    // ── rows ─────────────────────────────────────────────────
    // opts (optional, for AI-generated images): { page, section, generated } — skips the filename matching.
    function addFiles(fileList, opts) {
        opts = opts || {};
        var added = 0;
        Array.prototype.forEach.call(fileList, function (file) {
            if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) return;
            if (rows.length >= MAX_FILES) return;
            var dup = rows.some(function (r) { return r.file.name === file.name && r.file.size === file.size && r.file.lastModified === file.lastModified; });
            if (dup) return;
            var parsed = opts.page ? { page: opts.page, section: opts.section } : parseName(file.name);
            var row = {
                id: nextId++, file: file, url: URL.createObjectURL(file),
                page: parsed.page, section: parsed.section || 'gallery', generated: !!opts.generated,
                altRu: '', altUz: '', altRuTouched: false, altUzTouched: false,
                state: 'idle', message: ''
            };
            rows.push(row);
            added++;
        });
        rows.forEach(fillAlt);
        if (added) render();
    }

    function statusOf(row) {
        if (row.state === 'done') return ['mq-status--ok', 'Готово'];
        if (row.state === 'uploading') return ['', 'Загрузка…'];
        if (row.state === 'error') return ['mq-status--bad', 'Ошибка: ' + row.message];
        if (!row.page) return ['mq-status--warn', 'Выберите страницу'];
        return ['', 'Ждёт загрузки'];
    }

    function rowClass(row) {
        if (row.state === 'done') return 'mq-row is-done';
        if (row.state === 'error') return 'mq-row is-error';
        if (!row.page) return 'mq-row is-nopage';
        return 'mq-row';
    }

    function rowHtml(row) {
        var locked = row.state === 'done' || row.state === 'uploading' ? ' disabled' : '';
        var st = statusOf(row);
        var opts = SECTIONS.map(function (s) {
            return '<option value="' + s[0] + '"' + (row.section === s[0] ? ' selected' : '') + '>' + s[1] + '</option>';
        }).join('');
        return '<div class="' + rowClass(row) + '" data-id="' + row.id + '">' +
            '<img class="mq-thumb" src="' + esc(row.url) + '" alt="">' +
            '<div class="mq-fields">' +
            '<label>Страница<input type="text" data-f="page" list="mqPages" value="' + esc(row.page ? row.page.slug : '') + '" placeholder="slug страницы" autocomplete="off"' + locked + '></label>' +
            '<label>Раздел<select data-f="section"' + locked + '>' + opts + '</select></label>' +
            '<label>Alt RU<input type="text" data-f="altRu" value="' + esc(row.altRu) + '" maxlength="255"' + locked + '></label>' +
            '<label>Alt UZ<input type="text" data-f="altUz" value="' + esc(row.altUz) + '" maxlength="255"' + locked + '></label>' +
            '<div class="mq-meta"><span class="mq-status ' + st[0] + '" data-status>' + esc(st[1]) + '</span>' +
            (row.generated ? '<span class="mq-ai" title="Создано ИИ">ИИ</span>' : '') +
            (row.stock ? '<span class="mq-pexels" title="Фото с Pexels">Pexels</span>' : '') +
            '<span>' + esc(row.file.name) + (row.file.size ? ' · ' + sizeLabel(row.file.size) : '') + '</span>' +
            (row.state === 'done' || row.state === 'uploading' || row.stock || typeof Cropper === 'undefined' ? '' : '<button type="button" class="mq-crop-btn" data-crop>Обрезать</button>') +
            (row.cropped ? '<span class="mq-status" title="Обрезано вручную">обрезано</span>' : '') +
            (row.state === 'done' || row.state === 'uploading' ? '' : '<button type="button" class="mq-remove" data-remove>Убрать</button>') +
            '</div></div></div>';
    }

    function render() {
        $('#mqRows').innerHTML = rows.map(rowHtml).join('');
        $('#mqBulk').hidden = rows.length === 0;
        summary();
    }

    // cheap in-place refresh of one row's status/classes (keeps focus in the inputs)
    function refreshRow(row) {
        var el = $('.mq-row[data-id="' + row.id + '"]');
        if (!el) return;
        el.className = rowClass(row);
        var st = statusOf(row);
        var chip = $('[data-status]', el);
        chip.className = 'mq-status ' + st[0];
        chip.textContent = st[1];
        if (row.state === 'done' || row.state === 'uploading') {
            Array.prototype.forEach.call(el.querySelectorAll('input, select'), function (i) { i.disabled = true; });
            var rm = $('[data-remove]', el);
            if (rm) rm.remove();
        }
        summary();
    }

    function summary() {
        var done = 0, bad = 0, nopage = 0, pending = 0;
        rows.forEach(function (r) {
            if (r.state === 'done') done++;
            else if (r.state === 'error') bad++;
            else if (!r.page) nopage++;
            else pending++;
        });
        var parts = [];
        if (rows.length) parts.push('Всего: ' + rows.length);
        if (pending) parts.push('к загрузке: ' + pending);
        if (done) parts.push('готово: ' + done);
        if (bad) parts.push('ошибок: ' + bad);
        if (nopage) parts.push('без страницы: ' + nopage);
        $('#mqSummary').textContent = parts.join(' · ');
        $('#mqUpload').disabled = uploading || (pending + bad) === 0;
    }

    function rowById(id) {
        for (var i = 0; i < rows.length; i++) if (String(rows[i].id) === String(id)) return rows[i];
        return null;
    }

    // ── events ───────────────────────────────────────────────
    var drop = $('#mqDrop');
    var fileInput = $('#mqFile');
    drop.addEventListener('click', function () { fileInput.click(); });
    drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); } });
    fileInput.addEventListener('change', function () { addFiles(fileInput.files); fileInput.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
    });
    drop.addEventListener('drop', function (e) { addFiles(e.dataTransfer.files); });
    document.addEventListener('paste', function (e) {
        var files = (e.clipboardData && e.clipboardData.files) || [];
        if (files.length) { e.preventDefault(); addFiles(files); }
    });

    var list = $('#mqRows');
    list.addEventListener('input', function (e) {
        var f = e.target.getAttribute('data-f');
        var rowEl = e.target.closest('.mq-row');
        if (!f || !rowEl) return;
        var row = rowById(rowEl.getAttribute('data-id'));
        if (!row) return;
        if (f === 'altRu') { row.altRu = e.target.value; row.altRuTouched = true; }
        else if (f === 'altUz') { row.altUz = e.target.value; row.altUzTouched = true; }
        else if (f === 'page') {
            row.page = bySlug[e.target.value.trim()] || null;
            if (row.state === 'error') { row.state = 'idle'; row.message = ''; }
            fillAlt(row);
            $('[data-f="altRu"]', rowEl).value = row.altRu;
            $('[data-f="altUz"]', rowEl).value = row.altUz;
            refreshRow(row);
        }
    });
    list.addEventListener('change', function (e) {
        if (e.target.getAttribute('data-f') !== 'section') return;
        var row = rowById(e.target.closest('.mq-row').getAttribute('data-id'));
        if (row) row.section = e.target.value;
    });
    list.addEventListener('click', function (e) {
        if (!e.target.hasAttribute('data-remove')) return;
        var row = rowById(e.target.closest('.mq-row').getAttribute('data-id'));
        if (!row) return;
        URL.revokeObjectURL(row.url);
        rows.splice(rows.indexOf(row), 1);
        rows.forEach(fillAlt);
        render();
    });

    $('#mqApplyPage').addEventListener('click', function () {
        var page = bySlug[$('#mqBulkPage').value.trim()];
        if (!page) { $('#mqBulkPage').focus(); return; }
        rows.forEach(function (r) { if (!r.page && r.state !== 'done') r.page = page; });
        rows.forEach(fillAlt);
        render();
    });
    $('#mqBulkSection').addEventListener('change', function () {
        var v = this.value;
        if (!v) return;
        rows.forEach(function (r) { if (r.state !== 'done' && r.state !== 'uploading') r.section = v; });
        render();
        this.value = '';
    });
    $('#mqClear').addEventListener('click', function () {
        if (uploading) return;
        rows.forEach(function (r) { URL.revokeObjectURL(r.url); });
        rows = [];
        render();
    });

    // ── upload ───────────────────────────────────────────────
    function csrf() {
        return window.csrfToken || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    }

    // ── resize / convert before upload (toolbar: max width + format). Crop is separate: "Обрезать" on a row. ──
    function loadBitmap(file) {
        if (window.createImageBitmap) return createImageBitmap(file);
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.onload = function () { resolve(img); };
            img.onerror = reject;
            img.src = URL.createObjectURL(file);
        });
    }

    // Resolves to the File to send: the original when nothing needs changing (or on any failure).
    function prepareFile(row) {
        var maxW = parseInt($('#mqMaxWidth').value, 10) || 0;
        var fmt = $('#mqFormat').value;                       // '' | 'webp' | 'jpeg'
        var f = row.file;
        if ((!maxW && !fmt) || f.type === 'image/gif') return Promise.resolve(f); // GIF may be animated: leave it
        return loadBitmap(f).then(function (bmp) {
            var w = bmp.naturalWidth || bmp.width;
            var h = bmp.naturalHeight || bmp.height;
            var scale = maxW && w > maxW ? maxW / w : 1;
            var target = fmt === 'webp' ? 'image/webp' : (fmt === 'jpeg' ? 'image/jpeg' : f.type);
            if (scale === 1 && target === f.type) return f;   // already small enough and in the right format
            var cw = Math.max(1, Math.round(w * scale));
            var ch = Math.max(1, Math.round(h * scale));
            var canvas = document.createElement('canvas');
            canvas.width = cw;
            canvas.height = ch;
            var ctx = canvas.getContext('2d');
            if (target === 'image/jpeg') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cw, ch); } // no black where PNG was transparent
            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(bmp, 0, 0, cw, ch);
            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    if (!blob) return resolve(f);
                    var type = blob.type || target;            // browsers without WebP encoding return PNG
                    var ext = type === 'image/jpeg' ? 'jpg' : (type === 'image/webp' ? 'webp' : 'png');
                    resolve(new File([blob], f.name.replace(/\.[^.]+$/, '') + '.' + ext, { type: type, lastModified: Date.now() }));
                }, target, target === 'image/png' ? undefined : 0.85);
            });
        }).catch(function () { return f; });
    }

    // ── crop (Cropper.js) ────────────────────────────────────
    var cropper = null;
    var cropRow = null;

    function closeCrop() {
        if (cropper) { cropper.destroy(); cropper = null; }
        cropRow = null;
        $('#mqCropStage').innerHTML = '';
        $('#mqCrop').hidden = true;
    }

    function setRatio(value) {
        if (!cropper) return;
        cropper.setAspectRatio(value === 'free' ? NaN : parseFloat(value));
        Array.prototype.forEach.call($('#mqRatios').querySelectorAll('button'), function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-ratio') === value);
        });
    }

    function openCrop(row) {
        if (typeof Cropper === 'undefined') { window.alert('Cropper.js не загрузился (нет доступа к cdnjs.cloudflare.com).'); return; }
        cropRow = row;
        var stage = $('#mqCropStage');
        stage.innerHTML = '<img id="mqCropImg" alt="">';
        $('#mqCrop').hidden = false;
        var img = $('#mqCropImg');
        img.onload = function () {
            cropper = new Cropper(img, {
                viewMode: 1, autoCropArea: 1, background: false, responsive: true,
                crop: function (e) { $('#mqCropInfo').textContent = Math.round(e.detail.width) + ' × ' + Math.round(e.detail.height) + ' px'; }
            });
            setRatio('free');
        };
        img.src = row.url;
    }

    function applyCrop() {
        if (!cropper || !cropRow) return;
        var row = cropRow;
        var type = /^image\/(jpeg|png|webp)$/.test(row.file.type) ? row.file.type : 'image/png';
        var canvas = cropper.getCroppedCanvas({
            maxWidth: 4096, maxHeight: 4096, imageSmoothingQuality: 'high',
            fillColor: type === 'image/jpeg' ? '#fff' : 'transparent'
        });
        if (!canvas) { window.alert('Не удалось обрезать это изображение.'); return; }
        canvas.toBlob(function (blob) {
            if (!blob) { window.alert('Не удалось обрезать это изображение.'); return; }
            var ext = type === 'image/jpeg' ? 'jpg' : (type === 'image/webp' ? 'webp' : 'png');
            var file = new File([blob], row.file.name.replace(/\.[^.]+$/, '') + '.' + ext, { type: type, lastModified: Date.now() });
            URL.revokeObjectURL(row.url);
            row.file = file;
            row.url = URL.createObjectURL(file);
            row.cropped = true;
            closeCrop();
            render();
        }, type, 0.92);
    }

    $('#mqRatios').addEventListener('click', function (e) {
        var b = e.target.closest('[data-ratio]');
        if (b) setRatio(b.getAttribute('data-ratio'));
    });
    $('#mqCrop').addEventListener('click', function (e) {
        var r = e.target.closest('[data-rotate]');
        if (r && cropper) cropper.rotate(parseInt(r.getAttribute('data-rotate'), 10));
        if (e.target === this) closeCrop();                    // click on the dark backdrop
    });
    $('#mqCropCancel').addEventListener('click', closeCrop);
    $('#mqCropApply').addEventListener('click', applyCrop);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !$('#mqCrop').hidden) closeCrop(); });
    $('#mqRows').addEventListener('click', function (e) {
        if (!e.target.hasAttribute('data-crop')) return;
        var row = rowById(e.target.closest('.mq-row').getAttribute('data-id'));
        if (row) openCrop(row);
    });

    function uploadRow(row) {
        row.state = 'uploading';
        refreshRow(row);
        return prepareFile(row).then(function (file) { return uploadPrepared(row, file); });
    }

    function uploadPrepared(row, file) {
        var fd = new FormData();
        fd.append('file', file, file.name);
        fd.append('page_id', row.page.id);
        fd.append('section', row.section);
        fd.append('alt_text_ru', row.altRu);
        fd.append('alt_text_uz', row.altUz);
        return fetch(BASE + '/admin/media/quick-add', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            return r.json().catch(function () { return { success: false, message: 'Неверный ответ сервера (HTTP ' + r.status + ')' }; });
        }).catch(function () {
            return { success: false, message: 'Сетевая ошибка' };
        }).then(function (res) {
            if (res.success) { row.state = 'done'; row.message = ''; }
            else { row.state = 'error'; row.message = res.message || 'не удалось загрузить'; }
            refreshRow(row);
        });
    }

    $('#mqUpload').addEventListener('click', function () {
        if (uploading) return;
        var queue = rows.filter(function (r) { return r.page && (r.state === 'idle' || r.state === 'error'); });
        if (!queue.length) return;
        uploading = true;
        summary();
        var chain = Promise.resolve();
        queue.forEach(function (row) { chain = chain.then(function () { return uploadRow(row); }); });
        chain.then(function () { uploading = false; summary(); });
    });

    window.addEventListener('beforeunload', function (e) {
        if (uploading || generating) { e.preventDefault(); e.returnValue = ''; }
    });

    // ── AI generation (panel exists only when OPENROUTER_API_KEY + migration 019 are in place) ──
    var generating = false;
    var genBtn = $('#mqGenBtn');

    function cleanTitle(t) {
        return String(t || '').replace(/^\s*(скупка|куплю|выкуп)\s+/i, '').replace(/\s+в\s+ташкент\S*\s*$/i, '').trim();
    }
    function defaultPrompt(page) {
        return 'Realistic photo of a used ' + cleanTitle(page.title_ru || page.slug) +
            ', front view, clean modern home interior, soft natural light, sharp focus, no text, no watermark, no brand logos';
    }
    function b64ToFile(b64, type, name) {
        var bin = atob(b64);
        var bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return new File([bytes], name, { type: type, lastModified: Date.now() });
    }

    if (genBtn) {
        var genPage = $('#mqGenPage');
        var genPrompt = $('#mqGenPrompt');
        var genStatus = $('#mqGenStatus');
        var promptTouched = false;

        function setGenStatus(text, isError) {
            genStatus.textContent = text;
            genStatus.className = 'mq-gen-status' + (isError ? ' is-error' : '');
        }

        genPrompt.addEventListener('input', function () { promptTouched = true; });
        genPage.addEventListener('input', function () {
            var p = bySlug[genPage.value.trim()];
            if (p && (!promptTouched || !genPrompt.value.trim())) { genPrompt.value = defaultPrompt(p); promptTouched = false; }
        });

        function generateOne(page, n, total, cost) {
            setGenStatus('Создаю ' + n + ' из ' + total + '… (до минуты)', false);
            var fd = new FormData();
            fd.append('page_id', page.id);
            fd.append('prompt', genPrompt.value.trim());
            fd.append('model', $('#mqGenModel').value);
            fd.append('aspect_ratio', $('#mqGenAspect').value);
            return fetch(BASE + '/admin/media/generate', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) {
                return r.json().catch(function () { return { success: false, message: 'Неверный ответ сервера (HTTP ' + r.status + ')' }; });
            }).catch(function () {
                return { success: false, message: 'Сетевая ошибка' };
            }).then(function (res) {
                if (!res.success) throw new Error(res.message || 'не удалось создать');
                var ext = /png/.test(res.media_type) ? 'png' : (/webp/.test(res.media_type) ? 'webp' : 'jpg');
                var file = b64ToFile(res.image, res.media_type || 'image/png', page.slug + '-ai-' + Date.now() + '.' + ext);
                addFiles([file], { page: page, section: $('#mqGenSection').value, generated: true });
                $('#mqGenQuota').textContent = 'Сегодня создано: ' + res.used_today + ' из ' + res.limit;
                return cost + (res.cost || 0);
            });
        }

        genBtn.addEventListener('click', function () {
            if (generating) return;
            var page = bySlug[genPage.value.trim()];
            if (!page) { setGenStatus('Выберите страницу из списка.', true); genPage.focus(); return; }
            if (genPrompt.value.trim().length < 3) { setGenStatus('Введите описание.', true); genPrompt.focus(); return; }
            var total = parseInt($('#mqGenCount').value, 10) || 1;
            generating = true;
            genBtn.disabled = true;
            var chain = Promise.resolve(0);
            var made = 0;
            for (var i = 1; i <= total; i++) {
                (function (n) {
                    chain = chain.then(function (cost) {
                        return generateOne(page, n, total, cost).then(function (c) { made++; return c; });
                    });
                })(i);
            }
            chain.then(function (cost) {
                setGenStatus('Готово: ' + made + ' шт.' + (cost ? ' · списано ≈ $' + cost.toFixed(3) : '') + '. Проверьте результаты в списке ниже.', false);
            }).catch(function (err) {
                setGenStatus((made ? 'Создано ' + made + ', затем ошибка: ' : 'Ошибка: ') + err.message, true);
            }).then(function () {
                generating = false;
                genBtn.disabled = false;
            });
        });
    }

    // ── Stock photos (Pexels): tick several → imported + attached straight away (panel exists only with PEXELS_API_KEY) ──
    var stockSearchBtn = $('#mqStockSearchBtn');
    if (stockSearchBtn) {
        var stockPageIn = $('#mqStockPage');
        var stockQueryIn = $('#mqStockQuery');
        var stockGrid = $('#mqStockGrid');
        var stockStatus = $('#mqStockStatus');
        var stockMore = $('#mqStockMore');
        var stockAdd = $('#mqStockAdd');
        var stockSel = {};       // photo id -> photo (kept across "show more")
        var stockPageNo = 1;
        var stockBusy = false;

        function setStockStatus(text, isError) {
            stockStatus.textContent = text;
            stockStatus.className = 'mq-gen-status' + (isError ? ' is-error' : '');
        }
        function stockCount() { return Object.keys(stockSel).length; }
        function updateStockAdd() {
            stockAdd.textContent = 'Добавить выбранные (' + stockCount() + ')';
            stockAdd.disabled = stockBusy || stockCount() === 0;
        }
        function stockJson(r) {
            return r.json().catch(function () { return { success: false, message: 'Неверный ответ сервера (HTTP ' + r.status + ')' }; });
        }

        function addStockCard(p) {
            var card = document.createElement('div');
            card.className = 'mq-stock-card' + (stockSel[p.id] ? ' is-selected' : '');
            card.setAttribute('data-id', p.id);
            card.innerHTML = '<img src="' + esc(p.thumb) + '" alt="' + esc(p.alt || '') + '" loading="lazy" decoding="async">' +
                '<div class="mq-stock-credit">' + esc(p.photographer) + ' · Pexels</div>';
            card.addEventListener('click', function () {
                if (stockBusy) return;
                if (stockSel[p.id]) { delete stockSel[p.id]; card.classList.remove('is-selected'); }
                else { stockSel[p.id] = p; card.classList.add('is-selected'); }
                updateStockAdd();
            });
            stockGrid.appendChild(card);
        }

        function stockLoad(reset) {
            var page = bySlug[stockPageIn.value.trim()];
            if (!page) { setStockStatus('Выберите страницу из списка.', true); stockPageIn.focus(); return; }
            if (reset) { stockPageNo = 1; stockGrid.innerHTML = ''; stockMore.hidden = true; }
            setStockStatus('Ищу…', false);
            var url = BASE + '/admin/media/stock-search?page_id=' + page.id + '&q=' + encodeURIComponent(stockQueryIn.value.trim()) + '&p=' + stockPageNo;
            fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(stockJson).then(function (res) {
                if (!res.success) throw new Error(res.message || 'не удалось загрузить');
                stockQueryIn.value = res.query;           // keyword derived from the page when the box was empty
                var altBox = $('#mqStockAlt');            // alternatives from config/stock_queries.php as chips
                var alts = res.alternatives || [];
                altBox.hidden = alts.length < 2;
                altBox.innerHTML = alts.map(function (a) {
                    return '<button type="button" data-q="' + esc(a) + '"' + (a === res.query ? ' class="is-active"' : '') + '>' + esc(a) + '</button>';
                }).join('');
                res.results.forEach(addStockCard);
                setStockStatus(res.results.length || !reset ? '«' + res.query + '» — найдено ' + res.total : 'Ничего не найдено — измените запрос.', false);
                stockMore.hidden = !res.has_more;
            }).catch(function (err) {
                setStockStatus('Ошибка: ' + err.message, true);
            });
        }

        // choosing a page resets the query so the keyword is derived from that page, then searches
        stockPageIn.addEventListener('input', function () {
            if (!bySlug[stockPageIn.value.trim()]) return;
            stockSel = {};
            stockQueryIn.value = '';
            updateStockAdd();
            stockLoad(true);
        });
        stockSearchBtn.addEventListener('click', function () { stockLoad(true); });
        stockQueryIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); stockLoad(true); } });
        stockMore.addEventListener('click', function () { stockPageNo++; stockLoad(false); });
        $('#mqStockAlt').addEventListener('click', function (e) {
            var b = e.target.closest('[data-q]');
            if (!b) return;
            stockQueryIn.value = b.getAttribute('data-q');
            stockLoad(true);
        });

        function importOne(photo, page, section) {
            var card = stockGrid.querySelector('.mq-stock-card[data-id="' + photo.id + '"]');
            if (card) card.classList.add('is-busy');
            var fd = new FormData();
            fd.append('csrf_token', csrf());
            fd.append('page_id', page.id);
            fd.append('section', section);
            fd.append('photo_id', photo.id);
            return fetch(BASE + '/admin/media/stock-import', {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf(), 'X-Requested-With': 'XMLHttpRequest' }
            }).then(stockJson).catch(function () {
                return { success: false, message: 'Сетевая ошибка' };
            }).then(function (res) {
                if (card) card.classList.remove('is-busy');
                if (!res.success) throw new Error(res.message || 'не удалось добавить');
                delete stockSel[photo.id];
                if (card) card.classList.remove('is-selected');
                rows.push({
                    id: nextId++, file: { name: res.original_name, size: 0 }, url: photo.thumb,
                    page: page, section: section, stock: true,
                    altRu: res.alt_text_ru || '', altUz: res.alt_text_uz || '', altRuTouched: true, altUzTouched: true,
                    state: 'done', message: ''
                });
                render();
            });
        }

        stockAdd.addEventListener('click', function () {
            var page = bySlug[stockPageIn.value.trim()];
            if (!page) { setStockStatus('Выберите страницу из списка.', true); return; }
            var section = $('#mqStockSection').value;
            var photos = Object.keys(stockSel).map(function (k) { return stockSel[k]; });
            if (!photos.length || stockBusy) return;
            stockBusy = true;
            updateStockAdd();
            var done = 0;
            var chain = Promise.resolve();
            photos.forEach(function (p) {
                chain = chain.then(function () {
                    setStockStatus('Добавляю ' + (done + 1) + ' из ' + photos.length + '…', false);
                    return importOne(p, page, section).then(function () { done++; });
                });
            });
            chain.then(function () {
                setStockStatus('Готово: добавлено ' + done + ' шт. Они в списке ниже.', false);
            }).catch(function (err) {
                setStockStatus((done ? 'Добавлено ' + done + ', затем ошибка: ' : 'Ошибка: ') + err.message, true);
            }).then(function () {
                stockBusy = false;
                updateStockAdd();
            });
        });
    }

    summary();
})();
