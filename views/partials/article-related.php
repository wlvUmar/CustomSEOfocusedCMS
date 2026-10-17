<?php
// path: ./views/partials/article-related.php
// Relocated verbatim from views/templates/article.php — no logic changes.
?>
            <!-- Bottom Links Section -->

            <div class="article-bottom-links">
                <!-- Related Page CTA Banner -->
                <?php if (!empty($relatedPage)): ?>
                <a href="<?= BASE_URL ?>/<?= $relatedPage['slug'] ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>" class="related-page-banner">
                    <div class="rpb-content">
                        <span class="rpb-label"><?= $lang === 'ru' ? 'Связанная услуга:' : 'Bog\'liq xizmat:' ?></span>
                        <span class="rpb-title"><?= e($relatedPage["title_$lang"]) ?></span>
                    </div>
                    <span class="rpb-arrow">
                        <?= $lang === 'ru' ? 'Подробнее' : 'Batafsil' ?> &rarr;
                    </span>
                </a>
                <?php endif; ?>

                <!-- Internal Links Suggestions -->
                <?php if (!empty($internalLinks)): ?>
                    <section class="internal-links-section">
                        <h2><?= $lang === 'ru' ? 'Читайте также' : 'O\'qishni tavsiya qilamiz' ?></h2>
                        <div class="internal-links-grid">
                            <?php foreach ($internalLinks as $link): ?>
                                <a href="<?= BASE_URL ?>/<?= $link['slug'] ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>" class="internal-link-card">
                                    <span class="link-icon">📄</span>
                                    <span class="link-title"><?= e($link['title']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($relatedArticles)): ?>
                    <section class="related-articles-section">
                        <h2><?= $lang === 'ru' ? 'Похожие статьи' : 'O\'xshash maqolalar' ?></h2>
                        <div class="related-articles-grid">
                            <?php foreach ($relatedArticles as $related): ?>
                                <a href="<?= BASE_URL ?>/articles/<?= $related['id'] ?><?= $lang !== DEFAULT_LANGUAGE ? '/' . $lang : '' ?>"
                                   class="related-article-card">
                                    <?php if (!empty($related['image'])): ?>
                                        <img src="<?= BASE_URL ?>/uploads/<?= e($related['image']) ?>"
                                             alt="<?= e($related["title_$lang"]) ?>"
                                             loading="lazy">
                                    <?php endif; ?>
                                    <div class="related-article-content">
                                        <h3><?= e($related["title_$lang"]) ?></h3>
                                        <?php if (!empty($related["excerpt_$lang"])): ?>
                                            <p><?= e($related["excerpt_$lang"]) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
