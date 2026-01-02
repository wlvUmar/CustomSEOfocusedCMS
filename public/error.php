<?php
// error.php
require_once __DIR__ . '/../config/init.php';

require 'views/templates/header.php';

$code = $_GET['code'] ?? 500;
$code = intval($code);

// Default messages
$errorMessages = [
    400 => ['ru' => 'Неверный запрос', 'uz' => 'Noto‘g‘ri so‘rov'],
    401 => ['ru' => 'Требуется авторизация', 'uz' => 'Avtorizatsiya talab qilinadi'],
    403 => ['ru' => 'Доступ запрещён', 'uz' => 'Kirish taqiqlangan'],
    404 => ['ru' => 'Страница не найдена', 'uz' => 'Sahifa topilmadi'],
    500 => ['ru' => 'Внутренняя ошибка сервера', 'uz' => 'Serverda ichki xato']
];

$title = $errorMessages[$code][$lang] ?? $errorMessages[500][$lang];
$message = [
    400 => ['ru' => 'Ваш запрос не может быть обработан.', 'uz' => 'Sizning so‘rovingiz qayta ishlanmaydi.'],
    401 => ['ru' => 'Пожалуйста, авторизуйтесь для доступа.', 'uz' => 'Kirish uchun avtorizatsiya qilishingiz kerak.'],
    403 => ['ru' => 'У вас нет прав для доступа к этой странице.', 'uz' => 'Sizda ushbu sahifaga kirish huquqi yo‘q.'],
    404 => ['ru' => 'Страница, которую вы ищете, не найдена.', 'uz' => 'Siz izlayotgan sahifa topilmadi.'],
    500 => ['ru' => 'Произошла ошибка на сервере.', 'uz' => 'Serverda xato yuz berdi.']
][$code] ?? $message[500];

http_response_code($code);
?>

<main class="page-hero">
    <div class="container" style="text-align:center; padding:80px 20px;">
        <h1><?= htmlspecialchars($title) ?></h1>
        <p style="margin:20px 0; font-size:1.1em; color: var(--text-muted);"><?= htmlspecialchars($message) ?></p>
        <a href="<?= BASE_URL ?>" 
           style="display:inline-block; padding:12px 24px; background: var(--primary-dark); color:#fff; border-radius:6px; text-decoration:none; font-weight:500; margin-top:20px;">
            <?= $lang === 'ru' ? 'Вернуться на главную' : 'Bosh sahifaga qaytish' ?>
        </a>
    </div>
</main>

<?php require 'views/templates/footer.php'; ?>
