// FILE: public/js/admin/ai-studio.js
// AI Studio client: streams agent turns over SSE (fetch reader — EventSource
// cannot POST), renders the transcript with safe markdown, drives the live
// preview pane, tracks token usage, and handles the approval flow for guarded
// (destructive) tool calls.

(function() {
    'use strict';

    const cfg = window.AI_STUDIO;
    if (!cfg) return;

    const els = {
        form: document.getElementById('ai-form'),
        input: document.getElementById('ai-input'),
        send: document.getElementById('ai-send'),
        stop: document.getElementById('ai-stop'),
        transcript: document.getElementById('ai-transcript'),
        status: document.getElementById('ai-status'),
        usage: document.getElementById('ai-usage'),
        activity: document.getElementById('ai-activity'),
        activityText: document.getElementById('ai-activity-text'),
        activityTimer: document.getElementById('ai-activity-timer'),
        approval: document.getElementById('ai-approval'),
        approvalPlan: document.getElementById('ai-approval-plan'),
        approvalReason: document.getElementById('ai-approval-reason'),
        approve: document.getElementById('ai-approve'),
        deny: document.getElementById('ai-deny'),
        model: document.getElementById('ai-model'),
        newSession: document.getElementById('ai-new-session'),
        suggestions: document.getElementById('ai-suggestions'),
        previewFrame: document.getElementById('ai-preview-frame'),
        previewHint: document.getElementById('ai-preview-hint'),
        previewOpen: document.getElementById('ai-preview-open'),
        gscFile: document.getElementById('ai-gsc-file'),
        gscReplace: document.getElementById('ai-gsc-replace'),
        gscStatus: document.getElementById('ai-gsc-status'),
        gscDisconnect: document.getElementById('ai-gsc-disconnect'),
    };

    let busy = false;
    let history = [];           // [{role:'user'|'assistant', content}] sent for model context
    let pendingApproval = null; // {call_id, plan, reason}
    let pendingContext = null;  // interrupted tool_call + result, resent on the follow-up run
    let abortCtrl = null;       // AbortController for the in-flight request
    let typingEl = null;
    let lastPreviewHtml = '';
    let activityTimerId = null; // interval driving the elapsed-time readout
    let activityStartedAt = 0;

    // ---- Model selector persistence --------------------------------------
    const savedModel = localStorage.getItem('ai-studio-model');
    if (savedModel && Array.prototype.some.call(els.model.options, o => o.value === savedModel)) {
        els.model.value = savedModel;
    }
    els.model.addEventListener('change', () => {
        localStorage.setItem('ai-studio-model', els.model.value);
    });

    // ---- GSC: CSV upload + live Connect/Disconnect --------------------------
    (function initGsc() {
        if (!els.gscFile && !els.gscDisconnect) return;
        function setGscStatus(text, kind) {
            if (!els.gscStatus) return;
            els.gscStatus.textContent = text;
            els.gscStatus.className = 'ai-gsc-bar__hint' + (kind ? ' ai-gsc-bar__hint--' + kind : '');
        }
        if (els.gscDisconnect) {
            els.gscDisconnect.addEventListener('click', async () => {
                if (!confirm('Disconnect Search Console? Live GSC tools will fall back to local CSV data.')) return;
                els.gscDisconnect.disabled = true;
                setGscStatus('Disconnecting…', 'busy');
                try {
                    const fd = new FormData();
                    fd.append('csrf_token', cfg.csrf);
                    const resp = await fetch(cfg.baseUrl + '/admin/ai-studio/gsc-disconnect', { method: 'POST', body: fd });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok || !data.success) throw new Error((data && data.message) || ('Failed (' + resp.status + ')'));
                    setGscStatus('Disconnected — use Connect GSC or CSV fallback', 'warn');
                    addAgentBubble('🔌 GSC disconnected. Tools now use local `gsc_data` (CSV) until you reconnect.');
                    setTimeout(() => location.reload(), 800);
                } catch (err) {
                    setGscStatus('Disconnect failed: ' + err.message, 'error');
                    els.gscDisconnect.disabled = false;
                }
            });
        }
        if (!els.gscFile) return;
        els.gscFile.addEventListener('change', async () => {
            const file = els.gscFile.files && els.gscFile.files[0];
            if (!file) return;
            if (!file.name.toLowerCase().endsWith('.csv')) {
                setGscStatus('Only .csv files are allowed', 'error');
                addAgentBubble('⚠ GSC upload: only .csv files are allowed.');
                els.gscFile.value = '';
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                setGscStatus('File too large (max 10 MB)', 'error');
                addAgentBubble('⚠ GSC upload: file too large (max 10 MB).');
                els.gscFile.value = '';
                return;
            }
            setGscStatus('Uploading ' + file.name + '…', 'busy');
            setActivity('Uploading GSC CSV…');
            const fd = new FormData();
            fd.append('csrf_token', cfg.csrf);
            fd.append('file', file);
            fd.append('replace', els.gscReplace && els.gscReplace.checked ? '1' : '0');
            try {
                const resp = await fetch(cfg.baseUrl + '/admin/ai-studio/upload-gsc', {
                    method: 'POST',
                    body: fd,
                });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data.success) {
                    throw new Error((data && data.message) || ('Upload failed (' + resp.status + ')'));
                }
                const n = data.total || (data.imported + data.updated) || 0;
                const msg = 'GSC CSV imported: ' + n.toLocaleString() + ' rows' + (data.skipped ? ' (' + data.skipped + ' skipped)' : '') + (data.errors && data.errors.length ? ' — ' + data.errors.join(' ') : '');
                setGscStatus(msg, 'ok');
                addAgentBubble('✅ ' + msg + '\n\nThe AI can now use `get_gsc_overview`, `get_page_gsc`, `get_gsc_pages`, `get_gsc_queries` and `search_gsc_queries` to include Search Console data in audits. Try: "Give me a GSC overview" or "Audit the services page using GSC + analytics."');
                hideActivity();
            } catch (err) {
                setGscStatus('Upload failed: ' + err.message, 'error');
                addAgentBubble('⚠ GSC upload failed: ' + err.message);
                hideActivity();
            } finally {
                els.gscFile.value = '';
            }
        });
    })();

    // ---- New session ------------------------------------------------------
    els.newSession.addEventListener('click', () => {
        if (busy) return;
        history = [];
        pendingApproval = null;
        pendingContext = null;
        els.approval.hidden = true;
        els.transcript.innerHTML = '';
        els.previewFrame.removeAttribute('srcdoc');
        lastPreviewHtml = '';
        els.previewHint.textContent = 'Renders here after each turn';
        setStatus('Ready');
        updateUsage(null);
        hideActivity();
        els.input.value = '';
        els.input.style.height = 'auto';
        els.input.focus();
    });

    // ---- Busy state -------------------------------------------------------
    function setBusy(value) {
        busy = value;
        els.send.disabled = value;
        els.input.disabled = value;
        els.model.disabled = value;
        els.newSession.disabled = value;
        els.stop.hidden = !value;
        els.suggestions.classList.toggle('ai-suggestions--disabled', value);
        Array.prototype.forEach.call(els.suggestions.querySelectorAll('.ai-chip'), chip => {
            chip.disabled = value;
        });
        if (!value) abortCtrl = null;
    }

    // ---- DOM helpers ------------------------------------------------------
    function setStatus(text, kind) {
        els.status.textContent = text;
        els.status.className = 'ai-status' + (kind ? ' ai-status--' + kind : '');
    }

    function updateUsage(u) {
        if (!u) {
            els.usage.textContent = '0 tok';
            els.usage.title = '';
            return;
        }
        const total = u.total || 0;
        const hasCost = typeof u.cost === 'number' && u.cost > 0;
        els.usage.textContent = total.toLocaleString() + ' tok' + (hasCost ? ' · $' + u.cost.toFixed(4) : '');
        els.usage.title = 'Prompt ' + (u.prompt || 0).toLocaleString() + ' · completion '
            + (u.completion || 0).toLocaleString() + (hasCost ? ' · cost $' + u.cost.toFixed(4) : '');
    }

    // ---- Activity bar -------------------------------------------------------
    // A compact "is it alive" strip between the transcript and the composer:
    // spinner + current phase + elapsed time, driven by SSE 'activity' events
    // (with event-derived fallbacks if one is ever missed).

    function fmtElapsed(ms) {
        const s = Math.max(0, Math.floor(ms / 1000));
        return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    }

    function setActivity(text) {
        if (!els.activity) return;
        els.activityText.textContent = text;
        els.activity.hidden = false;
        if (activityTimerId === null) {
            activityStartedAt = Date.now();
            els.activityTimer.textContent = '0:00';
            activityTimerId = setInterval(() => {
                els.activityTimer.textContent = fmtElapsed(Date.now() - activityStartedAt);
            }, 1000);
        }
    }

    function hideActivity() {
        if (activityTimerId !== null) {
            clearInterval(activityTimerId);
            activityTimerId = null;
        }
        if (els.activity) els.activity.hidden = true;
    }

    function scrollTranscript() {
        els.transcript.scrollTop = els.transcript.scrollHeight;
    }

    function addUserBubble(text) {
        const wrap = document.createElement('div');
        wrap.className = 'ai-msg ai-msg--user';
        const body = document.createElement('div');
        body.className = 'ai-msg__body';
        body.textContent = text;
        wrap.appendChild(body);
        els.transcript.appendChild(wrap);
        scrollTranscript();
    }

    function addAgentBubble(text) {
        hideTyping();
        const wrap = document.createElement('div');
        wrap.className = 'ai-msg ai-msg--agent';
        const body = document.createElement('div');
        body.className = 'ai-msg__body md';
        body.appendChild(renderMarkdown(text));
        wrap.appendChild(body);
        els.transcript.appendChild(wrap);
        scrollTranscript();
    }

    function addToolEvent(tool, summary, ok) {
        hideTyping();
        const wrap = document.createElement('div');
        wrap.className = 'ai-tool-event' + (ok ? '' : ' ai-tool-event--error');
        const head = document.createElement('button');
        head.type = 'button';
        head.className = 'ai-tool-event__head';
        head.innerHTML = '<span class="ai-tool-event__dot"></span><span class="ai-tool-event__name"></span><span class="ai-tool-event__chev"></span>';
        head.querySelector('.ai-tool-event__name').textContent = tool;
        const body = document.createElement('pre');
        body.className = 'ai-tool-event__body';
        body.textContent = summary || '';
        wrap.appendChild(head);
        wrap.appendChild(body);
        head.addEventListener('click', () => {
            body.hidden = !body.hidden;
            head.querySelector('.ai-tool-event__chev').textContent = body.hidden ? '▸' : '▾';
        });
        els.transcript.appendChild(wrap);
        scrollTranscript();
    }

    function addApprovalEvent(plan) {
        hideTyping();
        const wrap = document.createElement('div');
        wrap.className = 'ai-tool-event ai-tool-event--approval';
        const head = document.createElement('button');
        head.type = 'button';
        head.className = 'ai-tool-event__head';
        head.innerHTML = '<span class="ai-tool-event__dot"></span><span class="ai-tool-event__name"></span><span class="ai-tool-event__chev"></span>';
        head.querySelector('.ai-tool-event__name').textContent = 'Approval requested';
        const body = document.createElement('div');
        body.className = 'ai-tool-event__body';
        body.textContent = plan;
        wrap.appendChild(head);
        wrap.appendChild(body);
        head.addEventListener('click', () => {
            body.hidden = !body.hidden;
            head.querySelector('.ai-tool-event__chev').textContent = body.hidden ? '▸' : '▾';
        });
        els.transcript.appendChild(wrap);
        scrollTranscript();
    }

    function showTyping() {
        if (typingEl && typingEl.isConnected) return;
        typingEl = document.createElement('div');
        typingEl.className = 'ai-typing';
        for (let k = 0; k < 3; k++) typingEl.appendChild(document.createElement('span'));
        els.transcript.appendChild(typingEl);
        scrollTranscript();
    }

    function hideTyping() {
        if (typingEl && typingEl.isConnected) typingEl.remove();
        typingEl = null;
    }

    function updatePreview(html) {
        lastPreviewHtml = html;
        els.previewHint.textContent = 'Updated ' + new Date().toLocaleTimeString();
        els.previewFrame.style.opacity = '0';
        els.previewFrame.setAttribute('srcdoc', html);
    }

    els.previewFrame.addEventListener('load', () => {
        els.previewFrame.style.opacity = '1';
    });

    els.previewOpen.addEventListener('click', () => {
        if (!lastPreviewHtml) return;
        const blob = new Blob([lastPreviewHtml], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        window.open(url, '_blank');
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    });

    function showApproval(p) {
        pendingApproval = p;
        els.approvalPlan.textContent = p.plan;
        els.approvalReason.textContent = p.reason || '';
        els.approval.hidden = false;
        els.approve.disabled = false;
        els.deny.disabled = false;
    }

    function hideApproval() {
        pendingApproval = null;
        els.approval.hidden = true;
    }

    // ---- Safe markdown rendering ------------------------------------------
    // Builds DOM nodes only (no innerHTML for model text), so model output can
    // never inject markup. Links are restricted to safe protocols.

    const SAFE_URL = /^(https?:|mailto:|tel:)/i;
    const RE_HEADING = /^(#{1,4})\s+(.*)$/;
    const RE_HR = /^\s*([-*_])(\s*\1){2,}\s*$/;
    const RE_QUOTE = /^\s*>/;
    const RE_LIST = /^\s*([-*+]|\d+\.)\s+(.*)$/;
    const RE_FENCE = /^\s*```/;
    const INLINE_RE = /(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`|\[[^\]]*\]\([^)]+\))/g;

    function renderMarkdown(text) {
        const frag = document.createDocumentFragment();
        const lines = String(text).split('\n');
        let i = 0;
        while (i < lines.length) {
            if (RE_FENCE.test(lines[i])) {
                const lang = lines[i].trim().slice(3).trim();
                i++;
                const buf = [];
                while (i < lines.length && !RE_FENCE.test(lines[i])) {
                    buf.push(lines[i]);
                    i++;
                }
                i++; // skip closing fence
                const pre = document.createElement('pre');
                const code = document.createElement('code');
                if (lang) code.className = 'lang-' + lang.replace(/[^\w-]/g, '');
                code.textContent = buf.join('\n');
                pre.appendChild(code);
                frag.appendChild(pre);
                continue;
            }
            const para = [];
            while (i < lines.length && !RE_FENCE.test(lines[i])) {
                para.push(lines[i]);
                i++;
            }
            frag.appendChild(parseBlocks(para));
        }
        return frag;
    }

    function parseBlocks(lines) {
        const frag = document.createDocumentFragment();
        let i = 0;
        const n = lines.length;
        while (i < n) {
            const line = lines[i];
            if (line.trim() === '') { i++; continue; }

            const h = line.match(RE_HEADING);
            if (h) {
                const el = document.createElement('h' + h[1].length);
                appendInline(el, h[2]);
                frag.appendChild(el);
                i++;
                continue;
            }

            if (RE_HR.test(line)) {
                frag.appendChild(document.createElement('hr'));
                i++;
                continue;
            }

            if (RE_QUOTE.test(line)) {
                const q = [];
                while (i < n && RE_QUOTE.test(lines[i])) {
                    q.push(lines[i].replace(/^\s*>\s?/, ''));
                    i++;
                }
                const bq = document.createElement('blockquote');
                bq.appendChild(parseBlocks(q));
                frag.appendChild(bq);
                continue;
            }

            const list = line.match(RE_LIST);
            if (list) {
                const ordered = /\d+\./.test(list[1]);
                const el = document.createElement(ordered ? 'ol' : 'ul');
                while (i < n) {
                    const m = lines[i].match(RE_LIST);
                    if (!m || ordered !== /\d+\./.test(m[1])) break;
                    const li = document.createElement('li');
                    appendInline(li, m[2]);
                    el.appendChild(li);
                    i++;
                }
                frag.appendChild(el);
                continue;
            }

            // GFM-style table: | a | b | rows with a --- separator row
            if (line.trim().charAt(0) === '|') {
                const rows = [];
                while (i < n && lines[i].trim().charAt(0) === '|') {
                    rows.push(lines[i]);
                    i++;
                }
                const table = buildTable(rows);
                if (table) {
                    frag.appendChild(table);
                } else {
                    // stray pipe lines — render as plain text
                    const p = document.createElement('p');
                    appendInline(p, rows.join(' '));
                    frag.appendChild(p);
                }
                continue;
            }

            const pLines = [];
            while (i < n) {
                const t = lines[i];
                if (t.trim() === '' || RE_HEADING.test(t) || RE_HR.test(t) || RE_QUOTE.test(t) || RE_LIST.test(t)
                    || t.trim().charAt(0) === '|') break;
                pLines.push(t);
                i++;
            }
            const p = document.createElement('p');
            appendInline(p, pLines.join(' '));
            frag.appendChild(p);
        }
        return frag;
    }

    function splitRow(line) {
        let s = String(line).trim();
        if (s.charAt(0) === '|') s = s.slice(1);
        if (s.charAt(s.length - 1) === '|') s = s.slice(0, -1);
        return s.split('|').map(c => c.trim());
    }

    function buildTable(rows) {
        if (rows.length < 2) return null;
        const headerCells = splitRow(rows[0]);
        const sepCells = splitRow(rows[1]);
        if (!headerCells.length || !sepCells.length) return null;
        const isSep = sepCells.every(c => /^\s*:?-{3,}:?\s*$/.test(c));
        if (!isSep) return null;

        const wrap = document.createElement('div');
        wrap.className = 'ai-md-table-wrap';
        const table = document.createElement('table');

        const thead = document.createElement('thead');
        const trh = document.createElement('tr');
        headerCells.forEach(c => {
            const th = document.createElement('th');
            appendInline(th, c);
            trh.appendChild(th);
        });
        thead.appendChild(trh);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        for (let r = 2; r < rows.length; r++) {
            const cells = splitRow(rows[r]);
            if (!cells.length) continue;
            const tr = document.createElement('tr');
            cells.forEach(c => {
                const td = document.createElement('td');
                appendInline(td, c);
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        }
        table.appendChild(tbody);
        wrap.appendChild(table);
        return wrap;
    }

    function appendInline(parent, text) {
        const parts = String(text).split(INLINE_RE);
        parts.forEach(part => {
            if (!part) return;
            if (part.length > 4 && part.startsWith('**') && part.endsWith('**')) {
                const b = document.createElement('strong');
                appendInline(b, part.slice(2, -2));
                parent.appendChild(b);
            } else if (part.length > 2 && part.startsWith('*') && part.endsWith('*')) {
                const em = document.createElement('em');
                appendInline(em, part.slice(1, -1));
                parent.appendChild(em);
            } else if (part.length > 2 && part.startsWith('`') && part.endsWith('`')) {
                const code = document.createElement('code');
                code.textContent = part.slice(1, -1);
                parent.appendChild(code);
            } else if (part.charAt(0) === '[') {
                const m = part.match(/^\[([^\]]*)\]\(([^)]+)\)$/);
                if (m && SAFE_URL.test(m[2])) {
                    const a = document.createElement('a');
                    a.href = m[2];
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.textContent = m[1] || m[2];
                    parent.appendChild(a);
                } else {
                    parent.appendChild(document.createTextNode(part));
                }
            } else {
                const brParts = part.split(/<br\s*\/?>/i);
                brParts.forEach((seg, idx) => {
                    if (idx > 0) parent.appendChild(document.createElement('br'));
                    if (seg) parent.appendChild(document.createTextNode(seg));
                });
            }
        });
    }

    // ---- SSE over fetch (EventSource can't POST) --------------------------
    async function sendTurn(payload, onEvent, signal) {
        const resp = await fetch(cfg.baseUrl + '/admin/ai-studio/run', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload),
            signal: signal,
        });
        if (!resp.ok) {
            const text = await resp.text();
            throw new Error('Request failed (' + resp.status + '): ' + text.slice(0, 200));
        }

        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buf = '';
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buf += decoder.decode(value, { stream: true });
            let idx;
            while ((idx = buf.indexOf('\n\n')) !== -1) {
                parseFrame(buf.slice(0, idx), onEvent);
                buf = buf.slice(idx + 2);
            }
        }
        // Anything left over (host buffered the whole body): parse it too.
        if (buf.trim()) parseFrame(buf, onEvent);
    }

    function parseFrame(frame, onEvent) {
        let event = 'message';
        let data = '';
        frame.split('\n').forEach(line => {
            if (line.indexOf('event:') === 0) event = line.slice(6).trim();
            else if (line.indexOf('data:') === 0) data += line.slice(5).trim() + '\n';
        });
        if (!data.trim()) return;
        let parsed;
        try { parsed = JSON.parse(data); } catch (e) { return; }
        onEvent(event, parsed);
    }

    // ---- Turn orchestration ------------------------------------------------
    els.form.addEventListener('submit', (e) => {
        e.preventDefault();
        if (busy) return;
        const text = els.input.value.trim();
        if (!text) return;
        runTurn(text, []);
    });

    els.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            els.form.dispatchEvent(new Event('submit'));
        }
    });

    els.input.addEventListener('input', () => {
        els.input.style.height = 'auto';
        els.input.style.height = Math.min(els.input.scrollHeight, 200) + 'px';
    });

    els.suggestions.addEventListener('click', (e) => {
        const chip = e.target.closest('.ai-chip');
        if (!chip || busy) return;
        runTurn(chip.dataset.prompt, []);
    });

    els.stop.addEventListener('click', () => {
        if (abortCtrl) abortCtrl.abort();
    });

    async function runTurn(userText, approved) {
        setBusy(true);
        hideApproval();
        addUserBubble(userText);
        els.input.value = '';
        els.input.style.height = 'auto';
        history.push({ role: 'user', content: userText });
        setStatus('Working…', 'busy');
        setActivity('Starting…');
        showTyping();

        let assistantText = '';
        let receivedDone = false;
        let watchdogId = null;
        abortCtrl = new AbortController();

        const pending = pendingContext;
        pendingContext = null;

        // Watchdog: if server stalls and sends no SSE for N seconds, abort with a helpful message.
        // Prevents the "thinking forever" state when the loop dies or the host buffers/kills the stream.
        function resetWatchdog() {
            if (watchdogId) clearTimeout(watchdogId);
            watchdogId = setTimeout(() => {
                if (abortCtrl && !receivedDone) {
                    try { abortCtrl.abort(); } catch (_) {}
                    hideTyping();
                    hideActivity();
                    setStatus('Timeout', 'error');
                    addAgentBubble('⚠ No response from server for 90s — the run may have stalled (host buffer / PHP timeout / model error). Try again or pick a cheaper model.');
                }
            }, 90000);
        }
        resetWatchdog();

        try {
            await sendTurn({
                csrf_token: cfg.csrf,
                model: els.model.value,
                message: userText,
                history: JSON.stringify(history),
                approved: JSON.stringify(approved),
                pending: JSON.stringify(pending || []),
            }, (event, data) => {
                resetWatchdog();
                switch (event) {
                    case 'activity':
                        setActivity(data.text);
                        break;
                    case 'turn':
                        setStatus('Thinking… turn ' + data.number + '/' + data.max, 'busy');
                        setActivity('Thinking… turn ' + data.number + '/' + data.max);
                        showTyping();
                        break;
                    case 'usage':
                        updateUsage(data);
                        break;
                    case 'narrate':
                        assistantText = data.text;
                        addAgentBubble(data.text);
                        break;
                    case 'tool_result':
                        addToolEvent(data.tool, data.summary, data.ok);
                        setActivity(data.ok ? 'Done: ' + data.tool : 'Failed: ' + data.tool);
                        break;
                    case 'preview':
                        updatePreview(data.html);
                        setActivity('Preview rendered');
                        break;
                    case 'approval_required':
                        addApprovalEvent(data.plan);
                        showApproval({ call_id: data.call_id, plan: data.plan, reason: data.reason });
                        pendingContext = Array.isArray(data.pending) ? data.pending : null;
                        setActivity('Awaiting your approval…');
                        break;
                    case 'error':
                        hideTyping();
                        hideActivity();
                        addAgentBubble('⚠ ' + data.message);
                        setStatus('Error', 'error');
                        break;
                    case 'done':
                        receivedDone = true;
                        hideTyping();
                        hideActivity();
                        if (data.status === 'complete') {
                            if (!assistantText) {
                                assistantText = data.text || '';
                                if (assistantText) addAgentBubble(assistantText);
                            }
                            setStatus('Done', 'ok');
                        } else if (data.status === 'awaiting_approval') {
                            setStatus('Awaiting approval', 'wait');
                        } else {
                            setStatus('Stopped', 'error');
                        }
                        break;
                }
            }, abortCtrl.signal);
            // Stream ended but server never sent `done` (host killed the loop / connection_aborted early / PHP fatal).
            if (!receivedDone) {
                hideTyping();
                hideActivity();
                // Don't overwrite an explicit error/done status that was already set.
                const cur = els.status.textContent || '';
                const isStillBusy = cur.includes('Working') || cur.includes('Thinking') || cur === 'Starting…';
                if (isStillBusy) {
                    setStatus('Stopped', 'error');
                    addAgentBubble('⚠ Stream ended without a final status — the agent loop may have been killed (PHP timeout / host buffer). Check logs/ai-studio.log and try again.');
                } else if (!els.status.className.includes('ai-status--')) {
                    setStatus('Ready', '');
                }
            }
        } catch (err) {
            receivedDone = true; // prevent double message from the !receivedDone guard
            if (err && err.name === 'AbortError') {
                hideTyping();
                hideActivity();
                // Only show Stopped if not already showing approval wait.
                if (els.status.textContent !== 'Awaiting approval') setStatus('Stopped', 'error');
            } else {
                hideTyping();
                hideActivity();
                setStatus('Request failed', 'error');
                addAgentBubble('⚠ ' + err.message);
            }
        } finally {
            if (watchdogId) { clearTimeout(watchdogId); watchdogId = null; }
            // Absolute guarantee: never leave spinner/typing visible after a turn finishes.
            hideTyping();
            hideActivity();
        }

        if (assistantText) {
            history.push({ role: 'assistant', content: assistantText });
        }
        // Trim long sessions so the context stays manageable.
        if (history.length > 24) {
            history = history.slice(-24);
        }

        abortCtrl = null;
        setBusy(false);
        // If we ended in a terminal state (Done / Error / Awaiting approval) keep it; otherwise reset to Ready after a short grace.
        const statusText = els.status.textContent || '';
        const isTerminal = statusText === 'Done' || statusText === 'Awaiting approval' || statusText === 'Stopped' || statusText === 'Error' || statusText === 'Request failed' || statusText === 'Timeout';
        if (!isTerminal && els.status.className.indexOf('ai-status--') === -1) setStatus('Ready', '');
        if (isTerminal && (statusText === 'Done' || statusText === 'Stopped')) {
            // Auto-reset Done/Stopped to Ready after 4s so next turn doesn't look stuck.
            setTimeout(() => {
                if (!busy && (els.status.textContent === 'Done' || els.status.textContent === 'Stopped')) setStatus('Ready', '');
            }, 4000);
        }
        els.input.focus();
    }

    // ---- Approval actions ---------------------------------------------------
    els.approve.addEventListener('click', async () => {
        if (!pendingApproval || busy) return;
        els.approve.disabled = true;
        els.deny.disabled = true;
        const plan = pendingApproval.plan;
        await runTurn('[Approved] Proceed with the requested change: ' + plan, [pendingApproval.call_id]);
    });

    els.deny.addEventListener('click', async () => {
        if (!pendingApproval || busy) return;
        els.approve.disabled = true;
        els.deny.disabled = true;
        const plan = pendingApproval.plan;
        await runTurn('[Denied] Do not make this change: ' + plan + '. Propose an alternative if appropriate.', []);
    });

    // ---- Preview toggle: preview is optional, chat gets the full width when hidden ----
    const layoutEl = document.querySelector('.ai-studio__layout');
    const previewToggle = document.getElementById('ai-preview-toggle');
    const previewToggleLabel = document.getElementById('ai-preview-toggle-label');
    const PREVIEW_HIDDEN_KEY = 'aiStudioPreviewHidden';

    function applyPreviewState(hidden) {
        if (!layoutEl) return;
        layoutEl.classList.toggle('ai-studio__layout--single', hidden);
        if (previewToggleLabel) previewToggleLabel.textContent = hidden ? 'Show preview' : 'Hide preview';
        if (previewToggle) previewToggle.setAttribute('aria-pressed', hidden ? 'false' : 'true');
        fitStudioHeight();
    }

    if (previewToggle) {
        previewToggle.addEventListener('click', () => {
            const hidden = !layoutEl.classList.contains('ai-studio__layout--single');
            try { localStorage.setItem(PREVIEW_HIDDEN_KEY, hidden ? '1' : '0'); } catch (e) { /* storage unavailable */ }
            applyPreviewState(hidden);
        });
    }

    // ---- Fill remaining viewport height instead of guessing the chrome height ----
    const studioEl = document.querySelector('.ai-studio');
    let fitRaf = null;

    function fitStudioHeight() {
        if (!studioEl) return;
        if (fitRaf) cancelAnimationFrame(fitRaf);
        fitRaf = requestAnimationFrame(() => {
            const top = studioEl.getBoundingClientRect().top + window.scrollY;
            const bottomBreathingRoom = 28;
            const h = window.innerHeight - top - bottomBreathingRoom;
            studioEl.style.setProperty('--ai-studio-h', Math.max(560, h) + 'px');
        });
    }

    window.addEventListener('resize', fitStudioHeight);
    window.addEventListener('load', fitStudioHeight);

    (function initStudioLayout() {
        let hidden = false;
        try { hidden = localStorage.getItem(PREVIEW_HIDDEN_KEY) === '1'; } catch (e) { /* storage unavailable */ }
        applyPreviewState(hidden);
    })();
})();