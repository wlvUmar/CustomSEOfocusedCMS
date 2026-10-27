<?php
// path: ./views/partials/bot-modal.php
// Relocated verbatim from views/templates/header.php — no logic changes.
?>
    <!-- Bot Modal: the one remaining popup. Triggered later (45s of active
         viewing OR 60% scroll depth, whichever first) and shown at most once
         per browser session via sessionStorage, not on every page load. -->
    <div id="bot-modal" class="bot-modal">
        <div class="bot-modal__card" role="dialog" aria-modal="true" aria-labelledby="bot-modal-title">
            <button class="bot-modal__close" onclick="closeBotModal(true)" aria-label="<?= $lang === 'ru' ? 'Закрыть' : 'Yopish' ?>">×</button>
            <div class="bot-modal__icon">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M22.05 1.577c-.393-.016-.784.08-1.117.235-.484.186-4.92 1.902-9.41 3.64-2.26.873-4.518 1.746-6.256 2.415-1.737.67-3.045 1.168-3.114 1.192-.46.16-1.082.362-1.61.984-.133.155-.267.354-.335.628s-.038.622.095.895c.265.547.714.773 1.244.976 1.76.564 3.58 1.102 5.087 1.608.556 1.96 1.09 3.927 1.618 5.89.174.394.553.54.944.544l-.002.02s.307.03.606-.042c.3-.07.677-.244 1.02-.565.377-.354 1.4-1.36 1.98-1.928l4.37 3.226.035.02s.484.34 1.192.388c.354.024.82-.044 1.22-.337.403-.294.67-.767.795-1.307.374-1.63 2.853-13.427 3.276-15.38l-.012.046c.296-1.1.187-2.108-.496-2.705-.342-.297-.736-.427-1.13-.444zm-.118 1.874c.027.025.025.025.002.027-.007-.002.08.118-.09.755l-.007.024-.005.022c-.432 1.997-2.936 13.9-3.27 15.356-.046.196-.065.182-.054.17-.1-.015-.285-.094-.3-.1l-7.48-5.525c2.562-2.467 5.182-4.7 7.827-7.08.468-.235.39-.96-.17-.972-.594.14-1.095.567-1.64.84-3.132 1.858-6.332 3.492-9.43 5.406-1.59-.553-3.177-1.012-4.643-1.467 1.272-.51 2.283-.886 3.278-1.27 1.738-.67 3.996-1.54 6.256-2.415 4.522-1.748 9.07-3.51 9.465-3.662l.032-.013.03-.013c.11-.05.173-.055.202-.057 0 0-.01-.033-.002-.026zM10.02 16.016l1.234.912c-.532.52-1.035 1.01-1.398 1.36z"/>
                </svg>
            </div>
            <?php // <p>, not <h2>: a popup title must not become the first heading of every page's outline ?>
            <p class="bot-modal__title" id="bot-modal-title">
                <?= $lang === 'ru' ? 'Продайте технику онлайн' : 'Texnikani onlayn soting'?>
            </p>
            <p class="bot-modal__desc">
                <?= $lang === 'ru'
                    ? 'Отправьте фото, описание и желаемую цену. Если товар нам подходит, мы предложим цену покупки.'
                    : 'Rasm, tavsif va istagan narxingizni yuboring. Agar tovar bizga mos kelsa, xarid narximizni taklif qilamiz.' ?>
            </p>
            <ul class="bot-modal__steps">
                <li class="bot-modal__step"><span class="bot-modal__step-num">1</span> <?= $lang === 'ru' ? 'Сфотографируйте технику' : 'Texnikaning rasmini oling' ?></li>
                <li class="bot-modal__step"><span class="bot-modal__step-num">2</span> <?= $lang === 'ru' ? 'Отправьте фото боту' : 'Botga rasm yuboring' ?></li>
                <li class="bot-modal__step"><span class="bot-modal__step-num">3</span> <?= $lang === 'ru' ?  'Получите ответ от нашей команды' : 'Jamoamizdan javob oling' ?></li>
            </ul>
            <a href="<?= TELEGRAM_BOT_URL ?>?start=<?= urlencode($page["clientID"] ?? "") ?>" class="bot-modal__cta" target="_blank" rel="noopener" data-track-click="1" data-cta="bot_modal" data-cta-manual onclick="markBotModalSeen()">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M22.05 1.577c-.393-.016-.784.08-1.117.235-.484.186-4.92 1.902-9.41 3.64-2.26.873-4.518 1.746-6.256 2.415-1.737.67-3.045 1.168-3.114 1.192-.46.16-1.082.362-1.61.984-.133.155-.267.354-.335.628s-.038.622.095.895c.265.547.714.773 1.244.976 1.76.564 3.58 1.102 5.087 1.608.556 1.96 1.09 3.927 1.618 5.89.174.394.553.54.944.544l-.002.02s.307.03.606-.042c.3-.07.677-.244 1.02-.565.377-.354 1.4-1.36 1.98-1.928l4.37 3.226.035.02s.484.34 1.192.388c.354.024.82-.044 1.22-.337.403-.294.67-.767.795-1.307.374-1.63 2.853-13.427 3.276-15.38z"/>
                </svg>
                <?= $lang === 'ru' ? 'Открыть Telegram-бот' : 'Telegram-botni ochish' ?>
            </a>
            <button type="button" class="bot-modal__dismiss" onclick="closeBotModal(true)">
                <?= $lang === 'ru' ? 'Не сейчас' : 'Hozir kerak emas' ?>
            </button>
            <p class="bot-modal__note"><?= $lang === 'ru' ? 'Бесплатно · Без регистрации · Работает 24/7' : 'Bepul · Ro\'yxatdan o\'tishsiz · 24/7 ishlaydi' ?></p>
        </div>
    </div>

    <script>
    window.BOT_MODAL_ENABLED = <?= !empty($botModalEnabled) ? 'true' : 'false' ?>;
    var BOT_MODAL_SESSION_KEY = 'botModalShown';
    var BOT_MODAL_LS_KEY = 'botModalShownAt';
    var BOT_MODAL_COOLDOWN_MS = 7 * 24 * 60 * 60 * 1000; // 7 days — less annoying than per-session

    function isBotModalCooledDown() {
        try {
            var at = localStorage.getItem(BOT_MODAL_LS_KEY);
            if (at && (Date.now() - parseInt(at, 10) < BOT_MODAL_COOLDOWN_MS)) return true;
            if (sessionStorage.getItem(BOT_MODAL_SESSION_KEY)) return true;
        } catch (e) {}
        return false;
    }
    function markBotModalSeen() {
        try { sessionStorage.setItem(BOT_MODAL_SESSION_KEY, '1'); localStorage.setItem(BOT_MODAL_LS_KEY, String(Date.now())); } catch (e) {}
    }
    function lockBodyScroll(lock) {
        if (lock) {
            var sb = window.innerWidth - document.documentElement.clientWidth;
            document.documentElement.style.scrollbarGutter = 'stable';
            document.body.style.overflow = 'hidden';
            if (sb > 0) document.body.style.paddingRight = sb + 'px';
        } else {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            document.documentElement.style.scrollbarGutter = '';
        }
    }
    function closeBotModal(remember) {
        var modal = document.getElementById('bot-modal');
        if (!modal || !modal.classList.contains('is-open')) return;
        modal.classList.remove('is-open');
        lockBodyScroll(false);
        if (remember) markBotModalSeen();
        // allow focus return
        setTimeout(function() {
            var prev = document.activeElement;
            if (prev) try { prev.blur(); } catch(e) {}
        }, 0);
    }
    function openBotModal() {
        if (isBotModalCooledDown()) return;
        var modal = document.getElementById('bot-modal');
        if (!modal || modal.classList.contains('is-open')) return;
        // don't open if page hidden or user already typing
        if (document.visibilityState === 'hidden') return;
        if (document.querySelector('input:focus, textarea:focus')) return;
        modal.classList.add('is-open');
        lockBodyScroll(true);
        markBotModalSeen();
        if (window.trackCtaEvent) window.trackCtaEvent('bot_modal', 'view');
        // trap focus to close button for a11y, no layout thrash
        try { modal.querySelector('.bot-modal__close')?.focus({preventScroll:true}); } catch(e) {}
    }

    // Close modal when clicking overlay — passive, no reflow
    document.getElementById('bot-modal')?.addEventListener('click', function(e) {
        if (e.target === this) closeBotModal(true);
    }, {passive: true});
    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeBotModal(true);
    });

    // Trigger: gentle — deep scroll (60%) or desktop exit-intent only (no timer), max once per 7 days.
    // Skipped while the inline #bot-card is on screen: the visitor is already looking at the same offer.
    // To test: localStorage.removeItem('botModalShownAt'); sessionStorage.removeItem('botModalShown')
    (function() {
        if (!window.BOT_MODAL_ENABLED) return;
        if (isBotModalCooledDown()) return;
        // respect reduced-motion — delay longer
        var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReduced) return; // don't auto-open for motion-sensitive users

        var fired = false;
        function botCardOnScreen() {
            var card = document.getElementById('bot-card');
            if (!card) return false;
            var r = card.getBoundingClientRect();
            return r.bottom > 0 && r.top < window.innerHeight;
        }
        function maybeOpen() {
            if (fired || isBotModalCooledDown()) return;
            if (document.visibilityState === 'hidden') return;
            if (botCardOnScreen()) return;
            fired = true;
            openBotModal();
            cleanup();
        }
        function cleanup() {
            window.removeEventListener('scroll', onScroll, {passive: true});
            document.removeEventListener('mouseout', onExitIntent);
            document.removeEventListener('visibilitychange', onVis);
        }
        function onVis() {
            if (document.visibilityState === 'visible' && fired) return;
        }
        // throttle scroll to rAF — avoids freeze
        var ticking = false;
        function onScroll() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function() {
                ticking = false;
                var scrolled = window.scrollY;
                var docEl = document.documentElement;
                var max = docEl.scrollHeight - window.innerHeight;
                if (max > 120 && scrolled / max > 0.6) {
                    maybeOpen();
                }
            });
        }
        function onExitIntent(e) {
            // mouse leaves viewport at top — user likely to bounce
            if (e.clientY <= 2 && window.scrollY > 200) {
                maybeOpen();
            }
        }
        window.addEventListener('scroll', onScroll, {passive: true});
        document.addEventListener('mouseout', onExitIntent, {passive: true});
        document.addEventListener('visibilitychange', onVis, {passive: true});
    })();
    </script>
