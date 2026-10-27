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
            '<span>' + esc(row.file.name) + ' · ' + sizeLabel(row.file.size) + '</span>' +
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

    function uploadRow(row) {
        row.state = 'uploading';
        refreshRow(row);
        var fd = new FormData();
        fd.append('file', row.file, row.file.name);
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

    summary();
})();
