<?php

use App\Core\Locale;

// 404-трекер: этот шаблон — единственная общая точка всех 404 (роутер,
// страницы, новости, каталог). Запись не мешает отдаче страницы.
if (class_exists(\App\Models\NotFoundLog::class) && \App\Core\Database::isConnected()) {
    \App\Models\NotFoundLog::record();
}

$lang = class_exists(Locale::class) ? Locale::current() : 'ru';
$messages = [
    'ru' => ['Страница не найдена', 'Запрашиваемая страница не существует или была удалена.', 'На главную'],
    'uz' => ['Sahifa topilmadi', 'Soʻralgan sahifa mavjud emas yoki oʻchirilgan.', 'Bosh sahifaga'],
    'en' => ['Page not found', 'The requested page does not exist or was removed.', 'Home'],
];
[$title, $text, $home] = $messages[$lang] ?? $messages['ru'];
$homeUrl = class_exists(Locale::class) ? Locale::url('/') : '/';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 — <?= htmlspecialchars($title, ENT_QUOTES) ?></title>
<style>
    body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; text-align: center; padding: 80px 20px; color: #1a1a1a; background: #f4f5f7; }
    h1 { font-size: 64px; margin: 0; color: #e63946; }
    p { color: #666; margin: 12px 0 24px; }
    a { color: #4361ee; text-decoration: none; }
</style>
</head>
<body>
<h1>404</h1>
<h2><?= htmlspecialchars($title, ENT_QUOTES) ?></h2>
<p><?= htmlspecialchars($text, ENT_QUOTES) ?></p>
<a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($home, ENT_QUOTES) ?></a>
</body>
</html>
