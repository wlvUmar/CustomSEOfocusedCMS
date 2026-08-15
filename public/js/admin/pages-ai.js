// FILE: public/js/admin/pages-ai.js
// AI Assistant for the page edit form (OpenRouter backend).

(function() {
    'use strict';

    const cfg = window.AI_CONFIG;
    if (!cfg) return;

    let lastResult = null;

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

    function setStatus(text, type) {
        const el = document.getElementById('ai-status');
        if (!el) return;
        el.textContent = text || '';
        el.className = 'ai-panel__status' + (type ? ' ' + type : '');
    }

    function showResult(html) {
        const box = document.getElementById('ai-result');
        const content = document.getElementById('ai-result-content');
        const meta = document.getElementById('ai-result-meta');
        if (!box || !content) return;
        content.textContent = html;
        const field = document.getElementById('ai-field');
        const f = getFieldConfig(field ? field.value : '');
        if (meta) meta.textContent = f ? '— ' + f.label : '';
        box.classList.remove('hidden');
    }

    function hideResult() {
        const box = document.getElementById('ai-result');
        if (box) box.classList.add('hidden');
        lastResult = null;
    }

    function getPrompt() {
        const ta = document.getElementById('ai-prompt');
        return ta ? ta.value.trim() : '';
    }

    function doRequest() {
        const prompt = getPrompt();
        if (!prompt) {
            setStatus('Please enter a prompt first', 'error');
            return Promise.reject(new Error('empty prompt'));
        }

        const fieldSelect = document.getElementById('ai-field');
        const modelSelect = document.getElementById('ai-model');
        const field = fieldSelect ? fieldSelect.value : 'content_ru';
        const model = modelSelect ? modelSelect.value : 'deepseek/deepseek-chat';
        const btn = document.getElementById('ai-generate');

        const fd = new FormData();
        fd.append('csrf_token', cfg.csrf);
        fd.append('page_id', cfg.pageId);
        fd.append('field', field);
        fd.append('prompt', prompt);
        fd.append('model', model);

        if (btn) btn.disabled = true;
        setStatus('Generating…', 'loading');

        return fetch(cfg.endpoint, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(res) {
                return res.json().catch(function() {
                    throw new Error('Invalid server response (HTTP ' + res.status + ')');
                });
            })
            .then(function(data) {
                if (!data.success) throw new Error(data.message || 'Generation failed');
                lastResult = data.result;
                showResult(data.result);
                setStatus('Done. Review and click Apply when satisfied.', '');
            })
            .catch(function(err) {
                setStatus(err.message || 'Generation failed', 'error');
            })
            .finally(function() {
                if (btn) btn.disabled = false;
            });
    }

    window.aiGenerate = function() { doRequest(); };

    window.aiRegenerate = function() { doRequest(); };

    window.aiApply = function() {
        if (lastResult === null) return;
        const fieldSelect = document.getElementById('ai-field');
        const field = fieldSelect ? fieldSelect.value : 'content_ru';
        writeField(field, lastResult);
        setStatus('Applied to field. Don\'t forget to Save the page.', '');
        hideResult();
        const panel = document.querySelector('.ai-panel');
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    window.aiDiscard = function() {
        setStatus('Discarded.', '');
        hideResult();
    };

    // Quick-action chips prefill the prompt textarea
    document.querySelectorAll('.ai-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            const ta = document.getElementById('ai-prompt');
            if (!ta) return;
            ta.value = chip.dataset.prompt || '';
            ta.focus();
            setStatus('', '');
        });
    });
})();

function toggleAiPanel(btn) {
    const panel = btn.closest('.ai-panel');
    if (panel) panel.classList.toggle('collapsed');
}
