// FILE: public/js/admin/pages-ai.js
// AI Assistant for the page edit form (OpenRouter backend).
// Two modes:
//  - 'edits' (default): chat thread that makes targeted line edits (find/replace),
//    self-correcting when a match is ambiguous, and auto-applying to the field
//    as it goes (like an editing agent) unless auto-apply is turned off.
//  - 'full': iterative single-field rewrite — each prompt refines the previous
//    AI result, not the original saved value, until you Apply or Discard.

(function() {
    'use strict';

    const cfg = window.AI_CONFIG;
    if (!cfg) return;

    // ---- Chat ('edits' mode) state ------------------------------------------
    let workingContent = null;      // accumulated copy of the field being edited
    let originalContent = null;     // snapshot taken when chat mode started
    let history = [];               // prior {role, content} turns sent to the model
    let turns = [];                 // {prompt, changes, unresolved, before} for real undo + replay
    let chatField = null;           // which field the current chat session belongs to
    let chatBusy = false;

    // ---- Full mode state -----------------------------------------------------
    let fullWorkingContent = null;  // last AI output (or null = start from field)
    let fullHistory = [];
    let fullField = null;

    // ---- Shared field helpers ---------------------------------------------
    function getFieldConfig(field) {
        return cfg.fields[field] || null;
    }

    // tinymce.get(id) only works if the source <textarea>'s id matches our
    // field key. If it doesn't (missing id attribute, mismatched id, etc.)
    // fall back to scanning live editor instances by the underlying
    // textarea's `name` attribute, which is what the rest of the form (and
    // the PHP save handler) actually keys off of.
    function findTinymceEditor(field) {
        if (typeof tinymce === 'undefined') return null;
        const byId = tinymce.get(field);
        if (byId) return byId;
        const editors = tinymce.editors || [];
        for (let i = 0; i < editors.length; i++) {
            const el = editors[i].getElement && editors[i].getElement();
            if (el && el.getAttribute('name') === field) return editors[i];
        }
        return null;
    }

    function readField(field) {
        const f = getFieldConfig(field);
        if (!f) {
            console.error('[ai-panel] No field config for "' + field + '" — check AI_CONFIG.fields');
            return '';
        }
        if (f.type === 'tinymce') {
            const editor = findTinymceEditor(field);
            if (editor) return editor.getContent();
            const ta = document.querySelector('textarea[name="' + field + '"]');
            if (ta) return ta.value;
            console.error('[ai-panel] TinyMCE instance and fallback textarea both missing for "' + field + '"');
            return '';
        }
        const el = document.querySelector(f.selector);
        if (el) return el.value;
        console.error('[ai-panel] No element matches selector "' + f.selector + '" for field "' + field + '"');
        return '';
    }

    // Returns true if the field was actually located and written to, false
    // otherwise. Callers MUST check this before telling the user "Applied" —
    // previously this failed silently (wrong selector, TinyMCE not yet
    // initialized, etc.) while the UI still reported success.
    function writeField(field, html) {
        const f = getFieldConfig(field);
        if (!f) {
            console.error('[ai-panel] No field config for "' + field + '" — check AI_CONFIG.fields');
            return false;
        }
        if (f.type === 'tinymce') {
            const editor = findTinymceEditor(field);
            if (editor) {
                editor.setContent(html);
                // Sync TinyMCE's iframe content back into the underlying
                // <textarea> immediately so the value is correct even if
                // something reads the textarea directly before form submit.
                if (typeof editor.save === 'function') editor.save();
                return true;
            }
            const ta = document.querySelector('textarea[name="' + field + '"]');
            if (ta) {
                ta.value = html;
                return true;
            }
            console.error('[ai-panel] TinyMCE instance and fallback textarea both missing for "' + field + '"');
            return false;
        }
        const el = document.querySelector(f.selector);
        if (el) {
            el.value = html;
            // Let any listeners (validation, char counters, etc.) know the
            // value changed programmatically.
            el.dispatchEvent(new Event('input', { bubbles: true }));
            return true;
        }
        console.error('[ai-panel] No element matches selector "' + f.selector + '" for field "' + field + '"');
        return false;
    }

    function getField() {
        const sel = document.getElementById('ai-field');
        return sel ? sel.value : 'content_ru';
    }

    function getModel() {
        const sel = document.getElementById('ai-model');
        return sel ? sel.value : 'deepseek/deepseek-chat';
    }

    function isAutoApply() {
        const cb = document.getElementById('ai-autoapply');
        return cb ? cb.checked : true;
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

    function addAssistantMessage(changes, unresolved) {
        const chat = document.getElementById('ai-chat');
        if (!chat) return;
        const empty = chat.querySelector('.ai-chat__empty');
        if (empty) empty.remove();
        const msg = document.createElement('div');
        msg.className = 'ai-chat__msg ai-chat__msg--ai';

        let html = '<div class="ai-chat__bubble">';
        if ((!changes || changes.length === 0) && (!unresolved || unresolved.length === 0)) {
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
        (unresolved || []).forEach(function(u) {
            html += '<div class="ai-diff ai-diff--unresolved">';
            html += '<div class="ai-diff__row ai-diff__row--warn"><span class="ai-diff__label ai-diff__label--warn">skipped</span><span class="ai-diff__old">' + esc(u.find) + '</span></div>';
            html += '<div class="ai-diff__expl">' + esc(u.reason || 'Could not be applied safely.') + ' Try being more specific.</div>';
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
        chat.innerHTML = '<div class="ai-chat__empty">Tell the AI what to change, e.g. "replace the phone number in the hero with +998 90 123 45 67". It will locate the exact text, change only that, and (unless you turn off auto-apply) write it straight to the field as it goes.</div>';
    }

    function replayChatUi() {
        resetChatUi();
        turns.forEach(function(t) {
            addUserMessage(t.prompt);
            addAssistantMessage(t.changes, t.unresolved);
        });
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
        if (workingContent === null || chatField !== field) {
            workingContent = readField(field);
            originalContent = workingContent;
            chatField = field;
            history = [];
            turns = [];
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

        const before = workingContent;

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
                turns.push({ prompt: prompt, changes: data.changes || [], unresolved: data.unresolved || [], before: before });
                addAssistantMessage(data.changes || [], data.unresolved || []);

                const n = (data.changes || []).length;
                const u = (data.unresolved || []).length;
                let msg = n + ' change(s) applied';
                if (u > 0) msg += ', ' + u + ' skipped (too ambiguous — see above)';
                if (data.scoped) {
                    msg += ' · scoped to ' + (data.sections_used || []).length + '/' + data.sections_total + ' section(s)';
                }

                if (isAutoApply()) {
                    const ok = writeField(field, workingContent);
                    if (ok) {
                        setChatStatus(msg + ' — written to the field automatically.', u > 0 ? 'error' : '');
                    } else {
                        setChatStatus(msg + ', but auto-apply could not find the "' + field + '" field on the page (see console). The change is kept in this session — try "Apply all changes" or reload.', 'error');
                    }
                } else {
                    setChatStatus(msg + ' — click "Apply all changes" to write it to the field.', u > 0 ? 'error' : '');
                }
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
        // Use the field this session's content actually belongs to (chatField),
        // not whatever happens to be selected in the dropdown right now — those
        // can drift apart. Fall back to the dropdown only if we somehow have no
        // session field recorded.
        const targetField = chatField || getField();
        const ok = writeField(targetField, workingContent);
        if (ok) {
            setChatStatus('Applied to field. Don\'t forget to Save the page.', '');
        } else {
            setChatStatus('Could not apply — the "' + targetField + '" field wasn\'t found on the page (see browser console for details).', 'error');
            return;
        }
        const panel = document.querySelector('.ai-panel');
        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function chatUndo() {
        if (turns.length === 0) {
            setChatStatus('Nothing to undo.', '');
            return;
        }
        turns.pop();
        history.pop(); // assistant
        history.pop(); // user
        workingContent = turns.length ? turns[turns.length - 1].before : originalContent;
        replayChatUi();
        if (isAutoApply() && chatField) {
            const ok = writeField(chatField, workingContent);
            setChatStatus(ok
                ? 'Undid last change and updated the field.'
                : 'Undid last change, but couldn\'t write it to the field (see console). Try "Apply all changes".', ok ? '' : 'error');
        } else {
            setChatStatus('Undid last change. Click "Apply all changes" to write it to the field.', '');
        }
    }

    function chatReset() {
        workingContent = null;
        originalContent = null;
        chatField = null;
        history = [];
        turns = [];
        resetChatUi();
        setChatStatus('Reset. Starting fresh from the field\'s current content.', '');
    }

    // ---- Full mode (iterative rewrite) --------------------------------------
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

    function fullReset() {
        fullWorkingContent = null;
        fullHistory = [];
        fullField = null;
    }

    function fullRequest() {
        const prompt = getPrompt();
        if (!prompt) {
            setFullStatus('Please enter a prompt first', 'error');
            return Promise.reject(new Error('empty prompt'));
        }

        const field = getField();
        if (fullWorkingContent === null || fullField !== field) {
            fullWorkingContent = readField(field);
            fullHistory = [];
            fullField = field;
        }
        const iterating = fullHistory.length > 0;

        const btn = document.getElementById('ai-generate');
        const fd = new FormData();
        fd.append('csrf_token', cfg.csrf);
        fd.append('page_id', cfg.pageId);
        fd.append('field', field);
        fd.append('prompt', prompt);
        fd.append('model', getModel());
        fd.append('mode', 'full');
        fd.append('working_content', fullWorkingContent);
        fd.append('history', JSON.stringify(fullHistory));

        if (btn) btn.disabled = true;
        setFullStatus(iterating ? 'Refining…' : 'Generating…', 'loading');

        return fetch(cfg.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(res) {
                return res.json().catch(function() {
                    throw new Error('Invalid server response (HTTP ' + res.status + ')');
                });
            })
            .then(function(data) {
                if (!data.success) throw new Error(data.message || 'Generation failed');
                fullHistory.push({ role: 'user', content: prompt });
                fullHistory.push({ role: 'assistant', content: data.result });
                fullWorkingContent = data.result;
                showFullResult(data.result);
                setFullStatus('Done. Review and click Apply, or keep refining with another prompt.', '');
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
        // Same reasoning as chatApply(): apply to the field this result was
        // actually generated for, not whatever the dropdown shows right now.
        const targetField = fullField || getField();
        const ok = writeField(targetField, content.textContent);
        if (!ok) {
            setFullStatus('Could not apply — the "' + targetField + '" field wasn\'t found on the page (see browser console for details).', 'error');
            return;
        }
        setFullStatus('Applied to field. Don\'t forget to Save the page.', '');
        hideFullResult();
        fullReset();
        const panel = document.querySelector('.ai-panel');
        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    window.aiDiscard = function() {
        setFullStatus('Discarded.', '');
        hideFullResult();
        fullReset();
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

    // Switching the target field mid-session would silently mix content from
    // two different fields into one working copy — start clean instead.
    const fieldSel = document.getElementById('ai-field');
    if (fieldSel) {
        fieldSel.addEventListener('change', function() {
            if (workingContent !== null) chatReset();
            if (fullWorkingContent !== null) {
                fullReset();
                hideFullResult();
                setFullStatus('', '');
            }
        });
    }

    switchMode();
})();

function toggleAiPanel(btn) {
    const panel = btn.closest('.ai-panel');
    if (panel) panel.classList.toggle('collapsed');
}