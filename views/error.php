<?php

$lang = $lang ?? ($_GET['lang'] ?? (function_exists('getCurrentLanguage') ? getCurrentLanguage() : 'ru'));
$lang = in_array($lang, ['ru', 'uz'], true) ? $lang : 'ru';

$code = intval($_GET['code'] ?? 500);
$allowedCodes = [400, 401, 403, 404, 500];
if (!in_array($code, $allowedCodes, true)) $code = 500;

http_response_code($code);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);
try {
    $seoModel = new SEO();
    $seoSettings = $seoModel->getSettings() ?: [];
} catch (Throwable $e) {
    $seoSettings = [];
}


$seo = $seoSettings;

// Ensure default SEO keys
foreach(['ru', 'uz'] as $l) {
    $seo["address_$l"] = $seo["address_$l"] ?? '';
    $seo["working_hours_$l"] = $seo["working_hours_$l"] ?? '';
    $seo["site_name_$l"] = $seo["site_name_$l"] ?? 'Kuplyu Tashkent';
}
$seo['phone'] = $seo['phone'] ?? '';
$seo['email'] = $seo['email'] ?? '';

$global = [
    'phone' => $seoSettings['phone'] ?? '',
    'email' => $seoSettings['email'] ?? '',
    'address' => $seoSettings["address_$lang"] ?? '',
    'working_hours' => $seoSettings["working_hours_$lang"] ?? '',
    'site_name' => $seoSettings["site_name_$lang"] ?? 'Kuplyu Tashkent',
    'google_review_url' => $seoSettings['google_review_url'] ?? '',
];

$templateData = $templateData ?? [];
$templateData['global'] = $templateData['global'] ?? $global;
$templateData['seo']    = $templateData['seo']    ?? $seoSettings;
$templateData['lang']   = $templateData['lang']   ?? $lang;
$templateData['date']   = $templateData['date']   ?? [
    'year' => date('Y'),
    'month' => date('n'),
    'month_name' => date('F'),
    'day' => date('j'),
];
$templateData['rotation'] = $templateData['rotation'] ?? [
    'active' => false,
    'month' => null
];

// Ensure $page is defined for header.php
$page = $page ?? [
    'id' => 0,
    'slug' => 'error',
    'title_ru' => $errorTitles[$code]['ru'] ?? 'Ошибка',
    'title_uz' => $errorTitles[$code]['uz'] ?? 'Xato',
    'meta_title_ru' => null,
    'meta_title_uz' => null,
    'meta_description_ru' => null,
    'meta_description_uz' => null,
    'og_image' => null,
    'enable_rotation' => 0,
    'canonical_url' => BASE_URL . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/error')
];

$date = $templateData['date'];

$metaRobots = 'noindex, nofollow';
$errorTitles = [
    400 => ['ru' => 'Неверный запрос',           'uz' => "Noto‘g‘ri so‘rov"],
    401 => ['ru' => 'Требуется авторизация',     'uz' => 'Avtorizatsiya talab qilinadi'],
    403 => ['ru' => 'Доступ запрещён',           'uz' => "Kirish taqiqlangan"],
    404 => ['ru' => 'Страница не найдена',       'uz' => 'Sahifa topilmadi'],
    500 => ['ru' => 'Внутренняя ошибка сервера', 'uz' => 'Serverda ichki xato'],
];

$errorMessages = [
    400 => ['ru' => 'Ваш запрос не может быть обработан.',         'uz' => "So‘rovingizni qayta ishlash imkoni yo‘q."],
    401 => ['ru' => 'Пожалуйста, авторизуйтесь для доступа.',      'uz' => "Kirish uchun avtorizatsiyadan o‘ting."],
    403 => ['ru' => 'У вас нет прав для доступа к этой странице.', 'uz' => "Ushbu sahifaga kirish huquqingiz yo‘q."],
    404 => ['ru' => 'Страница, которую вы ищете, не найдена.',     'uz' => "Siz izlayotgan sahifa topilmadi."],
    500 => ['ru' => 'Произошла ошибка на сервере.',               'uz' => "Serverda xatolik yuz berdi."],
];

$title   = $errorTitles[$code][$lang]   ?? $errorTitles[500][$lang];
$message = $errorMessages[$code][$lang] ?? $errorMessages[500][$lang];

$homeUrl = BASE_URL . '/';
$tgUrl   = 'https://t.me/n0_odle';
$tgText  = ($lang === 'ru') ? 'Написать в Telegram' : 'Telegramda yozish';
?>

<?php require BASE_PATH . '/views/templates/header.php'; ?>

<main class="error-page">
    <div class="container">
        <section class="error-card" aria-labelledby="error-title">
            <div class="error-badge" aria-hidden="true">
                <span class="error-code"><?= (int)$code ?></span>
            </div>

            <h1 id="error-title" class="error-title"><?= htmlspecialchars($title) ?></h1>
            <p class="error-message"><?= htmlspecialchars($message) ?></p>

            <div class="error-actions">
                <a class="btn-primary" href="<?= htmlspecialchars($homeUrl) ?>">
                    <?= $lang === 'ru' ? 'На главную' : 'Bosh sahifa' ?>
                </a>

                <a class="btn-secondary"
                   href="<?= htmlspecialchars($tgUrl) ?>"
                   target="_blank"
                   rel="noopener noreferrer">
                    <?= htmlspecialchars($tgText) ?>
                </a>
            </div>

            <div class="error-meta">
                <div class="error-meta-row">
                    <span class="error-meta-label"><?= $lang === 'ru' ? 'Код ошибки:' : 'Xato kodi:' ?></span>
                    <span class="error-meta-value"><?= (int)$code ?></span>
                </div>

                <div class="error-meta-row">
                    <span class="error-meta-label"><?= $lang === 'ru' ? 'URL:' : 'Manzil:' ?></span>
                    <span class="error-meta-value"><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?></span>
                </div>
            </div>

            <?php if ($code === 404): ?>
                <div class="error-hint">
                    <?= $lang === 'ru'
                        ? 'Проверьте адрес или вернитесь на главную. Если вы перешли по ссылке с сайта — сообщите нам, мы исправим.'
                        : "Manzilni tekshiring yoki bosh sahifaga qayting. Agar saytdagi havola orqali kelsangiz — bizga yozing, tuzatamiz." ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php require BASE_PATH . '/views/templates/footer.php'; ?>
