<?php

declare(strict_types=1);

require __DIR__ . '/../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;

$fileId = filter_input(INPUT_GET, 'file_id', FILTER_VALIDATE_INT);
$token = $_GET['token'] ?? null;

if (!$fileId) {
    http_response_code(400);
    exit('Некорректный запрос.');
}

$stmt = Database::pdo()->prepare('SELECT * FROM files WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $fileId]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('Файл не найден.');
}

$authorized = false;

if ($file['access_type'] === 'public') {
    $authorized = true;
} elseif (Auth::check()) {
    // Любой авторизованный пользователь панели управления имеет доступ.
    $authorized = true;
} elseif (is_string($token) && $token !== '' && !empty($file['access_token']) && hash_equals((string) $file['access_token'], $token)) {
    $authorized = true;
}

if (!$authorized) {
    http_response_code(403);
    exit('Доступ запрещён.');
}

$basePath = $file['access_type'] === 'protected'
    ? Config::get('paths.protected_uploads')
    : Config::get('paths.public_uploads');

$expectedBase = realpath($basePath);
$fullPath = $expectedBase !== false ? realpath($expectedBase . '/' . $file['stored_name']) : false;

if ($fullPath === false || $expectedBase === false || !str_starts_with($fullPath, $expectedBase)) {
    http_response_code(404);
    exit('Файл не найден.');
}

if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Файл не найден.');
}

$update = Database::pdo()->prepare('UPDATE files SET download_count = download_count + 1 WHERE id = :id');
$update->execute([':id' => $fileId]);

header('Content-Type: ' . ($file['mime_type'] !== '' ? $file['mime_type'] : 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
header('Content-Length: ' . (string) filesize($fullPath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($fullPath);
exit;
