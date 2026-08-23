<?php
// path: ./views/admin/ai-studio/index.php
require BASE_PATH . '/views/admin/layout/header.php';
?>

<div class="ai-studio ai-studio--app">
    <header class="ai-studio__topbar">
        <div class="ai-studio__heading">
            <h1>AI Studio</h1>
            <p class="ai-studio__subtitle">Investigate, edit pages, preview live — destructive changes require approval.</p>
        </div>
        <div class="ai-studio__topbar-right">
            <div id="ai-gsc-bar" class="ai-gsc-inline" aria-label="GSC data">
                <span class="ai-gsc-inline__dot" aria-hidden="true"></span>
                <span class="ai-gsc-inline__label">GSC</span>
                <?php
                  $gscConfigured = !empty($gscStatus['configured']);
                  $gscConnected = !empty($gscStatus['connected']);
                ?>
                <?php if (!$gscConfigured): ?>
                    <span id="ai-gsc-status" class="ai-gsc-inline__status ai-gsc-inline__status--warn">Not configured</span>
                <?php elseif ($gscConnected): ?>
                    <span id="ai-gsc-status" class="ai-gsc-inline__status ai-gsc-inline__status--ok" title="<?= e($gscStatus['site_url'] ?? '') ?>">Connected</span>
                <?php else: ?>
                    <span id="ai-gsc-status" class="ai-gsc-inline__status">Not connected</span>
                <?php endif; ?>
                <?php if ($gscConfigured && !$gscConnected): ?>
                    <a id="ai-gsc-connect" href="<?= BASE_URL ?>/admin/ai-studio/gsc-auth" class="ai-btn ai-btn--primary ai-btn--xs"><i data-feather="link"></i> Connect</a>
                <?php elseif ($gscConnected): ?>
                    <button id="ai-gsc-disconnect" type="button" class="ai-btn ai-btn--ghost ai-btn--xs" title="Disconnect Search Console"><i data-feather="log-out"></i> Disconnect</button>
                <?php endif; ?>
            </div>
            <div class="ai-studio__controls">
                <label class="ai-studio__model-label" for="ai-model">Model</label>
                <select id="ai-model">
                    <?php foreach ($models as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $key === 'deepseek/deepseek-chat' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="ai-new-session" type="button" class="ai-btn ai-btn--ghost ai-btn--sm"><i data-feather="rotate-ccw"></i> New session</button>
                <button id="ai-preview-toggle" type="button" class="ai-btn ai-btn--ghost ai-btn--sm" aria-pressed="true"><i data-feather="sidebar"></i> <span id="ai-preview-toggle-label">Hide preview</span></button>
            </div>
        </div>
    </header>

    <div class="ai-studio__layout ai-studio__layout--full">
        <section class="ai-studio__chat">
            <div class="ai-studio__chat-head">
                <span class="ai-studio__chat-head__title"><i data-feather="message-square"></i> Conversation</span>
                <div class="ai-studio__chat-head__actions">
                    <button id="ai-history-toggle" type="button" class="ai-btn ai-btn--ghost ai-btn--sm" title="Chat history"><i data-feather="clock"></i> History</button>
                    <span class="ai-studio__chat-head__hint">Shift+Enter for a new line</span>
                </div>
            </div>

            <div id="ai-history-panel" class="ai-history-panel" hidden>
                <div class="ai-history-panel__head">
                    <span>Sessions</span>
                    <button id="ai-history-close" type="button" class="ai-btn ai-btn--ghost ai-btn--sm"><i data-feather="x"></i></button>
                </div>
                <div id="ai-history-list" class="ai-history-list"></div>
            </div>

            <div class="ai-transcript-wrap">
                <div id="ai-transcript" class="ai-transcript" aria-live="polite">
                    <div class="ai-welcome">
                        <div class="ai-welcome__icon"><i data-feather="zap"></i></div>
                        <h2 class="ai-welcome__title">Hi, I'm your AI Studio agent</h2>
                        <p class="ai-welcome__text">I can inspect pages, rotation variants, FAQs and analytics, then edit content and show you a live preview. Ask me to improve a page, find underperforming content, or create a new section.</p>
                    </div>
                </div>
                <button id="ai-scroll-bottom" type="button" class="ai-scroll-fab" hidden aria-label="Scroll to latest message">
                    <i data-feather="arrow-down"></i>
                </button>
            </div>

            <div id="ai-activity" class="ai-activity" hidden>
                <span class="ai-activity__spinner" aria-hidden="true"></span>
                <span id="ai-activity-text" class="ai-activity__text">Working…</span>
                <span id="ai-activity-timer" class="ai-activity__timer"></span>
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

            <form id="ai-form" class="ai-composer" autocomplete="off">
                <div class="ai-composer__field">
                    <textarea id="ai-input" rows="3" placeholder="e.g. Find the weakest pages by traffic and propose an improved intro section for the worst one…"></textarea>
                </div>
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
        baseUrl: <?= json_encode(BASE_URL) ?>,
        csrf: <?= json_encode(generateCSRFToken()) ?>
    };
</script>
<script src="<?= BASE_URL ?>/js/admin/ai-studio.js"></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>