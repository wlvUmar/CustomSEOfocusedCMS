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
