<?php
// path: ./views/admin/ai-studio/index.php
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="ai-studio">
    <header class="ai-studio__topbar">
        <div class="ai-studio__heading">
            <h1>AI Studio</h1>
            <p class="ai-studio__subtitle">Agent over the admin: investigate, edit pages, preview live, confirm destructive changes.</p>
        </div>
        <div class="ai-studio__controls">
            <label class="ai-studio__model-label" for="ai-model">Model</label>
            <select id="ai-model">
                <?php foreach ($models as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $key === 'deepseek/deepseek-chat' ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button id="ai-new-session" type="button" class="ai-btn ai-btn--ghost"><i data-feather="rotate-ccw"></i> New session</button>
            <button id="ai-preview-toggle" type="button" class="ai-btn ai-btn--ghost" aria-pressed="true"><i data-feather="sidebar"></i> <span id="ai-preview-toggle-label">Hide preview</span></button>
        </div>
    </header>

    <div class="ai-studio__layout">
        <section class="ai-studio__chat">
            <div id="ai-transcript" class="ai-transcript" aria-live="polite">
                <div class="ai-msg ai-msg--agent">
                    <div class="ai-msg__body md">
                        <p>I'm the AI Studio agent. I can inspect pages, rotation variants, FAQs and analytics, then edit content and show you a live preview. Ask me to improve a page, find underperforming content, or create a new section.</p>
                    </div>
                </div>
            </div>

            <div id="ai-approval" class="ai-approval" hidden>
                <div class="ai-approval__title">
                    <i data-feather="shield"></i> Approval required
                </div>
                <div id="ai-approval-plan" class="ai-approval__plan"></div>
                <div id="ai-approval-reason" class="ai-approval__reason"></div>
                <div class="ai-approval__actions">
                    <button id="ai-approve" type="button" class="ai-btn ai-btn--primary"><i data-feather="check"></i> Approve</button>
                    <button id="ai-deny" type="button" class="ai-btn ai-btn--ghost"><i data-feather="x"></i> Deny</button>
                </div>
            </div>

            <div id="ai-suggestions" class="ai-suggestions" aria-label="Suggested prompts">
                <button type="button" class="ai-chip" data-prompt="Find the weakest pages by traffic and propose fixes for the worst one."><i data-feather="trending-down"></i> Find weakest pages</button>
                <button type="button" class="ai-chip" data-prompt="Read the homepage, then propose an improved intro section and render a preview."><i data-feather="edit-3"></i> Improve homepage intro</button>
                <button type="button" class="ai-chip" data-prompt="Add a &quot;How it works&quot; FAQ entry to the FAQ page."><i data-feather="help-circle"></i> Add a how-it-works FAQ</button>
            </div>

            <form id="ai-form" class="ai-composer" autocomplete="off">
                <textarea id="ai-input" rows="3" placeholder="e.g. Find the weakest pages by traffic and propose an improved intro section for the worst one…"></textarea>
                <div class="ai-composer__footer">
                    <div class="ai-composer__meta">
                        <span id="ai-status" class="ai-status">Ready</span>
                        <span id="ai-usage" class="ai-usage" title="">0 tok</span>
                    </div>
                    <div class="ai-composer__actions">
                        <button id="ai-stop" type="button" class="ai-btn ai-btn--danger" hidden><i data-feather="square"></i> Stop</button>
                        <button id="ai-send" type="submit" class="ai-btn ai-btn--primary"><i data-feather="send"></i> Send</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="ai-studio__preview">
            <div class="ai-preview__bar">
                <span class="ai-preview__title"><i data-feather="eye"></i> Live preview</span>
                <span class="ai-preview__side">
                    <span id="ai-preview-hint" class="ai-preview__hint">Renders here after each turn</span>
                    <button id="ai-preview-open" type="button" class="ai-btn ai-btn--ghost ai-btn--sm" title="Open preview in a new tab">
                        <i data-feather="external-link"></i> Open
                    </button>
                </span>
            </div>
            <div class="ai-preview__frame-wrap">
                <iframe id="ai-preview-frame" title="Live preview" sandbox="allow-same-origin"></iframe>
            </div>
        </section>
    </div>
</div>

<script>
    window.AI_STUDIO = {
        baseUrl: '<?= BASE_URL ?>',
        csrf: '<?= generateCSRFToken() ?>'
    };
</script>
<script src="<?= BASE_URL ?>/js/admin/ai-studio.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>