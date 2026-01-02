<?php
// path: ./views/error.php

$lang = $lang ?? 'ru';
$code = intval($_GET['code'] ?? 500);

http_response_code($code);

// Error titles and messages
$errorTitles = [
    400 => ['ru'=>'Неверный запрос','uz'=>"Noto‘g‘ri so‘rov"],
    401 => ['ru'=>'Требуется авторизация','uz'=>'Avtorizatsiya talab qilinadi'],
    403 => ['ru'=>'Доступ запрещён','uz'=>"Kirish taqiqlangan"],
    404 => ['ru'=>'Страница не найдена','uz'=>'Sahifa topilmadi'],
    500 => ['ru'=>'Внутренняя ошибка сервера','uz'=>'Serverda ichki xato']
];

$errorMessages = [
    400 => ['ru'=>'Ваш запрос не может быть обработан.','uz'=>"Sizning so‘rovingiz qayta ishlanmaydi."],
    401 => ['ru'=>'Пожалуйста, авторизуйтесь для доступа.','uz'=>'Kirish uchun avtorizatsiya qilishingiz kerak.'],
    403 => ['ru'=>'У вас нет прав для доступа к этой странице.','uz'=>'Sizda ushbu sahifaga kirish huquqi yo‘q.'],
    404 => ['ru'=>'Страница, которую вы ищете, не найдена.','uz'=>'Siz izlayotgan sahifa topilmadi.'],
    500 => ['ru'=>'Произошла ошибка на сервере.','uz'=>'Serverda xato yuz berdi.']
];

$title = $errorTitles[$code][$lang] ?? $errorTitles[500][$lang];
$message = $errorMessages[$code][$lang] ?? $errorMessages[500][$lang];

$siteName = 'Kuplyu Tashkent';
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars($siteName) ?></title>
    <link rel="icon" href="<?= BASE_URL ?>/css/favicon.ico">
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/pages.css">
    <style>
        body { font-family: sans-serif; margin:0; padding:0; background:#f9f9f9; color:#333; display:flex; flex-direction:column; min-height:100vh; }
        .container { max-width: 900px; margin: auto; text-align: center; padding: 80px 20px; flex:1; }
        h1 { font-size: 2em; margin-bottom: 20px; }
        p { font-size: 1.1em; color: #555; margin-bottom: 30px; }
        a.button { display: inline-block; padding: 12px 24px; background: #dc3545; color: #fff; border-radius:6px; text-decoration:none; font-weight:500; transition: all 0.2s; }
        a.button:hover { background: #c82333; }
        footer { text-align:center; padding:20px 0; background:#f1f1f1; font-size:0.9em; color:#666; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($message) ?></p>
        <a class="button" href="<?= BASE_URL ?>"><?= $lang === 'ru' ? 'Вернуться на главную' : "Bosh sahifaga qaytish" ?></a>
    </div>
    <footer>
        &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. <?= $lang === 'ru' ? 'Все права защищены.' : "Barcha huquqlar himoyalangan." ?>
    </footer>
</body>
</html>
