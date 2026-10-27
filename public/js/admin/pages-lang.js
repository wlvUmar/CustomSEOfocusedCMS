// path: ./public/js/admin/pages-lang.js
// Page editor: RU / UZ switch. Shows one language's fields instead of every RU+UZ field at once.
// Works on every tab (title, content, meta, OG, JSON-LD …): any input/textarea named *_ru or *_uz.
//  • the choice is remembered (localStorage), default RU
//  • applied only after both TinyMCE editors are initialised (an editor created inside a hidden box mis-sizes)
//  • a hidden required field would silently block saving → on "invalid" we switch to that field's language and show the message
//  • badges on the buttons warn about parity: a field filled in one language but empty in the other
(function () {
    'use strict';

    var form = document.querySelector('form.admin-form');
    var bar = document.getElementById('lang-toggle');
    if (!form || !bar) return;

    var KEY = 'pageEditLang';
    var PARITY = ['title', 'meta_title', 'meta_description', 'content']; // the fields the RU↔UZ parity rule is about
    var mode = 'both';
    var wanted = 'ru';
    try {
        var saved = localStorage.getItem(KEY);
        if (saved === 'ru' || saved === 'uz') wanted = saved;
    } catch (e) {}

    // Tag each language field's wrapper (.form-group) and its side-by-side row once.
    var pairs = {};
    Array.prototype.forEach.call(form.querySelectorAll('[name$="_ru"], [name$="_uz"]'), function (el) {
        var m = (el.getAttribute('name') || '').match(/^(.*)_(ru|uz)$/);
        if (!m) return;
        var group = el.closest('.form-group');
        if (!group) return;
        group.classList.add('lang-field', 'lang-field--' + m[2]);
        var row = group.closest('.form-row');
        if (row) row.classList.add('lang-row');
        (pairs[m[1]] = pairs[m[1]] || {})[m[2]] = el;
    });

    function valueOf(el) {
        if (!el) return '';
        try {
            if (el.id && window.tinymce && tinymce.get(el.id)) return tinymce.get(el.id).getContent({ format: 'text' }).trim();
        } catch (e) {}
        return String(el.value || '').trim();
    }

    var buttons = bar.querySelectorAll('[data-lang]');

    function updateBadges() {
        var missing = { ru: [], uz: [] };
        PARITY.forEach(function (base) {
            var p = pairs[base];
            if (!p || !p.ru || !p.uz) return;
            var ru = valueOf(p.ru), uz = valueOf(p.uz);
            if (ru && !uz) missing.uz.push(base);
            if (uz && !ru) missing.ru.push(base);
        });
        Array.prototype.forEach.call(buttons, function (b) {
            var lang = b.getAttribute('data-lang');
            var warn = b.querySelector('.lang-warn');
            if (warn) { warn.remove(); warn = null; }
            if (lang === 'both') return;
            var list = missing[lang];
            if (!list.length) { b.removeAttribute('title'); return; }
            warn = document.createElement('span');
            warn.className = 'lang-warn';
            warn.textContent = '⚠ ' + list.length;
            b.appendChild(warn);
            b.setAttribute('title', 'Не заполнено (есть в другом языке): ' + list.join(', '));
        });
    }

    function setMode(m, remember) {
        mode = m;
        form.setAttribute('data-lang', m);
        Array.prototype.forEach.call(buttons, function (b) {
            var on = b.getAttribute('data-lang') === m;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (remember) { try { localStorage.setItem(KEY, m); } catch (e) {} }
        // editors that were hidden need a re-layout when shown again
        setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 0);
    }

    bar.addEventListener('click', function (e) {
        var b = e.target.closest('[data-lang]');
        if (b) setMode(b.getAttribute('data-lang'), true);
    });

    // A hidden field can't be focused by the browser's validation: switch to its language and point at the problem instead.
    form.addEventListener('invalid', function (e) {
        var g = e.target.closest && e.target.closest('.lang-field');
        if (!g || mode === 'both') return;
        var hiddenLang = mode === 'ru' ? 'uz' : 'ru';
        if (g.classList.contains('lang-field--' + hiddenLang)) {
            setMode(hiddenLang, false);
            setTimeout(function () { try { e.target.reportValidity(); } catch (err) {} }, 0);
        }
    }, true);

    var timer = null;
    function scheduleBadges() { clearTimeout(timer); timer = setTimeout(updateBadges, 250); }
    form.addEventListener('input', scheduleBadges);
    form.addEventListener('change', scheduleBadges);

    // Wait for TinyMCE (content_ru / content_uz) before hiding anything; give up after ~6 s and apply anyway.
    var tries = 0;
    (function check() {
        var ready = true;
        if (window.tinymce) {
            ['content_ru', 'content_uz'].forEach(function (id) {
                if (!document.getElementById(id)) return;
                var ed = tinymce.get(id);
                if (!ed || !ed.initialized) ready = false;
                else if (!ed._langBound) { ed._langBound = true; ed.on('input change keyup', scheduleBadges); }
            });
        }
        if (ready || ++tries > 40) {
            setMode(wanted, false);
            updateBadges();
        } else {
            setTimeout(check, 150);
        }
    })();

    setMode('both', false); // until the editors are ready everything stays visible, exactly as before
    updateBadges();
})();
