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

    <section class="mq-gen" id="mqStock" aria-labelledby="mqStockTitle">
        <h2 id="mqStockTitle">Найти фото в интернете</h2>
        <?php if (empty($stockReady)): ?>
            <div class="mq-warn">Не задан ключ в <code>.env</code>: добавьте <code>PIXABAY_API_KEY</code> (бесплатно: <a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener noreferrer">pixabay.com/api/docs</a>) или <code>PEXELS_API_KEY</code>.</div>
        <?php else: ?>
        <div class="mq-gen-grid">
            <label>Страница
                <input type="text" id="mqStockPage" list="mqPages" placeholder="slug страницы" autocomplete="off">
            </label>
            <label class="mq-stock-q">Запрос (подставится из страницы, лучше по-английски)
                <input type="text" id="mqStockQuery" placeholder="refrigerator kitchen" autocomplete="off" maxlength="100">
            </label>
            <label>Раздел
                <select id="mqStockSection">
                    <option value="gallery">Галерея</option><option value="content">Контент</option>
                    <option value="hero">Hero</option><option value="banner">Баннер</option>
                </select>
            </label>
        </div>
        <div class="mq-gen-actions">
            <button type="button" class="btn btn-secondary" id="mqStockSearchBtn">Искать</button>
            <button type="button" class="btn btn-primary" id="mqStockAdd" disabled>Добавить выбранные (0)</button>
            <span id="mqStockStatus" class="mq-gen-status" aria-live="polite"></span>
        </div>
        <div id="mqStockAlt" class="mq-stock-alt" hidden></div>
        <div id="mqStockGrid" class="mq-stock-grid"></div>
        <div class="mq-gen-actions">
            <button type="button" class="btn btn-secondary" id="mqStockMore" hidden>Показать ещё</button>
        </div>
        <p class="mq-note">Отметьте фото кликом и нажмите «Добавить выбранные» — они сразу загрузятся на сайт и прикрепятся к странице. Фото общие (например, «холодильник»), а не конкретная модель. <span id="mqStockCredit">Фото бесплатные, указывать автора не обязательно.</span></p>
        <?php endif; ?>
    </section>

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
        <label>Макс. ширина:
            <select id="mqMaxWidth" title="Уменьшает слишком большие фото перед загрузкой (быстрее загрузка и страница)">
                <option value="0">Как есть</option>
                <option value="2000">2000 px</option>
                <option value="1600" selected>1600 px</option>
                <option value="1200">1200 px</option>
                <option value="800">800 px</option>
            </select>
        </label>
        <label>Формат:
            <select id="mqFormat">
                <option value="">Как есть</option>
                <option value="webp">WebP (легче)</option>
                <option value="jpeg">JPEG</option>
            </select>
        </label>
        <span class="mq-spacer"></span>
        <button type="button" class="btn btn-secondary" id="mqClear">Очистить список</button>
        <button type="button" class="btn btn-primary" id="mqUpload">Загрузить и прикрепить</button>
    </div>

    <div id="mqRows" class="mq-rows"></div>
    <div id="mqSummary" class="mq-summary" aria-live="polite"></div>

    <datalist id="mqPages"></datalist>

    <!-- Crop window (Cropper.js) -->
    <div class="mq-modal" id="mqCrop" hidden role="dialog" aria-modal="true" aria-label="Обрезка фото">
        <div class="mq-modal__card">
            <div class="mq-modal__stage" id="mqCropStage"></div>
            <div class="mq-modal__bar">
                <div class="mq-ratios" id="mqRatios">
                    <button type="button" data-ratio="free" class="is-active">Свободно</button>
                    <button type="button" data-ratio="1.3333333">4:3</button>
                    <button type="button" data-ratio="1.7777778">16:9</button>
                    <button type="button" data-ratio="1">1:1</button>
                    <button type="button" data-ratio="0.75">3:4</button>
                </div>
                <button type="button" class="btn btn-secondary" data-rotate="-90" aria-label="Повернуть влево">⟲</button>
                <button type="button" class="btn btn-secondary" data-rotate="90" aria-label="Повернуть вправо">⟳</button>
                <span class="mq-modal__info" id="mqCropInfo"></span>
                <span class="mq-spacer"></span>
                <button type="button" class="btn btn-secondary" id="mqCropCancel">Отмена</button>
                <button type="button" class="btn btn-primary" id="mqCropApply">Применить</button>
            </div>
        </div>
    </div>
</div>

<script>window.MQ_PAGES = <?= json_encode($quickPages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" defer></script>
<script src="<?= BASE_URL ?>/js/admin/media-quick.js?v=<?= @filemtime(BASE_PATH . '/public/js/admin/media-quick.js') ?: 1 ?>" defer></script>

<?php require BASE_PATH . '/views/admin/layout/footer.php'; ?>
