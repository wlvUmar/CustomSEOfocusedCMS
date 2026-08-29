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
        gscStatus: document.getElementById('ai-gsc-status'),
        gscDisconnect: document.getElementById('ai-gsc-disconnect'),
        historyToggle: document.getElementById('ai-history-toggle'),
        historyPanel: document.getElementById('ai-history-panel'),
        historyList: document.getElementById('ai-history-list'),
        historyClose: document.getElementById('ai-history-close'),
        modeToggle: document.getElementById('ai-mode-toggle'),
        modeLabel: document.getElementById('ai-mode-label'),
    };

    let busy = false;
    let history = [];           // [{role:'user'|'assistant', content}] sent for model context
    let pendingApproval = null; // {call_id, plan, reason}
    let pendingContext = null;  // interrupted tool_call + result, resent on the follow-up run
    let abortCtrl = null;       // AbortController for the in-flight request
    let typingEl = null;
    let lastPreviewHtml = '';
    let previewSections = [];   // accumulated per-turn section previews (render_preview); full-page replaces
    let previewTurnSeq = 0;     // bumped per user turn so previews group per turn
    let activityTimerId = null; // interval driving the elapsed-time readout
    let activityStartedAt = 0;
    let currentMode = 'plan';   // 'plan' or 'build'

    // ---- Model selector persistence --------------------------------------
    const savedModel = localStorage.getItem('ai-studio-model');
    if (savedModel && Array.prototype.some.call(els.model.options, o => o.value === savedModel)) {
        els.model.value = savedModel;
    }
    els.model.addEventListener('change', () => {
        localStorage.setItem('ai-studio-model', els.model.value);
    });

    // ---- Mode toggle (Plan/Build) -----------------------------------------
    function setMode(mode) {
        currentMode = mode === 'build' ? 'build' : 'plan';
        if (els.modeToggle) els.modeToggle.setAttribute('aria-pressed', currentMode === 'build');
        if (els.modeLabel) els.modeLabel.textContent = currentMode.charAt(0).toUpperCase() + currentMode.slice(1);
        localStorage.setItem('ai-studio-mode', currentMode);
    }
    if (els.modeToggle) {
        els.modeToggle.addEventListener('click', () => {
            setMode(currentMode === 'plan' ? 'build' : 'plan');
        });
    }
    // Restore saved mode
    const savedMode = localStorage.getItem('ai-studio-mode');
    if (savedMode) setMode(savedMode);
    // Realtime model list from OpenRouter (falls back to PHP const)
    (async () => {
        try {
            const r = await fetch(cfg.baseUrl + '/admin/ai-studio/models', { headers: { 'Accept': 'application/json' } });
            const j = await r.json();
            const list = Array.isArray(j.models) ? j.models : [];
            if (!list.length) return;
            const cur = els.model.value;
            // Keep curated fallback order? Replace with live list sorted by name, curated first.
            const curated = new Set(['deepseek/deepseek-chat','openrouter/free','openai/gpt-oss-120b:free','openai/gpt-oss-20b:free','openai/gpt-4o-mini','anthropic/claude-3.5-haiku','google/gemini-2.5-flash','deepseek/deepseek-r1','meta-llama/llama-3.3-70b-instruct']);
            list.sort((a,b) => {
                const ca = curated.has(a.id), cb = curated.has(b.id);
                if (ca && !cb) return -1; if (!ca && cb) return 1;
                return (a.name || a.id).localeCompare(b.name || b.id);
            });
            const frag = document.createDocumentFragment();
            const seen = new Set();
            list.forEach(m => {
                if (!m.id || seen.has(m.id)) return; seen.add(m.id);
                const opt = document.createElement('option');
                opt.value = m.id;
                const pricing = m.pricing ? ` — $${Number(m.pricing.prompt||0).toFixed(4)}/$${Number(m.pricing.completion||0).toFixed(4)}` : '';
                const ctx = m.context_length ? ` · ${Math.round(m.context_length/1000)}k` : '';
                opt.textContent = (m.name || m.id) + (pricing||ctx ? ` (${pricing}${ctx})` : '');
                // keep curated labels short if they match const
                if (curated.has(m.id)) {
                    const short = { 'deepseek/deepseek-chat':'DeepSeek Chat (default, cheap)','openrouter/free':'Auto: best free model','openai/gpt-oss-120b:free':'GPT-OSS 120B (free)','openai/gpt-oss-20b:free':'GPT-OSS 20B (free, fast)','openai/gpt-4o-mini':'GPT-4o Mini','anthropic/claude-3.5-haiku':'Claude Haiku','google/gemini-2.5-flash':'Gemini 2.5 Flash','deepseek/deepseek-r1':'DeepSeek R1','meta-llama/llama-3.3-70b-instruct':'Llama 3.3 70B'}[m.id];
                    if (short) opt.textContent = short;
                }
                frag.appendChild(opt);
            });
            // Preserve current selection if still present, else keep savedModel if in new list
            const toSelect = (cur && seen.has(cur) ? cur : (savedModel && seen.has(savedModel) ? savedModel : null));
            els.model.innerHTML = ''; els.model.appendChild(frag);
            if (toSelect) els.model.value = toSelect;
            else els.model.selectedIndex = 0;
        } catch(e){ /* keep static list */ }
    })();

    // ---- Session history (localStorage persistence) ---------------------------
    const SESSIONS_KEY = 'ai-studio-sessions';
    const CURRENT_KEY = 'ai-studio-current-session-id';
    let currentSessionId = null;

    function loadSessions() {
        try { const raw = localStorage.getItem(SESSIONS_KEY); const arr = raw ? JSON.parse(raw) : []; return Array.isArray(arr) ? arr : []; } catch (e) { return []; }
    }
    function saveSessions(arr) {
        try { localStorage.setItem(SESSIONS_KEY, JSON.stringify(arr.slice(0, 50))); } catch (e) { /* quota */ }
    }
    function genSessionId() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
    function sessionTitleFromHistory(hist) {
        const firstUser = hist.find(m => m.role === 'user');
        if (!firstUser) return 'New conversation';
        const t = firstUser.content.trim().slice(0, 48);
        return t.length < firstUser.content.trim().length ? t + '…' : t;
    }
    function saveCurrentSession() {
        if (!currentSessionId) return;
        const sessions = loadSessions();
        const idx = sessions.findIndex(s => s.id === currentSessionId);
        const payload = {
            id: currentSessionId,
            title: sessionTitleFromHistory(history),
            updatedAt: Date.now(),
            createdAt: (idx >= 0 ? sessions[idx].createdAt : Date.now()),
            history: history.slice(),
            transcriptHTML: els.transcript ? els.transcript.innerHTML : '',
            lastPreviewHtml: lastPreviewHtml || '',
            usageText: els.usage ? els.usage.textContent : ''
        };
        if (idx >= 0) sessions[idx] = payload; else sessions.unshift(payload);
        // keep sorted by updatedAt desc
        sessions.sort((a, b) => b.updatedAt - a.updatedAt);
        saveSessions(sessions);
        renderHistoryList();
    }
    function renderHistoryList() {
        if (!els.historyList) return;
        const sessions = loadSessions();
        els.historyList.innerHTML = '';
        if (!sessions.length) {
            els.historyList.innerHTML = '<div style="padding:12px;color:var(--ai-muted);font-size:.82rem;text-align:center">No sessions yet</div>';
            return;
        }
        sessions.forEach(s => {
            const item = document.createElement('div');
            item.className = 'ai-history-item' + (s.id === currentSessionId ? ' ai-history-item--active' : '');
            const title = document.createElement('div'); title.className = 'ai-history-item__title'; title.textContent = s.title || 'Untitled';
            const meta = document.createElement('div'); meta.className = 'ai-history-item__meta';
            const d = new Date(s.updatedAt); meta.textContent = d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' · ' + (s.history ? s.history.length : 0) + ' msgs';
            const actions = document.createElement('div'); actions.className = 'ai-history-item__actions';
            const loadBtn = document.createElement('button'); loadBtn.type = 'button'; loadBtn.className = 'ai-btn ai-btn--ghost ai-btn--sm'; loadBtn.textContent = 'Open';
            loadBtn.addEventListener('click', (e) => { e.stopPropagation(); restoreSession(s.id); });
            const delBtn = document.createElement('button'); delBtn.type = 'button'; delBtn.className = 'ai-history-item__del'; delBtn.textContent = 'Delete';
            delBtn.addEventListener('click', (e) => { e.stopPropagation(); if (!confirm('Delete this session?')) return; deleteSession(s.id); });
            actions.appendChild(loadBtn); actions.appendChild(delBtn);
            item.appendChild(title); item.appendChild(meta); item.appendChild(actions);
            item.addEventListener('click', () => restoreSession(s.id));
            els.historyList.appendChild(item);
        });
        if (window.feather) try { feather.replace({class:'feather-icon'}); } catch(e){}
    }
    function restoreSession(id) {
        const sessions = loadSessions();
        const s = sessions.find(x => x.id === id);
        if (!s) return;
        currentSessionId = s.id;
        try { localStorage.setItem(CURRENT_KEY, currentSessionId); } catch(e){}
        history = Array.isArray(s.history) ? s.history.slice() : [];
        pendingApproval = null; pendingContext = null;
        if (els.transcript) {
            if (s.transcriptHTML) els.transcript.innerHTML = s.transcriptHTML;
            else els.transcript.innerHTML = '';
            // re-enhance missing? MutationObserver will handle next adds
        }
        lastPreviewHtml = s.lastPreviewHtml || '';
        if (lastPreviewHtml && els.previewFrame) { els.previewFrame.setAttribute('srcdoc', lastPreviewHtml); els.previewHint.textContent = 'Restored ' + new Date(s.updatedAt).toLocaleTimeString(); }
        if (els.approval) els.approval.hidden = true;
        setStatus('Ready'); updateUsage(null);
        if (s.usageText && els.usage) els.usage.textContent = s.usageText;
        hideActivity(); hideTyping();
        renderHistoryList();
        if (els.historyPanel) els.historyPanel.hidden = true;
        scrollTranscript();
        els.input.focus();
    }
    function deleteSession(id) {
        let sessions = loadSessions();
        sessions = sessions.filter(s => s.id !== id);
        saveSessions(sessions);
        // Also delete from DB
        (async()=>{ try{
            const fd=new FormData(); fd.append('csrf_token',cfg.csrf);
            await fetch(cfg.baseUrl+'/admin/ai-studio/session/'+encodeURIComponent(id)+'/delete',{method:'POST',body:fd});
        }catch(e){}})();
        if (currentSessionId === id) {
            if (sessions.length) restoreSession(sessions[0].id);
            else createNewSession(true);
        } else renderHistoryList();
    }
    function createNewSession(skipSaveCurrent) {
        if (!skipSaveCurrent && currentSessionId && history.length) saveCurrentSession();
        currentSessionId = genSessionId();
        try { localStorage.setItem(CURRENT_KEY, currentSessionId); } catch(e){}
        history = []; pendingApproval = null; pendingContext = null;
        if (els.transcript) els.transcript.innerHTML = '<div class="ai-welcome"><div class="ai-welcome__icon"><i data-feather="zap"></i></div><h2 class="ai-welcome__title">Hi, I\'m your AI Studio agent</h2><p class="ai-welcome__text">I can inspect pages, rotation variants, FAQs and analytics, then edit content and show you a live preview. Ask me to improve a page, find underperforming content, or create a new section.</p></div>';
        lastPreviewHtml = ''; if (els.previewFrame) els.previewFrame.removeAttribute('srcdoc');
        if (els.previewHint) els.previewHint.textContent = 'Renders here after each turn';
        setStatus('Ready'); updateUsage(null); hideActivity(); hideTyping(); if (els.approval) els.approval.hidden = true;
        const sessions = loadSessions();
        sessions.unshift({ id: currentSessionId, title: 'New conversation', createdAt: Date.now(), updatedAt: Date.now(), history: [], transcriptHTML: els.transcript.innerHTML, lastPreviewHtml: '', usageText: '' });
        saveSessions(sessions);
        renderHistoryList();
        if (window.feather) try { feather.replace({class:'feather-icon'}); } catch(e){}
        scrollTranscript();
    }
    // ---- DB-backed sessions sync (cross-session persistence) ---------------
    async function fetchDbSessions() {
        try {
            const r = await fetch(cfg.baseUrl + '/admin/ai-studio/sessions', { headers:{'Accept':'application/json'}});
            const j = await r.json();
            if (j.success && Array.isArray(j.sessions)) return j.sessions;
        } catch(e){}
        return null;
    }
    async function syncSessionsFromDb() {
        const db = await fetchDbSessions();
        if (!db) return;
        // Merge DB list into localStorage for display; prefer DB titles
        const local = loadSessions();
        const map = new Map(local.map(s=>[s.id,s]));
        db.forEach(row => {
            const id=row.id;
            if (!map.has(id)) {
                map.set(id,{id:id,title:row.title||'Session',updatedAt: new Date(row.updated_at).getTime(),createdAt:new Date(row.created_at).getTime(),history:[],transcriptHTML:'',lastPreviewHtml:'',usageText:''});
            } else {
                const ex=map.get(id);
                ex.title = row.title||ex.title;
                ex.updatedAt = new Date(row.updated_at).getTime();
            }
        });
        const merged=[...map.values()].sort((a,b)=>b.updatedAt-a.updatedAt).slice(0,50);
        saveSessions(merged);
        renderHistoryList();
    }
    async function loadSessionFromDb(id) {
        try {
            const r = await fetch(cfg.baseUrl + '/admin/ai-studio/session/'+encodeURIComponent(id), { headers:{'Accept':'application/json'}});
            const j = await r.json();
            if (j.success && j.session) {
                const s=j.session;
                currentSessionId=s.id;
                try{localStorage.setItem(CURRENT_KEY,currentSessionId);}catch(e){}
                history=Array.isArray(s.history)?s.history.slice():[];
                pendingApproval=null; pendingContext=null;
                if (els.transcript) {
                    if (s.history && s.history.length) {
                        els.transcript.innerHTML='';
                        s.history.forEach(m=>{
                            if(m.role==='user') addUserBubble(m.content);
                            else if(m.role==='assistant' && m.content) addAgentBubble(m.content);
                        });
                    } else els.transcript.innerHTML='';
                }
                if (els.approval) els.approval.hidden=true;
                setStatus('Ready'); hideActivity(); hideTyping(); renderHistoryList();
                if (els.historyPanel) els.historyPanel.hidden=true;
                scrollTranscript(); els.input.focus();
                return true;
            }
        } catch(e){}
        return false;
    }
    // init current session
    (function initSession() {
        const existing = loadSessions();
        const savedId = (() => { try { return localStorage.getItem(CURRENT_KEY); } catch(e){ return null; } })();
        if (savedId && existing.some(s => s.id === savedId)) {
            restoreSession(savedId);
        } else if (existing.length) {
            restoreSession(existing[0].id);
        } else {
            createNewSession(true);
        }
        renderHistoryList();
        syncSessionsFromDb();
        if (els.historyToggle && els.historyPanel) {
            els.historyToggle.addEventListener('click', () => { els.historyPanel.hidden = !els.historyPanel.hidden; if (!els.historyPanel.hidden) { renderHistoryList(); syncSessionsFromDb(); } });
        }
        if (els.historyClose && els.historyPanel) els.historyClose.addEventListener('click', () => els.historyPanel.hidden = true);
        // Patch restoreSession to try DB if not in local
        const _origRestore = restoreSession;
        window._aiRestoreSession = async function(id){
            const local = loadSessions().find(x=>x.id===id);
            if (local) return restoreSession(id);
            const ok = await loadSessionFromDb(id);
            if (!ok) restoreSession(id);
        };
    })();

    // ---- GSC: live Connect/Disconnect --------------------------
    (function initGsc() {
        if (!els.gscDisconnect) return;
        function setGscStatus(text, kind) {
            if (!els.gscStatus) return;
            els.gscStatus.textContent = text;
            els.gscStatus.className = 'ai-gsc-bar__hint' + (kind ? ' ai-gsc-bar__hint--' + kind : '');
        }
        els.gscDisconnect.addEventListener('click', async () => {
            if (!confirm('Disconnect Search Console? Live GSC tools will require reconnection.')) return;
            els.gscDisconnect.disabled = true;
            setGscStatus('Disconnecting…', 'busy');
            try {
                const fd = new FormData();
                fd.append('csrf_token', cfg.csrf);
                const resp = await fetch(cfg.baseUrl + '/admin/ai-studio/gsc-disconnect', { method: 'POST', body: fd });
                const data = await resp.json().catch(() => ({}));
                if (!resp.ok || !data.success) throw new Error((data && data.message) || ('Failed (' + resp.status + ')'));
                setGscStatus('Disconnected — reconnect to restore live GSC', 'warn');
                addAgentBubble('🔌 GSC disconnected. Tools will return empty until you reconnect Search Console.');
                setTimeout(() => location.reload(), 800);
            } catch (err) {
                setGscStatus('Disconnect failed: ' + err.message, 'error');
                els.gscDisconnect.disabled = false;
            }
        });
    })();

    // ---- New session ------------------------------------------------------
    els.newSession.addEventListener('click', () => {
        if (busy) return;
        createNewSession(false);
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
        if (els.suggestions) {
            els.suggestions.classList.toggle('ai-suggestions--disabled', value);
            Array.prototype.forEach.call(els.suggestions.querySelectorAll('.ai-chip'), chip => {
                chip.disabled = value;
            });
        }
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

    function extractPreviewFragment(html) {
        // render_preview wraps a single section in <div class="content-body">…</div>
        // We pull that inner HTML so multiple sections can be stacked into one doc.
        try {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const body = doc.querySelector('.content-body');
            if (body && body.innerHTML.trim().length > 0) return body.innerHTML;
            // fallback: body innerHTML
            if (doc.body && doc.body.innerHTML.trim().length > 0) return doc.body.innerHTML;
        } catch (e) {}
        return html;
    }
    function extractSectionLabel(fragment) {
        // Prefer first <h2>/<h3> text inside the fragment, else generic
        const m = fragment.match(/<h[23][^>]*>(.*?)<\/h[23]>/i);
        if (m) {
            const t = m[1].replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().slice(0, 60);
            if (t) return t;
        }
        return '';
    }
    function buildCombinedPreview(sections, sampleHtml) {
        let cssHref = '';
        let langAttr = 'ru';
        try {
            const doc = new DOMParser().parseFromString(sampleHtml, 'text/html');
            const link = doc.querySelector('link[rel="stylesheet"]');
            if (link && link.getAttribute('href')) cssHref = link.getAttribute('href');
            const htmlEl = doc.querySelector('html');
            if (htmlEl && htmlEl.getAttribute('lang')) langAttr = htmlEl.getAttribute('lang');
        } catch (e) {}
        if (!cssHref) cssHref = (cfg.baseUrl || '') + '/css/pages.css';
        const parts = sections.map((frag, i) => {
            const label = extractSectionLabel(frag);
            const title = label ? label + ' (section ' + (i + 1) + ')' : 'Section ' + (i + 1);
            return '<section class="ai-preview-stack__section"><div class="ai-preview-stack__label">' + title.replace(/</g, '&lt;') + '</div>' + frag + '</section>';
        }).join('<hr class="ai-preview-stack__sep">');
        return '<!DOCTYPE html>\n'
            + '<html lang="' + langAttr + '">\n'
            + '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            + '<link rel="stylesheet" href="' + cssHref + '">'
            + '<style>.ai-preview-stack__label{font:11px/1.4 system-ui, -apple-system, Segoe UI;color:#6a7282;background:#f3f4f6;border:1px solid #e5e7eb;padding:3px 8px;border-radius:999px;display:inline-block;margin:14px 0 10px} .ai-preview-stack__section:first-child .ai-preview-stack__label{margin-top:0} .ai-preview-stack__sep{border:none;border-top:1px dashed #d1d5db;margin:18px 0}</style>'
            + '</head>\n'
            + '<body><div class="content-body">' + parts + '</div></body>\n'
            + '</html>';
    }
    function updatePreview(html, kind) {
        const isFull = (kind === 'render_full_page') || (typeof html === 'string' && html.indexOf('<header>') !== -1 && html.indexOf('<footer>') !== -1) || (typeof html === 'string' && html.indexOf('preview-banner') !== -1);
        if (isFull) {
            // Full page replaces any stacked sections — it's the ground truth.
            previewSections = [];
            lastPreviewHtml = html;
            els.previewHint.textContent = 'Full page · ' + new Date().toLocaleTimeString();
            els.previewHint.title = 'render_full_page — header+content+footer';
            els.previewFrame.style.opacity = '0';
            els.previewFrame.setAttribute('srcdoc', html);
            return;
        }
        // Section preview — accumulate within this turn.
        const frag = extractPreviewFragment(html);
        previewSections.push(frag);
        let combined;
        let hint;
        if (previewSections.length === 1) {
            combined = html; // first section can use original doc as-is (identical to combined)
            hint = 'Section 1/1 · ' + new Date().toLocaleTimeString();
        } else {
            combined = buildCombinedPreview(previewSections, html);
            hint = previewSections.length + ' sections stacked · ' + new Date().toLocaleTimeString();
        }
        lastPreviewHtml = combined;
        els.previewHint.textContent = hint;
        els.previewHint.title = previewSections.length + ' render_preview(s) combined in this turn — click Open for full view. A final render_full_page will replace this with the real page.';
        els.previewFrame.style.opacity = '0';
        els.previewFrame.setAttribute('srcdoc', combined);
    }
    function resetPreviewAccumulation() {
        previewSections = [];
        previewTurnSeq++;
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
            throw new Error('Request failed (' + resp.status + '): ' + text.slice(0, 500));
        }
        if (!resp.body || typeof resp.body.getReader !== 'function') {
            throw new Error('Streaming not supported by this browser (ReadableStream missing)');
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
        try { parsed = JSON.parse(data); } catch (e) {
            // Surface malformed SSE instead of silently dropping (e.g., failed json_encode on PHP side).
            console.warn('AI Studio: dropped malformed SSE frame', frame.slice(0, 300), e);
            return;
        }
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

    if (els.suggestions) {
        els.suggestions.addEventListener('click', (e) => {
            const chip = e.target.closest('.ai-chip');
            if (!chip || busy) return;
            runTurn(chip.dataset.prompt, []);
        });
    }

    els.stop.addEventListener('click', () => {
        if (abortCtrl) abortCtrl.abort();
    });

    async function runTurn(userText, approved, mode = 'plan') {
        setBusy(true);
        hideApproval();
        resetPreviewAccumulation();
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

        // Watchdog: if server stalls and sends no SSE for N seconds, abort.
        // 180s accommodates 2×120s OpenRouter curls within one run (audit H6). Reset on any SSE keeps healthy long runs alive.
        function resetWatchdog() {
            if (watchdogId) clearTimeout(watchdogId);
            watchdogId = setTimeout(() => {
                if (abortCtrl && !receivedDone) {
                    try { abortCtrl.abort(); } catch (_) {}
                    hideTyping();
                    hideActivity();
                    setStatus('Timeout', 'error');
                    addAgentBubble('⚠ No response from server for 180s — the run may have stalled (host buffer / PHP timeout / model error). Check logs/ai-studio.log or try a cheaper model.');
                }
            }, 180000);
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
                mode: currentMode,
                session_id: currentSessionId || '',
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
                        updatePreview(data.html, data.kind);
                        if (data.kind === 'render_full_page') setActivity('Full page preview rendered');
                        else if (previewSections.length > 1) setActivity('Preview rendered (' + previewSections.length + ' sections stacked)');
                        else setActivity('Preview rendered');
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
        // Persist session for continue/refresh (history + transcript snapshot)
        try { saveCurrentSession(); } catch(e){}

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
        await runTurn('[Approved] Proceed with the requested change: ' + plan, [pendingApproval.call_id], currentMode);
    });

    els.deny.addEventListener('click', async () => {
        if (!pendingApproval || busy) return;
        els.approve.disabled = true;
        els.deny.disabled = true;
        const plan = pendingApproval.plan;
        await runTurn('[Denied] Do not make this change: ' + plan + '. Propose an alternative if appropriate.', [], currentMode);
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

    // App mode: prevent page scroll on desktop — only chat/history scroll (content-level, no global lock)
    try {
        if (document.querySelector('.ai-studio--app')) {
            document.querySelector('.admin-main')?.classList.add('ai-app');
            document.querySelector('.admin-content')?.classList.add('ai-app');
        }
    } catch(e){}

    // Ensure clean idle state on fresh open — prevents stale spinner/typing from cached DOM or back-forward cache.
    hideActivity();
    hideTyping();
    hideApproval();
    setBusy(false);
    setStatus('Ready', '');
    if (els.activity) els.activity.hidden = true;
    if (els.stop) els.stop.hidden = true;

    // bfcache (back/forward) can restore a stuck busy state — reset on pageshow.
    window.addEventListener('pageshow', () => {
        hideActivity();
        hideTyping();
        setBusy(false);
        if (!els.approval.hidden) hideApproval();
        const cur = els.status.textContent || '';
        if (cur === 'Working…' || cur.startsWith('Thinking')) setStatus('Ready', '');
    });

    // ========================================================================
    // UI polish (additive, presentation-only).
    // Nothing below drives the agent loop, SSE parsing, approvals, or session
    // state — it only observes the transcript DOM that the code above already
    // produces and layers cosmetic affordances on top: restoring the welcome
    // card after "New session", collapsing the starter prompts once the chat
    // has messages, adding a timestamp + copy button to each bubble, and a
    // scroll-to-bottom button while reading back through history.
    // ========================================================================
    (function aiStudioUiPolish() {
        const transcript = els.transcript;
        const scrollFab = document.getElementById('ai-scroll-bottom');
        const suggestionsEl = els.suggestions;
        if (!transcript) return;

        function refreshIcons() {
            if (window.feather) {
                try { feather.replace({ class: 'feather-icon' }); } catch (e) { /* feather not ready */ }
            }
        }

        function welcomeMarkup() {
            return '<div class="ai-welcome">'
                + '<div class="ai-welcome__icon"><i data-feather="zap"></i></div>'
                + '<h2 class="ai-welcome__title">Hi, I\u2019m your AI Studio agent</h2>'
                + '<p class="ai-welcome__text">I can inspect pages, rotation variants, FAQs and analytics, then edit content and show you a live preview. Ask me to improve a page, find underperforming content, or create a new section.</p>'
                + '</div>';
        }

        function refreshSuggestionsLayout() {
            if (!suggestionsEl) return;
            const hasMessages = transcript.querySelector('.ai-msg') !== null;
            suggestionsEl.classList.toggle('ai-suggestions--intro', !hasMessages);
        }

        function enhanceMessage(msg) {
            if (msg.dataset.enhanced) return;
            const body = msg.querySelector(':scope > .ai-msg__body');
            if (!body) return;
            msg.dataset.enhanced = '1';

            const col = document.createElement('div');
            col.className = 'ai-msg__col';
            msg.insertBefore(col, body);
            col.appendChild(body);

            const meta = document.createElement('div');
            meta.className = 'ai-msg__meta';

            const time = document.createElement('span');
            time.className = 'ai-msg__time';
            time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            meta.appendChild(time);

            if (msg.classList.contains('ai-msg--agent')) {
                const copyBtn = document.createElement('button');
                copyBtn.type = 'button';
                copyBtn.className = 'ai-msg__copy';
                copyBtn.setAttribute('aria-label', 'Copy message');
                copyBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg>';
                copyBtn.addEventListener('click', () => {
                    if (!navigator.clipboard) return;
                    const text = body.innerText || body.textContent || '';
                    navigator.clipboard.writeText(text).then(() => {
                        meta.classList.add('ai-msg__meta--pinned');
                        copyBtn.classList.add('ai-msg__copy--done');
                        setTimeout(() => {
                            meta.classList.remove('ai-msg__meta--pinned');
                            copyBtn.classList.remove('ai-msg__copy--done');
                        }, 1200);
                    }).catch(() => { /* clipboard denied — silently skip */ });
                });
                meta.appendChild(copyBtn);
            }

            col.appendChild(meta);
        }

        const transcriptObserver = new MutationObserver((mutations) => {
            let touched = false;
            mutations.forEach((m) => {
                m.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;
                    if (node.classList && node.classList.contains('ai-msg')) {
                        enhanceMessage(node);
                        touched = true;
                    }
                });
            });
            if (transcript.childElementCount === 0) {
                transcript.innerHTML = welcomeMarkup();
                refreshIcons();
            }
            if (touched) refreshSuggestionsLayout();
        });
        transcriptObserver.observe(transcript, { childList: true });
        refreshSuggestionsLayout();

        if (scrollFab) {
            function nearBottom() {
                return transcript.scrollHeight - transcript.scrollTop - transcript.clientHeight < 80;
            }
            function updateFab() {
                scrollFab.hidden = nearBottom();
            }
            transcript.addEventListener('scroll', updateFab, { passive: true });
            if (window.ResizeObserver) new ResizeObserver(updateFab).observe(transcript);
            new MutationObserver(updateFab).observe(transcript, { childList: true, subtree: true });
            scrollFab.addEventListener('click', () => {
                transcript.scrollTo({ top: transcript.scrollHeight, behavior: 'smooth' });
            });
            updateFab();
        }
    })();
})();