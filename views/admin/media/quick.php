<?php require BASE_PATH . '/views/admin/layout/header.php'; ?>

<div class="mq" id="mq">
    <div class="page-header">
        <h1><i data-feather="zap"></i> Быстрое добавление фото</h1>
        <div class="page-actions">
            <a href="<?= BASE_URL ?>/admin/media" class="btn btn-secondary">← Медиатека</a>
        </div>
    </div>

    <p class="mq-hint">
        Перетащите фото сюда (или вставьте из буфера: Ctrl+V). Файл с именем <code>slug-1.jpg</code> сам привяжется к странице <code>slug</code>;
        суффикс <code>-hero</code>, <code>-banner</code>, <code>-content</code> выбирает раздел, иначе — «галерея». Alt-текст RU/UZ подставляется из заголовка страницы —
        это шаблон, а не описание картинки, поправьте при необходимости. Макс. размер файла: <?= e($maxUploadMb) ?> МБ.
    </p>

    <section class="mq-gen" id="mqGen" aria-labelledby="mqGenTitle">
        <h2 id="mqGenTitle">Создать изображения с помощью ИИ</h2>
        <?php if (empty($gen['configured'])): ?>
            <div class="mq-warn">Не задан <code>OPENROUTER_API_KEY</code> в <code>.env</code> — генерация недоступна.</div>
        <?php elseif (empty($gen['ready'])): ?>
            <div class="mq-warn">Нет таблицы <code>image_generations</code> — запустите <code>php migrate.php</code> (миграция 019), затем обновите страницу.</div>
        <?php else: ?>
        <div class="mq-gen-grid">
            <label class="mq-gen-wide">Страница
                <input type="text" id="mqGenPage" list="mqPages" placeholder="slug страницы" autocomplete="off">
            </label>
            <label class="mq-gen-wide">Описание (правьте как нужно)
                <textarea id="mqGenPrompt" rows="3" maxlength="600" placeholder="Выберите страницу — описание подставится из её заголовка"></textarea>
            </label>
            <label>Сколько
                <select id="mqGenCount">
                    <option value="1">1</option><option value="2" selected>2</option><option value="3">3</option><option value="4">4</option>
                </select>
            </label>
            <label>Формат
                <select id="mqGenAspect">
                    <?php foreach ($gen['aspects'] as $a): ?><option value="<?= e($a) ?>"><?= e($a) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Раздел
                <select id="mqGenSection">
                    <option value="gallery">Галерея</option><option value="content">Контент</option>
                    <option value="hero">Hero</option><option value="banner">Баннер</option>
                </select>
            </label>
            <label>Модель
                <select id="mqGenModel">
                    <?php foreach ($gen['models'] as $id => $label): ?><option value="<?= e($id) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="mq-gen-actions">
            <button type="button" class="btn btn-primary" id="mqGenBtn">Создать</button>
            <span id="mqGenStatus" class="mq-gen-status" aria-live="polite"></span>
            <span class="mq-gen-quota" id="mqGenQuota">Сегодня создано: <?= (int)$gen['used'] ?> из <?= (int)$gen['limit'] ?></span>
        </div>
        <p class="mq-note">Результаты появятся в списке ниже — ничего не сохраняется, пока вы не нажмёте «Загрузить и прикрепить». ИИ рисует «похожий» предмет, а не конкретную модель: не выдавайте такие картинки за фото реального товара. Платно (списывается с баланса OpenRouter), есть дневной лимит.</p>
        <?php endif; ?>
    </section>

    <div class="mq-drop" id="mqDrop" tabindex="0" role="button" aria-label="Добавить фото">
        <input type="file" id="mqFile" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
        <strong>Перетащите фото сюда</strong>
        <span>или нажмите, чтобы выбрать · можно вставить из буфера (Ctrl+V)</span>
    </div>

    <div class="mq-bulk" id="mqBulk" hidden>
        <label>Для файлов без страницы:
            <input type="text" id="mqBulkPage" list="mqPages" placeholder="slug страницы" autocomplete="off">
        </label>
        <button type="button" class="btn btn-secondary" id="mqApplyPage">Применить</button>
        <label>Раздел для всех:
            <select id="mqBulkSection">
                <option value="">— не менять —</option>
                <option value="gallery">Галерея</option>
                <option value="content">Контент</option>
                <option value="hero">Hero</option>
                <option value="banner">Баннер</option>
            </select>
        </label>
        <span class="mq-spacer"></span>
        <button type="button" class="btn btn-secondary" id="mqClear">Очистить список</button>
        <button type="button" class="btn btn-primary" id="mqUpload">Загрузить и прикрепить</button>
    </div>

    <div id="mqRows" class="mq-rows"></div>
    <div id="mqSummary" class="mq-summary" aria-live="polite"></div>

    <datalist id="mqPages"></datalist>
</div>

<script>window.MQ_PAGES = <?= json_encode($quickPages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= BASE_URL ?>/js/admin/media-quick.js?v=<?= @filemtime(BASE_PATH . '/public/js/admin/media-quick.js') ?: 1 ?>" defer></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
