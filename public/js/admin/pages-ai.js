// FILE: public/js/admin/pages-ai.js
// AI Assistant for the page edit form (OpenRouter backend).
// Two modes:
//  - 'edits' (default): chat thread that makes targeted line edits (find/replace)
//  - 'full': single-shot rewrite of the entire field

(function() {
    'use strict';

    const cfg = window.AI_CONFIG;
    if (!cfg) return;

    // ---- Chat state -------------------------------------------------------
    let workingContent = null;      // accumulated copy of the field being edited
    let originalContent = null;     // snapshot taken when chat mode started
    let history = [];               // prior {role, content} turns
    let chatBusy = false;

    // ---- Shared field helpers ---------------------------------------------
    function getFieldConfig(field) {
        return cfg.fields[field] || null;
    }

    function readField(field) {
        const f = getFieldConfig(field);
        if (!f) return '';
        if (f.type === 'tinymce') {
            const editor = tinymce.get(field);
            if (editor) return editor.getContent();
            const ta = document.querySelector('textarea[name="' + field + '"]');
            return ta ? ta.value : '';
        }
        const el = document.querySelector(f.selector);
        return el ? el.value : '';
    }

    function writeField(field, html) {
        const f = getFieldConfig(field);
        if (!f) return;
        if (f.type === 'tinymce') {
            const editor = tinymce.get(field);
            if (editor) {
                editor.setContent(html);
            } else {
                const ta = document.querySelector('textarea[name="' + field + '"]');
                if (ta) ta.value = html;
            }
        } else {
            const el = document.querySelector(f.selector);
            if (el) el.value = html;
        }
    }

    function getField() {
        const sel = document.getElementById('ai-field');
        return sel ? sel.value : 'content_ru';
    }

    function getModel() {
        const sel = document.getElementById('ai-model');
        return sel ? sel.value : 'deepseek/deepseek-chat';
    }

    // ---- Status ------------------------------------------------------------
    function setChatStatus(text, type) {
        const el = document.getElementById('ai-chat-status');
        if (!el) return;
        el.textContent = text || '';
        el.className = 'ai-panel__status' + (type ? ' ' + type : '');
    }

    function setFullStatus(text, type) {
        const el = document.getElementById('ai-status');
        if (!el) return;
        el.textContent = text || '';
        el.className = 'ai-panel__status' + (type ? ' ' + type : '');
    }

    // ---- Chat rendering ----------------------------------------------------
    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function addUserMessage(text) {
        const chat = document.getElementById('ai-chat');
        if (!chat) return;
        const empty = chat.querySelector('.ai-chat__empty');
        if (empty) empty.remove();
        const msg = document.createElement('div');
        msg.className = 'ai-chat__msg ai-chat__msg--user';
        msg.innerHTML = '<div class="ai-chat__bubble">' + esc(text) + '</div>';
        chat.appendChild(msg);
        chat.scrollTop = chat.scrollHeight;
    }

    function addAssistantMessage(changes) {
        const chat = document.getElementById('ai-chat');
        if (!chat) return;
        const empty = chat.querySelector('.ai-chat__empty');
        if (empty) empty.remove();
        const msg = document.createElement('div');
        msg.className = 'ai-chat__msg ai-chat__msg--ai';

        let html = '<div class="ai-chat__bubble">';
        if (!changes || changes.length === 0) {
            html += '<div class="ai-chat__note">No changes were needed.</div>';
        }
        (changes || []).forEach(function(c) {
            html += '<div class="ai-diff">';
            html += '<div class="ai-diff__row ai-diff__row--old"><span class="ai-diff__label">was</span><span class="ai-diff__old">' + esc(c.find) + '</span></div>';
            html += '<div class="ai-diff__row ai-diff__row--new"><span class="ai-diff__label">now</span><span class="ai-diff__new">' + esc(c.replace) + '</span></div>';
            if (c.explanation) {
                html += '<div class="ai-diff__expl">' + esc(c.explanation) + '</div>';
            }
            html += '</div>';
        });
        html += '</div>';

        msg.innerHTML = html;
        chat.appendChild(msg);
        chat.scrollTop = chat.scrollHeight;
    }

    function addChatError(text) {
        const chat = document.getElementById('ai-chat');
        if (!chat) return;
        const msg = document.createElement('div');
        msg.className = 'ai-chat__msg ai-chat__msg--ai';
        msg.innerHTML = '<div class="ai-chat__bubble ai-chat__bubble--error">' + esc(text) + '</div>';
        chat.appendChild(msg);
        chat.scrollTop = chat.scrollHeight;
    }

    function resetChatUi() {
        const chat = document.getElementById('ai-chat');
        if (!chat) return;
        chat.innerHTML = '<div class="ai-chat__empty">Tell the AI what to change, e.g. "replace the phone number in the hero with +998 90 123 45 67". It will locate the exact text and change only that.</div>';
    }

    // ---- Chat API ----------------------------------------------------------
    function chatSend() {
        if (chatBusy) return;
        const ta = document.getElementById('ai-chat-prompt');
        const prompt = ta ? ta.value.trim() : '';
        if (!prompt) {
            setChatStatus('Please enter what you want to change', 'error');
            return;
        }

        const field = getField();
        if (workingContent === null) {
            workingContent = readField(field);
            originalContent = workingContent;
            history = [];
            resetChatUi();
        }

        chatBusy = true;
        ta.value = '';
        addUserMessage(prompt);
        setChatStatus('Thinking…', 'loading');
        const sendBtn = document.getElementById('ai-chat-send');
        if (sendBtn) sendBtn.disabled = true;

        const fd = new FormData();
        fd.append('csrf_token', cfg.csrf);
        fd.append('page_id', cfg.pageId);
        fd.append('field', field);
        fd.append('prompt', prompt);
        fd.append('model', getModel());
        fd.append('working_content', workingContent);
        fd.append('history', JSON.stringify(history));

        fetch(cfg.chatEndpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(res) {
                return res.json().catch(function() {
                    throw new Error('Invalid server response (HTTP ' + res.status + ')');
                });
            })
            .then(function(data) {
                if (!data.success) throw new Error(data.message || 'Edit failed');
                workingContent = data.result;
                history.push({ role: 'user', content: prompt });
                history.push({ role: 'assistant', content: 'Edits applied: ' + JSON.stringify(data.changes || []) });
                addAssistantMessage(data.changes || []);
                setChatStatus((data.changes || []).length + ' change(s) pending — click "Apply all changes" when done.', '');
            })
            .catch(function(err) {
                addChatError(err.message || 'Edit failed');
                setChatStatus('Nothing was changed.', 'error');
            })
            .finally(function() {
                chatBusy = false;
                if (sendBtn) sendBtn.disabled = false;
                ta.focus();
            });
    }

    function chatApply() {
        if (workingContent === null) {
            setChatStatus('Nothing to apply yet.', '');
            return;
        }
        writeField(getField(), workingContent);
        setChatStatus('Applied to field. Don\'t forget to Save the page.', '');
        const panel = document.querySelector('.ai-panel');
        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function chatUndo() {
        if (history.length === 0) {
            setChatStatus('Nothing to undo.', '');
            return;
        }
        // Roll back two history entries (user + assistant) at a time
        history.pop(); // assistant
        const lastUser = history.pop(); // user
        resetChatUi();
        history.forEach(function(turn) {
            if (turn.role === 'user') {
                addUserMessage(turn.content);
            }
        });
        workingContent = null;
        setChatStatus(lastUser ? 'Undid last change. The field copy was reset — keep chatting.' : 'Undid.', '');
    }

    function chatReset() {
        workingContent = null;
        originalContent = null;
        history = [];
        resetChatUi();
        setChatStatus('Reset. Starting fresh from the field\'s current content.', '');
    }

    // ---- Full mode (single-shot replace) -----------------------------------
    function getPrompt() {
        const ta = document.getElementById('ai-prompt');
        return ta ? ta.value.trim() : '';
    }

    function showFullResult(html) {
        const box = document.getElementById('ai-result');
        const content = document.getElementById('ai-result-content');
        const meta = document.getElementById('ai-result-meta');
        if (!box || !content) return;
        content.textContent = html;
        const f = getFieldConfig(getField());
        if (meta) meta.textContent = f ? '— ' + f.label : '';
        box.classList.remove('hidden');
    }

    function hideFullResult() {
        const box = document.getElementById('ai-result');
        if (box) box.classList.add('hidden');
    }

    function fullRequest() {
        const prompt = getPrompt();
        if (!prompt) {
            setFullStatus('Please enter a prompt first', 'error');
            return Promise.reject(new Error('empty prompt'));
        }

        const btn = document.getElementById('ai-generate');
        const fd = new FormData();
        fd.append('csrf_token', cfg.csrf);
        fd.append('page_id', cfg.pageId);
        fd.append('field', getField());
        fd.append('prompt', prompt);
        fd.append('model', getModel());
        fd.append('mode', 'full');

        if (btn) btn.disabled = true;
        setFullStatus('Generating…', 'loading');

        return fetch(cfg.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(res) {
                return res.json().catch(function() {
                    throw new Error('Invalid server response (HTTP ' + res.status + ')');
                });
            })
            .then(function(data) {
                if (!data.success) throw new Error(data.message || 'Generation failed');
                showFullResult(data.result);
                setFullStatus('Done. Review and click Apply when satisfied.', '');
            })
            .catch(function(err) {
                setFullStatus(err.message || 'Generation failed', 'error');
            })
            .finally(function() {
                if (btn) btn.disabled = false;
            });
    }

    // ---- Mode switching ----------------------------------------------------
    function switchMode() {
        const mode = getMode();
        const chatWrap = document.getElementById('ai-chat-wrap');
        const fullWrap = document.getElementById('ai-full-wrap');
        if (!chatWrap || !fullWrap) return;
        if (mode === 'edits') {
            chatWrap.style.display = '';
            fullWrap.style.display = 'none';
        } else {
            chatWrap.style.display = 'none';
            fullWrap.style.display = '';
        }
    }

    function getMode() {
        const sel = document.getElementById('ai-mode');
        return sel ? sel.value : 'edits';
    }

    // ---- Public API --------------------------------------------------------
    window.aiChatSend = function() { chatSend(); };
    window.aiChatApply = function() { chatApply(); };
    window.aiChatUndo = function() { chatUndo(); };
    window.aiChatReset = function() { chatReset(); };

    window.aiGenerate = function() { fullRequest(); };
    window.aiRegenerate = function() { fullRequest(); };
    window.aiApply = function() {
        const box = document.getElementById('ai-result');
        const content = document.getElementById('ai-result-content');
        if (!box || !content || box.classList.contains('hidden')) return;
        writeField(getField(), content.textContent);
        setFullStatus('Applied to field. Don\'t forget to Save the page.', '');
        hideFullResult();
        const panel = document.querySelector('.ai-panel');
        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    window.aiDiscard = function() {
        setFullStatus('Discarded.', '');
        hideFullResult();
    };

    // ---- Wire up events ----------------------------------------------------
    document.querySelectorAll('.ai-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            const ta = document.getElementById('ai-prompt');
            if (!ta) return;
            ta.value = chip.dataset.prompt || '';
            ta.focus();
            setFullStatus('', '');
        });
    });

    const chatInput = document.getElementById('ai-chat-prompt');
    if (chatInput) {
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatSend();
            }
        });
    }

    const modeSel = document.getElementById('ai-mode');
    if (modeSel) {
        modeSel.addEventListener('change', function() {
            switchMode();
            setChatStatus('', '');
            setFullStatus('', '');
        });
    }
    switchMode();
})();

function toggleAiPanel(btn) {
    const panel = btn.closest('.ai-panel');
    if (panel) panel.classList.toggle('collapsed');
}