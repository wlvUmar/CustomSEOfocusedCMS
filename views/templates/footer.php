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
            
            <div class="copyright">
                <p>© <?= date('Y') ?> <?= e($seo["site_name_$lang"]) ?>. <?= $lang === 'ru' ? 'Все права защищены.' : 'Barcha huquqlar himoyalangan.' ?></p>
            </div>
        </div>
    </footer>
</body>
</html>