   <footer>
        <div class="container">
            <p><strong><?= e($seo["site_name_$lang"]) ?></strong></p>
            <?php if ($seo['phone']): ?>
            <p><?= $lang === 'ru' ? 'Телефон' : 'Telefon' ?>: <?= e($seo['phone']) ?></p>
            <?php endif; ?>
            <?php if ($seo['email']): ?>
            <p>Email: <?= e($seo['email']) ?></p>
            <?php endif; ?>
            <?php if ($seo["address_$lang"]): ?>
            <p><?= $lang === 'ru' ? 'Адрес' : 'Manzil' ?>: <?= e($seo["address_$lang"]) ?></p>
            <?php endif; ?>
            <?php if ($seo["working_hours_$lang"]): ?>
            <p><?= $lang === 'ru' ? 'Режим работы' : 'Ish vaqti' ?>: <?= e($seo["working_hours_$lang"]) ?></p>
            <?php endif; ?>
            <p style="margin-top: 20px; opacity: 0.7;">© <?= date('Y') ?> <?= e($seo["site_name_$lang"]) ?></p>
        </div>
    </footer>
</body>
</html>