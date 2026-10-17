<?php
// path: ./views/partials/faq-accordion.php
// Relocated verbatim from views/templates/page.php — no logic changes.
?>
    <?php
    // ─── FAQ ACCORDION ───────────────────────────────────────────────────────
    // Moved out of the dark footer (where it was a flat <details> list that
    // clashed with the rest of the page) and into the main content flow as a
    // proper branded accordion. $faqs / generateFAQSchema() logic in
    // header.php is untouched — this only changes where/how it's displayed.
    if (!empty($faqs)):
    ?>
    <section class="faq-section" data-animate="fade-up">
        <div class="section-label"><?= $lang === 'ru' ? 'Вопросы и ответы' : 'Savol-javoblar' ?></div>
        <h2 class="faq-section__title">
            <?= $lang === 'ru' ? 'Часто задаваемые вопросы' : 'Tez-tez beriladigan savollar' ?>
        </h2>

        <div class="faq-accordion">
            <?php foreach ($faqs as $i => $faq): ?>
            <div class="faq-acc-item<?= $i === 0 ? ' is-open' : '' ?>">
                <button type="button" class="faq-acc-item__q" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                    <span class="faq-acc-item__q-text"><?= e($faq["question_$lang"]) ?></span>
                    <span class="faq-acc-item__icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 7l5 5 5-5"/>
                        </svg>
                    </span>
                </button>
                <div class="faq-acc-item__a">
                    <div class="faq-acc-item__a-inner">
                        <p><?= nl2br(e($faq["answer_$lang"])) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
