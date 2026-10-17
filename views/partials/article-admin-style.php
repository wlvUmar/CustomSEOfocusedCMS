<?php
// path: ./views/partials/article-admin-style.php
// Relocated verbatim from views/templates/article.php - no logic changes.
?>
    <?php if ($isAdmin): ?>
    <style>
    .admin-toolbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        color: white;
        padding: 8px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 9999;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        font-size: 13px;
    }
    .admin-toolbar a {
        color: white;
        text-decoration: none;
        padding: 6px 12px;
        background: rgba(255,255,255,0.15);
        border-radius: 4px;
        margin-left: 10px;
    }
    body.admin-mode {
        padding-top: 50px;
    }
    </style>
     <?php endif; ?>
