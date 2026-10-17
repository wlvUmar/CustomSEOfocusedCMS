<?php
// path: ./views/partials/article-admin-bar.php
// Relocated verbatim from views/templates/article.php - no logic changes.
?>
    <?php if ($isAdmin): ?>
    <div class="admin-toolbar">
        <span><?= $lang === 'ru' ? 'АДМИН — просмотр статьи' : 'ADMIN — Maqolani ko‘rish' ?></span>
        <div>
            <a href="<?= BASE_URL ?>/admin/articles/edit/<?= $article['id'] ?>"><?= $lang === 'ru' ? 'Редактировать статью' : 'Maqolani tahrirlash' ?></a>
            <a href="<?= BASE_URL ?>/admin/articles"><?= $lang === 'ru' ? 'Все статьи' : 'Barcha maqolalar' ?></a>
            <a href="<?= BASE_URL ?>/admin/dashboard"><?= $lang === 'ru' ? 'Панель' : 'Boshqaruv' ?></a>
        </div>
    </div>
    <?php endif; ?>
