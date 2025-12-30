<?php 
// path: ./views/templates/page.php
require 'header.php'; 
?>

<main>
    <div class="container">
        <?php
        $content = $page["content_$lang"];
        $content = renderTemplate($content, $templateData);
        echo $content;
        ?>
        
        <?php if (!empty($faqs)): ?>
        <section class="faq-section">
            <h2><?= $lang === 'ru' ? 'Часто задаваемые вопросы' : 'Ko\'p beriladigan savollar' ?></h2>
            
            <div class="faq-list">
                <?php foreach ($faqs as $faq): ?>
                <div class="faq-item">
                    <h3><?= e($faq["question_$lang"]) ?></h3>
                    <p><?= nl2br(e($faq["answer_$lang"])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</main>

<?php require 'footer.php'; ?>