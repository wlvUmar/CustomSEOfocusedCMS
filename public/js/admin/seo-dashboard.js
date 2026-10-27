// path: ./public/js/admin/seo-dashboard.js
// Admin SEO Dashboard (/admin/search-console). Every section loads independently from
// /admin/search-console/data?section=… so one failing Google call never blocks the others.
(function () {
    'use strict';

    var root = document.getElementById('sd');
    if (!root) return;

    var BASE = window.baseUrl || '';
    var ERRORS = {
        gsc_not_connected: 'Search Console не подключён (AI Studio → Connect GSC).',
        gsc_unavailable: 'Хранилище токенов GSC недоступно.',
        gsc_auth_required: 'Нужно заново авторизовать Search Console (токен отозван).',
        gsc_quota_exceeded: 'Превышена квота Google — повторите позже.',
        gsc_forbidden: 'Google отказал в доступе: у подключённого аккаунта нет прав для этого запроса.',
        gsc_api_error: 'Ошибка Google API — повторите позже.',
        invalid_param: 'Неверный параметр запроса.',
        unknown_section: 'Неизвестный раздел.'
    };

    var state = { opp: null, oppTab: 'striking_distance', chart: null };

    // ── helpers ──────────────────────────────────────────────
    function $(sel, el) { return (el || document).querySelector(sel); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function fmtInt(n) { return new Intl.NumberFormat('ru-RU').format(Number(n || 0)); }
    function fmtPct(fraction) { return (Number(fraction || 0) * 100).toFixed(2) + '%'; }
    function fmtPos(p) { return Number(p || 0) ? Number(p).toFixed(1) : '—'; }
    function days() { return $('#sdDays').value; }
    function body(sectionEl) { return $('.sd-body', sectionEl); }

    function pathOf(url) {
        try { var u = new URL(url); return u.pathname + u.search || '/'; } catch (e) { return url; }
    }
    function pageLink(url) {
        if (!url) return '—';
        return '<a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer"><span class="sd-cut" title="' + esc(url) + '">' + esc(pathOf(url)) + '</span></a>';
    }

    function delta(change, opts) {
        opts = opts || {};
        if (change == null) return '<span class="sd-delta sd-delta--flat">нет данных за пред. период</span>';
        var good = opts.invert ? change < 0 : change > 0;
        var cls = change === 0 ? 'flat' : (good ? 'up' : 'down');
        var arrow = change === 0 ? '•' : (change > 0 ? '▲' : '▼');
        var val = opts.abs ? Math.abs(change).toFixed(2) : Math.abs(change) + '%';
        return '<span class="sd-delta sd-delta--' + cls + '">' + arrow + ' ' + val + '</span>';
    }

    function table(headers, rows, numFrom) {
        if (!rows.length) return '<div class="sd-empty">Нет данных за период.</div>';
        var head = headers.map(function (h, i) {
            return '<th' + (numFrom != null && i >= numFrom ? ' class="num"' : '') + '>' + esc(h) + '</th>';
        }).join('');
        return '<div class="sd-scroll"><table class="sd-table"><thead><tr>' + head + '</tr></thead><tbody>' + rows.join('') + '</tbody></table></div>';
    }

    function load(section, params) {
        var q = new URLSearchParams(Object.assign({ section: section }, params || {}));
        return fetch(BASE + '/admin/search-console/data?' + q.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () { return { success: false, error: 'bad_response' }; });
        }).catch(function () {
            return { success: false, error: 'network' };
        });
    }

    function showError(el, res) {
        var msg = ERRORS[res.error] || (res.error === 'network' ? 'Сетевая ошибка.' : 'Не удалось загрузить (' + esc(res.error || 'error') + ').');
        el.innerHTML = '<div class="sd-error">' + esc(msg) + '</div>';
    }

    function run(section, params, el, render) {
        el.innerHTML = '<div class="sd-loading">Загрузка…</div>';
        return load(section, params).then(function (res) {
            if (!res.success) { showError(el, res); return; }
            render(res.data, el);
        });
    }

    // ── overview ─────────────────────────────────────────────
    function renderOverview(d, el) {
        var t = d.totals, c = d.change_pct;
        var tiles = [
            ['Клики', fmtInt(t.clicks), delta(c.clicks)],
            ['Показы', fmtInt(t.impressions), delta(c.impressions)],
            ['CTR', fmtPct(t.ctr), delta(c.ctr)],
            ['Средняя позиция', fmtPos(t.position), c.position_delta == null ? delta(null) : delta(c.position_delta, { invert: true, abs: true })]
        ].map(function (k) {
            return '<div class="sd-kpi"><div class="sd-kpi__label">' + k[0] + '</div><div class="sd-kpi__value">' + k[1] + '</div>' + k[2] + '</div>';
        }).join('');
        el.innerHTML = '<div class="sd-kpis">' + tiles + '</div>' +
            '<div class="sd-chart"><canvas id="sdChart" aria-label="Клики и показы по дням"></canvas></div>' +
            '<div class="sd-note">Период: ' + esc(d.period.start) + ' — ' + esc(d.period.end) + ' · сравнение с ' + esc(d.previous_period.start) + ' — ' + esc(d.previous_period.end) + '. Позиция: ▼ зелёный = выросла (лучше).</div>';

        if (typeof Chart === 'undefined' || !d.series.length) return;
        if (state.chart) { state.chart.destroy(); state.chart = null; }
        state.chart = new Chart($('#sdChart'), {
            type: 'line',
            data: {
                labels: d.series.map(function (s) { return s.date; }),
                datasets: [
                    { label: 'Клики', data: d.series.map(function (s) { return s.clicks; }), borderColor: '#0e9f8a', backgroundColor: '#0e9f8a', yAxisID: 'y', tension: .25, pointRadius: 0 },
                    { label: 'Показы', data: d.series.map(function (s) { return s.impressions; }), borderColor: '#3b82f6', backgroundColor: '#3b82f6', yAxisID: 'y1', tension: .25, pointRadius: 0 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { position: 'left', beginAtZero: true, title: { display: true, text: 'Клики' } },
                    y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Показы' } }
                }
            }
        });
    }

    // ── brand ────────────────────────────────────────────────
    function renderBrand(d, el) {
        var s = d.summary;
        if (!d.queries.length) {
            el.innerHTML = '<div class="sd-empty">По запросам, содержащим «' + esc(d.term) + '», нет показов за период ' + esc(d.period.start) + ' — ' + esc(d.period.end) +
                '. Google пока не связывает этот запрос с вашим сайтом (или написание отличается).</div>';
            return;
        }
        var share = s.homepage_impression_share == null ? '—' : Math.round(s.homepage_impression_share * 100) + '%';
        var facts = '<dl class="sd-facts">' +
            '<dt>Запросов с брендом</dt><dd>' + fmtInt(s.matching_queries) + '</dd>' +
            '<dt>Показы / клики</dt><dd>' + fmtInt(s.impressions) + ' / ' + fmtInt(s.clicks) + ' (CTR ' + fmtPct(s.ctr) + ')</dd>' +
            '<dt>Средняя позиция</dt><dd>' + fmtPos(s.position) + ' <span class="sd-note">(1.0–1.5 ≈ вы №1)</span></dd>' +
            '<dt>Главная страница</dt><dd>' + share + ' показов приходится на главную</dd>' +
            '<dt>Основная страница</dt><dd>' + pageLink(s.top_page) + '</dd></dl>';
        var rows = d.queries.map(function (q) {
            var pages = q.pages.map(function (p) { return pageLink(p.page); }).join('<br>');
            return '<tr><td>' + esc(q.query) + '</td><td class="num">' + fmtInt(q.impressions) + '</td><td class="num">' + fmtInt(q.clicks) +
                '</td><td class="num">' + fmtPct(q.ctr) + '</td><td class="num">' + fmtPos(q.position) + '</td><td>' + pages + '</td></tr>';
        });
        el.innerHTML = facts + '<h3>Запросы</h3>' + table(['Запрос', 'Показы', 'Клики', 'CTR', 'Позиция', 'Страницы'], rows, 1) +
            '<div class="sd-note">' + esc(d.note) + '</div>';
    }

    // ── opportunities ────────────────────────────────────────
    function renderOpp() {
        var el = body($('#sdOpp'));
        var d = state.opp;
        if (!d) return;
        var block = d[state.oppTab];
        var isLow = state.oppTab === 'low_ctr';
        var rows = block.rows.map(function (r) {
            return '<tr><td>' + esc(r.query) + '</td><td>' + pageLink(r.page) + '</td><td class="num">' + fmtInt(r.impressions) +
                '</td><td class="num">' + fmtInt(r.clicks) + '</td><td class="num">' + fmtPct(r.ctr) + '</td><td class="num">' + fmtPos(r.position) + '</td>' +
                (isLow ? '<td class="num">' + fmtInt(r.clicks_gap) + '</td>' : '') + '</tr>';
        });
        var heads = ['Запрос', 'Страница', 'Показы', 'Клики', 'CTR', 'Позиция'].concat(isLow ? ['Недобор кликов'] : []);
        el.innerHTML = '<p class="sd-def">' + esc(block.definition) + '</p>' + table(heads, rows, 2) +
            '<div class="sd-note">' + esc(d.note) + ' Проанализировано строк: ' + fmtInt(d.rows_analysed) + '.</div>';
    }

    // ── movers ───────────────────────────────────────────────
    function renderMovers(d, el) {
        function list(rows, title) {
            var trs = rows.map(function (r) {
                var pd = r.position_delta == null ? '—' : (r.position_delta > 0 ? '+' : '') + r.position_delta.toFixed(1);
                var cls = r.clicks_delta > 0 ? 'sd-delta--up' : (r.clicks_delta < 0 ? 'sd-delta--down' : 'sd-delta--flat');
                return '<tr><td>' + pageLink(r.page) + (r.status !== 'changed' ? ' <span class="sd-chip">' + (r.status === 'new' ? 'новая' : 'нет показов') + '</span>' : '') +
                    '</td><td class="num">' + fmtInt(r.current.clicks) + ' <span class="sd-delta ' + cls + '">(' + (r.clicks_delta > 0 ? '+' : '') + r.clicks_delta + ')</span></td>' +
                    '<td class="num">' + fmtInt(r.current.impressions) + '</td><td class="num">' + fmtPos(r.current.position) + ' <span class="sd-note">(' + pd + ')</span></td></tr>';
            });
            return '<div><h3>' + title + '</h3>' + table(['Страница', 'Клики (Δ)', 'Показы', 'Позиция (Δ)'], trs, 1) + '</div>';
        }
        var losing = d.rows.filter(function (r) { return r.clicks_delta < 0; }).slice(0, 10);
        var gaining = d.rows.filter(function (r) { return r.clicks_delta > 0; }).slice(0, 10);
        el.innerHTML = '<div class="sd-two">' + list(losing, 'Теряют клики') + list(gaining, 'Растут') + '</div>' +
            '<div class="sd-note">Текущий период ' + esc(d.current_period.start) + ' — ' + esc(d.current_period.end) + ' против ' + esc(d.previous_period.start) + ' — ' + esc(d.previous_period.end) +
            '. Позиция (Δ): отрицательное число = страница поднялась.</div>';
    }

    // ── audit ────────────────────────────────────────────────
    function renderAudit(d, el) {
        var sev = d.issues_by_severity;
        var codes = Object.keys(d.issues_by_code).slice(0, 8).map(function (c) {
            return '<span class="sd-chip">' + esc(c) + ' · ' + d.issues_by_code[c] + '</span>';
        }).join(' ');
        var summary = '<div class="sd-chips sd-chips--mb">' +
            '<span class="sd-chip sd-sev-error">Ошибки: ' + sev.error + '</span>' +
            '<span class="sd-chip sd-sev-warn">Предупреждения: ' + sev.warn + '</span>' +
            '<span class="sd-chip sd-sev-info">Замечания: ' + sev.info + '</span>' +
            '<span class="sd-chip">Страниц проверено: ' + d.pages_checked + '</span>' +
            '<span class="sd-chip">Со проблемами: ' + d.pages_with_issues + '</span></div>' +
            (codes ? '<div class="sd-chips sd-chips--mb">' + codes + '</div>' : '');
        var rows = d.pages.map(function (p) {
            var chips = p.issues.map(function (i) {
                return '<span class="sd-chip sd-sev-' + esc(i.severity) + '" title="' + esc(i.detail) + '">' + esc(i.code) + (i.lang ? ' (' + esc(i.lang) + ')' : '') + '</span>';
            }).join(' ');
            return '<tr><td><a href="' + esc(BASE) + '/admin/pages/edit/' + p.id + '">' + esc(p.slug) + '</a></td><td class="num">' + p.issue_count + '</td><td><div class="sd-chips">' + chips + '</div></td></tr>';
        });
        el.innerHTML = summary + (rows.length ? table(['Страница', 'Проблем', 'Что найдено (наведите — подробности)'], rows, 1)
            : '<div class="sd-empty">Проблем для выбранной серьёзности не найдено.</div>') +
            '<div class="sd-note">Пороги эвристические (title ≤ ' + d.thresholds.meta_title[1] + ', description ≤ ' + d.thresholds.meta_description[1] + ' знаков), это не правила Google. «no_inbound_links» считает ссылки из текста страниц и виджеты ссылок; меню и футер не учитываются (корневые страницы пропускаются). Короткий текст у карточек моделей — норма, поэтому thin_content только «замечание».</div>';
    }

    // ── indexing ─────────────────────────────────────────────
    function renderSitemaps(d, el) {
        var rows = d.sitemaps.map(function (s) {
            var sub = 0, idx = 0;
            s.contents.forEach(function (c) { sub += c.submitted || 0; idx += c.indexed || 0; });
            return '<tr><td>' + esc(s.path) + '</td><td class="num">' + fmtInt(sub) + '</td><td class="num">' + fmtInt(idx) + '</td><td class="num">' + s.errors + '</td><td class="num">' + s.warnings +
                '</td><td>' + esc((s.last_downloaded || '').replace('T', ' ').slice(0, 16)) + '</td></tr>';
        });
        el.innerHTML = rows.length ? table(['Sitemap', 'Отправлено', 'В индексе', 'Ошибки', 'Предупр.', 'Последняя загрузка'], rows, 1)
            : '<div class="sd-empty">Search Console не знает ни об одном sitemap. Отправьте его в Search Console.</div>';
    }

    function renderInspect(d, el) {
        var ix = d.index, m = d.mobile_usability, r = d.rich_results;
        var ok = ix.verdict === 'PASS';
        var rich = (r.detected || []).map(function (x) {
            return esc(x.type) + (x.issues.length ? ' — ' + esc(x.issues.join('; ')) : '');
        }).join('<br>') || '—';
        el.innerHTML = '<dl class="sd-facts">' +
            '<dt>URL</dt><dd>' + esc(d.url) + '</dd>' +
            '<dt>В индексе Google</dt><dd><span class="sd-chip ' + (ok ? 'sd-chip--ok' : 'sd-chip--bad') + '">' + esc(ix.verdict || '—') + '</span> ' + esc(ix.coverage_state || '') + '</dd>' +
            '<dt>Индексация</dt><dd>' + esc(ix.indexing_state || '—') + ' · robots: ' + esc(ix.robots_txt_state || '—') + ' · загрузка: ' + esc(ix.page_fetch_state || '—') + '</dd>' +
            '<dt>Последний обход</dt><dd>' + esc(ix.last_crawl_time || '—') + (ix.crawled_as ? ' (' + esc(ix.crawled_as) + ')' : '') + '</dd>' +
            '<dt>Canonical Google</dt><dd>' + esc(ix.google_canonical || '—') + '</dd>' +
            '<dt>Canonical сайта</dt><dd>' + esc(ix.user_canonical || '—') + (ix.canonical_matches === false ? ' <span class="sd-chip sd-chip--bad">не совпадает</span>' : '') + '</dd>' +
            '<dt>Мобильная версия</dt><dd>' + esc(m.verdict || '—') + (m.issues && m.issues.length ? ' — ' + esc(m.issues.join(', ')) : '') + '</dd>' +
            '<dt>Расширенные результаты</dt><dd>' + esc(r.verdict || '—') + '<br>' + rich + '</dd>' +
            '</dl>' + (d.inspection_link ? '<div class="sd-note"><a href="' + esc(d.inspection_link) + '" target="_blank" rel="noopener noreferrer">Открыть в Search Console</a></div>' : '') +
            '<div class="sd-note">' + esc(d.note) + '</div>';
    }

    // ── wiring ───────────────────────────────────────────────
    function loadGsc() {
        var d = days();
        run('overview', { days: d }, body($('#sdOverview')), renderOverview);
        run('opportunities', { days: d }, body($('#sdOpp')), function (data) { state.opp = data; renderOpp(); });
        run('movers', { days: d }, body($('#sdMovers')), renderMovers);
        run('sitemaps', {}, $('#sdSitemaps'), renderSitemaps);
        var brand = $('#sdBrandInput').value.trim();
        if (brand) loadBrand(brand);
    }

    function loadAudit() {
        run('audit', { severity: $('#sdAuditSeverity').value }, body($('#sdAudit')), renderAudit);
    }

    function loadBrand(term) {
        try { localStorage.setItem('sdBrand', term); } catch (e) {}
        run('brand', { q: term, days: days() }, body($('#sdBrand')), renderBrand);
    }

    $('#sdBrandForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var term = $('#sdBrandInput').value.trim();
        if (term) loadBrand(term);
    });

    $('#sdInspectForm').addEventListener('submit', function (e) {
        e.preventDefault();
        run('inspect', { url: $('#sdInspectInput').value.trim() }, $('#sdInspectResult'), renderInspect);
    });

    $('#sdOppTabs').addEventListener('click', function (e) {
        var btn = e.target.closest('.sd-tab');
        if (!btn) return;
        state.oppTab = btn.getAttribute('data-tab');
        Array.prototype.forEach.call(this.querySelectorAll('.sd-tab'), function (b) { b.classList.toggle('is-active', b === btn); });
        renderOpp();
    });

    $('#sdDays').addEventListener('change', loadGsc);
    $('#sdAuditSeverity').addEventListener('change', loadAudit);
    $('#sdRefresh').addEventListener('click', function () { loadGsc(); loadAudit(); });

    try { $('#sdBrandInput').value = localStorage.getItem('sdBrand') || ''; } catch (e) {}
    loadGsc();
    loadAudit();
})();
