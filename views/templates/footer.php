<footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?= e($seo["site_name_$lang"]) ?></h3>
                    <p><?= $lang === 'ru' ? 'Покупаем бытовую технику, новую и б/у. Быстрая оценка, честная цена, моментальная оплата.' : 'Maishiy texnikani, yangi va ishlatilganni sotib olamiz. Tez baholash, adolatli narx, oniy to\'lov.' ?></p>
                </div>
                
                <div class="footer-section">
                    <h3><?= $lang === 'ru' ? 'Контакты' : 'Aloqa' ?></h3>
                    <?php if ($seo['phone']): ?>
                    <div class="contact-item">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                        </svg>
                        <a href="tel:<?= preg_replace('/[^0-9+]/', '', $seo['phone']) ?>"><?= e($seo['phone']) ?></a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($seo['email']): ?>
                    <div class="contact-item">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                        <a href="mailto:<?= e($seo['email']) ?>"><?= e($seo['email']) ?></a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="footer-section">
                    <h3><?= $lang === 'ru' ? 'Информация' : 'Ma\'lumot' ?></h3>
                    <?php if ($seo["address_$lang"]): ?>
                    <div class="contact-item">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= e($seo["address_$lang"]) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($seo["working_hours_$lang"]): ?>
                    <div class="contact-item">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= e($seo["working_hours_$lang"]) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($faqs)): ?>
            <section class="footer-faq">
                <h4><?= $lang === 'ru' ? 'FAQ' : 'Savollar' ?></h4>
                <div class="footer-faq-list">
                    <?php foreach ($faqs as $faq): ?>
                    <details class="footer-faq-item">
                        <summary><?= e($faq["question_$lang"]) ?></summary>
                        <div class="footer-faq-answer">
                            <p><?= nl2br(e($faq["answer_$lang"])) ?></p>
                        </div>
                    </details>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <div class="copyright">
                <p>© <?= date('Y') ?> <?= e($seo["site_name_$lang"]) ?>. <?= $lang === 'ru' ? 'Все права защищены.' : 'Barcha huquqlar himoyalangan.' ?></p>
            </div>
        </div>
    </footer>

    <script>
        window.baseUrl = "<?= rtrim(BASE_URL, '/') ?>";
        <?php if (!empty($seo['google_review_url'])): ?>
        window.googleReviewUrl = '<?= e($seo['google_review_url']) ?>';
        window.googleReviewPrompt = <?= json_encode($lang === 'ru' ? 'Спасибо! Не могли бы вы оставить отзыв о нашем сервисе?' : 'Rahmat! Bizning xizmatimiz haqida sharh qoldirasizmi?') ?>;
        <?php endif; ?>
    </script>
    <div id="review-modal" class="review-modal" hidden aria-hidden="true">
        <div class="review-modal__backdrop" data-review-modal-close></div>
        <div class="review-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="review-modal-title">
            <h3 id="review-modal-title"><?= $lang === 'ru' ? 'Оставить отзыв?' : 'Sharh qoldirasizmi?' ?></h3>
            <p id="review-modal-message" data-review-message>
                <?= $lang === 'ru' ? 'Спасибо! Не могли бы вы оставить отзыв о нашем сервисе?' : 'Rahmat! Bizning xizmatimiz haqida sharh qoldirasizmi?' ?>
            </p>
            <div class="review-modal__actions">
                <button type="button" class="review-modal__btn review-modal__btn--secondary" data-review-modal-close>
                    <?= $lang === 'ru' ? 'Закрыть' : 'Yopish' ?>
                </button>
                <a href="#" class="review-modal__btn review-modal__btn--primary" data-review-call>
                    <?= $lang === 'ru' ? 'Позвонить' : 'Qo\'ng\'iroq qilish' ?>
                </a>
                <?php if (!empty($seo['google_review_url'])): ?>
                <a href="<?= e($seo['google_review_url']) ?>" class="review-modal__btn review-modal__btn--review" target="_blank" rel="noopener noreferrer" data-review-open>
                    <?= $lang === 'ru' ? 'Оставить отзыв' : 'Sharh qoldirish' ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (defined('GTM_ID')): ?>
    <script>
        (function() {
            if (typeof window.__loadGTM !== 'function') return;
            var triggered = false;
            var load = function() {
                if (triggered) return;
                triggered = true;
                window.__loadGTM();
            };
            var events = ['pointerdown', 'keydown', 'touchstart', 'scroll', 'wheel'];
            events.forEach(function(evt) {
                window.addEventListener(evt, load, { once: true, passive: true });
            });
        })();
    </script>
    <?php endif; ?>
    <script src="<?= BASE_URL ?>/js/link-tracking.js" defer></script>
    <style>
    .review-modal[hidden] { display: none !important; }
    .review-modal {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .review-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
    }
    .review-modal__dialog {
        position: relative;
        z-index: 1;
        width: min(100%, 520px);
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    }
    .review-modal__dialog h3 {
        margin: 0 0 8px;
        font-size: 20px;
    }
    .review-modal__dialog p {
        margin: 0 0 18px;
        color: #475569;
        line-height: 1.6;
    }
    .review-modal__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }
    .review-modal__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 10px 14px;
        border-radius: 10px;
        text-decoration: none;
        border: 1px solid transparent;
        cursor: pointer;
        font: inherit;
    }
    .review-modal__btn--secondary {
        background: #f8fafc;
        color: #334155;
        border-color: #e2e8f0;
    }
    .review-modal__btn--primary {
        background: #2563eb;
        color: #fff;
    }
    .review-modal__btn--review {
        background: #16a34a;
        color: #fff;
    }
    body.review-modal-open {
        overflow: hidden;
    }
    </style>
</body>
</html>
