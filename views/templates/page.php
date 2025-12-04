<?php require 'header.php'; ?>

<main>
    <div class="container">
        <?php
        $content = $page["content_$lang"];
        $content = replacePlaceholders($content, $page, $seo);
        echo $content;
        ?>
    </div>
</main>

<?php require 'footer.php'; ?>