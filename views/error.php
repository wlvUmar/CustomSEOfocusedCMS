<?php
require BASE_PATH . '/views/templates/error-header.php';

$code = intval($_GET['code'] ?? 500);

$errorMessages = [
    400 => ['ru'=>'Неверный запрос','uz'=>"Noto‘g‘ri so‘rov"],
    401 => ['ru'=>'Требуется авторизация','uz'=>'Avtorizatsiya talab qilinadi'],
    403 => ['ru'=>'Доступ запрещён','uz'=>"Kirish taqiqlangan"],
    404 => ['ru'=>'Страница не найдена','uz'=>'Sahifa topilmadi'],
    500 => ['ru'=>'Внутренняя ошибка сервера','uz'=>'Serverda ichki xato']
];

$messages = [
    400 => ['ru'=>'Ваш запрос не может быть обработан.','uz'=>"Sizning so‘rovingiz qayta ishlanmaydi."],
    401 => ['ru'=>'Пожалуйста, авторизуйтесь для доступа.','uz'=>'Kirish uchun avtorizatsiya qilishingiz kerak.'],
    403 => ['ru'=>'У вас нет прав для доступа к этой странице.','uz'=>'Sizda ushbu sahifaga kirish huquqi yo‘q.'],
    404 => ['ru'=>'Страница, которую вы ищете, не найдена.','uz'=>'Siz izlayotgan sahifa topilmadi.'],
    500 => ['ru'=>'Произошла ошибка на сервере.','uz'=>'Serverda xato yuz berdi.']
];

$title = $errorMessages[$code][$lang] ?? $errorMessages[500][$lang];
$message = $messages[$code][$lang] ?? $messages[500][$lang];

http_response_code($code);
?>

<h1><?= htmlspecialchars($title) ?></h1>
<p style="margin:20px 0; font-size:1.1em; color:#666;"><?= htmlspecialchars($message) ?></p>
<a class="button" href="<?= BASE_URL ?>"><?= $lang==='ru' ? 'Вернуться на главную' : 'Bosh sahifaga qaytish' ?></a>

<?php require BASE_PATH . '/views/templates/error-footer.php'; ?>
