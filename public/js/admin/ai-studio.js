// FILE: public/js/admin/ai-studio.js
// AI Studio client: streams agent turns over SSE (fetch reader — EventSource
// cannot POST), renders the transcript, drives the live preview pane, and
// handles the approval flow for guarded (destructive) tool calls.

(function() {
    'use strict';

    const cfg = window.AI_STUDIO;
    if (!cfg) return;

    const els = {
        form: document.getElementById('ai-form'),
        input: document.getElementById('ai-input'),
        send: document.getElementById('ai-send'),
        transcript: document.getElementById('ai-transcript'),
        status: document.getElementById('ai-status'),
        approval: document.getElementById('ai-approval'),
        approvalPlan: document.getElementById('ai-approval-plan'),
        approvalReason: document.getElementById('ai-approval-reason'),
        approve: document.getElementById('ai-approve'),
        deny: document.getElementById('ai-deny'),
        model: document.getElementById('ai-model'),
        newSession: document.getElementById('ai-new-session'),
        previewFrame: document.getElementById('ai-preview-frame'),
        previewHint: document.getElementById('ai-preview-hint'),
    };

    let busy = false;
    let history = [];           // [{role:'user'|'assistant', content}] sent for model context
    let pendingApproval = null; // {call_id, plan, reason}

    // ---- Model selector persistence --------------------------------------
    const savedModel = localStorage.getItem('ai-studio-model');
    if (savedModel && Array.prototype.some.call(els.model.options, o => o.value === savedModel)) {
        els.model.value = savedModel;
    }
    els.model.addEventListener('change', () => {
        localStorage.setItem('ai-studio-model', els.model.value);
    });

    // ---- New session ------------------------------------------------------
    els.newSession.addEventListener('click', () => {
        if (busy) return;
        history = [];
        pendingApproval = null;
        els.approval.hidden = true;
        els.transcript.innerHTML = '';
        els.previewFrame.removeAttribute('srcdoc');
        els.previewHint.textContent = 'Renders here after each turn';
        setStatus('Ready');
        els.input.value = '';
        els.input.focus();
    });

    // ---- DOM helpers ------------------------------------------------------
    function setStatus(text, kind) {
        els.status.textContent = text;
        els.status.className = 'ai-status' + (kind ? ' ai-status--' + kind : '');
    }

    function scrollTranscript() {
        els.transcript.scrollTop = els.transcript.scrollHeight;
    }

    function addBubble(role, text) {
        const wrap = document.createElement('div');
        wrap.className = 'ai-msg ai-msg--' + role;
        const body = document.createElement('div');
        body.className = 'ai-msg__body';
        body.textContent = text;
        wrap.appendChild(body);
        els.transcript.appendChild(wrap);
        scrollTranscript();
        return body;
    }

    function addToolEvent(tool, summary, ok) {
        const wrap = document.createElement('div');
        wrap.className = 'ai-tool-event' + (ok ? '' : ' ai-tool-event--error');
        const head = document.createElement('div');
        head.className = 'ai-tool-event__head';
        head.textContent = (ok ? '⚙ ' : '✗ ') + tool;
        wrap.appendChild(head);
        const body = document.createElement('pre');
        body.className = 'ai-tool-event__body';
        body.textContent = summary;
        wrap.appendChild(body);
        els.transcript.appendChild(wrap);
        scrollTranscript();
    }

    function addApprovalEvent(plan) {
        const wrap = document.createElement('div');
        wrap.className = 'ai-tool-event ai-tool-event--approval';
        const head = document.createElement('div');
        head.className = 'ai-tool-event__head';
        head.textContent = '🔒 Approval requested';
        wrap.appendChild(head);
        const body = document.createElement('div');
        body.className = 'ai-tool-event__body';
        body.textContent = plan;
        wrap.appendChild(body);
        els.transcript.appendChild(wrap);
        scrollTranscript();
    }

    function updatePreview(html) {
        els.previewFrame.setAttribute('srcdoc', html);
        els.previewHint.textContent = 'Updated ' + new Date().toLocaleTimeString();
    }

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

    // ---- SSE over fetch (EventSource can't POST) --------------------------
    async function sendTurn(payload, onEvent) {
        const resp = await fetch(cfg.baseUrl + '/admin/ai-studio/run', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload),
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

    async function runTurn(userText, approved) {
        busy = true;
        els.send.disabled = true;
        els.input.disabled = true;
        hideApproval();
        addBubble('user', userText);
        els.input.value = '';
        history.push({ role: 'user', content: userText });
        setStatus('Working…', 'busy');

        let assistantText = '';

        try {
            await sendTurn({
                csrf_token: cfg.csrf,
                model: els.model.value,
                message: userText,
                history: JSON.stringify(history),
                approved: JSON.stringify(approved),
            }, (event, data) => {
                switch (event) {
                    case 'turn':
                        setStatus('Thinking… turn ' + data.number + '/' + data.max, 'busy');
                        break;
                    case 'narrate':
                        assistantText = data.text;
                        addBubble('agent', data.text);
                        break;
                    case 'tool_result':
                        addToolEvent(data.tool, data.summary, data.ok);
                        break;
                    case 'preview':
                        updatePreview(data.html);
                        break;
                    case 'approval_required':
                        addApprovalEvent(data.plan);
                        showApproval({ call_id: data.call_id, plan: data.plan, reason: data.reason });
                        break;
                    case 'error':
                        addBubble('agent', '⚠ ' + data.message);
                        setStatus('Error', 'error');
                        break;
                    case 'done':
                        if (data.status === 'complete') {
                            if (!assistantText) assistantText = data.text || '';
                            setStatus('Done', 'ok');
                        } else if (data.status === 'awaiting_approval') {
                            setStatus('Awaiting approval', 'wait');
                        } else {
                            setStatus('Stopped', 'error');
                        }
                        break;
                }
            });
        } catch (err) {
            setStatus('Request failed', 'error');
            addBubble('agent', '⚠ ' + err.message);
        }

        if (assistantText) {
            history.push({ role: 'assistant', content: assistantText });
        }
        // Trim long sessions so the context stays manageable.
        if (history.length > 24) {
            history = history.slice(-24);
        }

        busy = false;
        els.send.disabled = false;
        els.input.disabled = false;
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
})();
