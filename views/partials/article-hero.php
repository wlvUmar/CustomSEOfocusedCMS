<?php
// path: ./views/partials/article-hero.php
// Relocated verbatim from views/templates/article.php — no logic changes.
?>
                <!-- Hero Header -->
                <div class="article-card-header">
                    <div class="header-content">
                        <h1><?= e($article["title_$lang"]) ?></h1>
                        <?php if (!empty($article["excerpt_$lang"])): ?>
                            <p class="article-subtitle"><?= e($article["excerpt_$lang"]) ?></p>
                        <?php endif; ?>
                        <div class="article-meta">
                            <span>
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?= date('d.m.Y', strtotime($article['created_at'])) ?>
                            </span>
                            <span>
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <?= e($article['author']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Featured Image -->
                <?php if (!empty($article['image'])): ?>
                    <?php $artDims = getImageDimensions($article['image']); ?>
                    <div class="article-card-image">
                        <img src="<?= BASE_URL ?>/uploads/<?= e($article['image']) ?>"
                             alt="<?= e($article["title_$lang"]) ?>"
                             <?= $artDims ? 'width="' . (int)$artDims['width'] . '" height="' . (int)$artDims['height'] . '"' : '' ?>
                             loading="lazy" decoding="async">
                    </div>
                <?php endif; ?>

                <div class="article-card-body">
                    <?= sanitizeFrontendHtml(enhanceContentSEO($article["content_$lang"] ?? '', $article["title_$lang"] ?? '', '')) ?>
                </div>
            </article>
