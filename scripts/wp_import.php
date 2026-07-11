<?php

declare(strict_types=1);

/**
 * Импорт новостей из старого сайта на WordPress (REST API) с фотографиями.
 *
 * Запуск на сервере, где доступен старый сайт:
 *   php scripts/wp_import.php https://asdr.gov.uz [опции]
 *
 * Опции:
 *   --limit N        импортировать не более N новостей (0 = все)
 *   --status STATE   draft (по умолчанию) | published
 *   --author ID      id пользователя-автора (по умолчанию первый админ)
 *   --lang WP:ART    язык (повторяемо): код языка WordPress → код языка ArtStudio.
 *                    Первый = основной, остальные пишутся переводами. Напр.:
 *                    --lang uz:uz --lang ru:ru   (двуязычный сайт)
 *   --dry-run        только показать, сколько будет импортировано, без записи
 *
 * По умолчанию новости создаются ЧЕРНОВИКАМИ — просмотрите и опубликуйте в
 * админке (Новости). Повторный запуск не создаёт дубли (пропуск по slug).
 */

require __DIR__ . '/../app/Core/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\WordPressImporter;

$args = array_slice($argv, 1);
$base = '';
$opts = ['status' => 'draft', 'limit' => 0, 'dryRun' => false, 'authorId' => null, 'langs' => []];

for ($i = 0; $i < count($args); $i++) {
    $a = $args[$i];
    if ($a === '--limit' && isset($args[$i + 1])) {
        $opts['limit'] = (int) $args[++$i];
    } elseif ($a === '--status' && isset($args[$i + 1])) {
        $opts['status'] = $args[++$i];
    } elseif ($a === '--author' && isset($args[$i + 1])) {
        $opts['authorId'] = (int) $args[++$i];
    } elseif ($a === '--lang' && isset($args[$i + 1])) {
        [$wp, $art] = array_pad(explode(':', $args[++$i], 2), 2, '');
        if ($wp !== '' && $art !== '') {
            $opts['langs'][$wp] = $art;
        }
    } elseif ($a === '--dry-run') {
        $opts['dryRun'] = true;
    } elseif (str_starts_with($a, 'http')) {
        $base = rtrim($a, '/');
    }
}

if ($base === '') {
    fwrite(STDERR, "Укажите адрес старого сайта, напр.:\n  php scripts/wp_import.php https://asdr.gov.uz --limit 20\n");
    exit(2);
}

// Инициализация БД из конфигурации приложения.
Database::init((array) Config::get('database'));

// Автор по умолчанию — первый администратор.
if ($opts['authorId'] === null) {
    try {
        $opts['authorId'] = (int) (Database::pdo()->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 0) ?: null;
    } catch (\Throwable) {
    }
}

echo "Импорт из {$base} (статус: {$opts['status']}" . ($opts['dryRun'] ? ', dry-run' : '') . ")…\n";
$r = WordPressImporter::importAll($base, $opts);

echo "\n────────────────────────────────────────\n";
echo "Импортировано новостей: {$r['imported']}\n";
echo "Пропущено (уже есть):   {$r['skipped']}\n";
echo "Переводов добавлено:    {$r['translations']}\n";
echo "Перенесено картинок:    {$r['images']}\n";
echo "Создано редиректов:     {$r['redirects']}\n";
if (!empty($r['errors'])) {
    echo "Ошибки (" . count($r['errors']) . "):\n";
    foreach (array_slice($r['errors'], 0, 20) as $e) {
        echo "  • {$e}\n";
    }
}
echo "\nГотово. Черновики — в админке: Новости. Не забудьте сбросить кэш.\n";

exit(empty($r['errors']) ? 0 : 1);
