<?php

declare(strict_types=1);

/*
 * Демо-наполнение разделов сайта (новости, документы, вакансии, тендеры,
 * команда). Идемпотентно: записи создаются только если их ещё нет (по slug).
 *
 *   php database/seed_demo.php
 *
 * Безопасно для повторного запуска. Демо-контент — обычные записи, их можно
 * редактировать и удалять в админке.
 */

require __DIR__ . '/../app/Core/Cli.php';
\App\Core\Cli::assertCli();

require __DIR__ . '/../app/Core/bootstrap.php';

use App\Core\Database;

$pdo = Database::pdo();
$created = ['news' => 0, 'documenty' => 0, 'vakansii' => 0, 'tendery' => 0, 'team' => 0];

// --- Новости ---
$news = [
    ['Запуск обновлённого официального портала', 'zapusk-portala', 'Представлен новый сайт организации с современным дизайном, удобной навигацией и версией для слабовидящих.'],
    ['График приёма граждан на квартал', 'grafik-priema', 'Опубликовано расписание личного приёма граждан руководством организации.'],
    ['Итоги деятельности за год', 'itogi-goda', 'Подведены основные итоги работы и ключевые показатели за отчётный период.'],
    ['Расширен перечень электронных услуг', 'elektronnye-uslugi', 'Теперь больше документов можно получить онлайн без личного визита.'],
    ['Объявлен новый набор специалистов', 'nabor-specialistov', 'Открыты вакансии в нескольких подразделениях. Подробности — в разделе «Вакансии».'],
];
$newsIns = $pdo->prepare(
    "INSERT INTO news (title, slug, excerpt, content, status, published_at, created_at)
     SELECT :t, :s, :e, :c, 'published', NOW() - INTERVAL :d DAY, NOW()
     FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM news WHERE slug = :s2)"
);
foreach ($news as $i => $n) {
    $newsIns->execute([':t' => $n[0], ':s' => $n[1], ':e' => $n[2], ':c' => '<p>' . $n[2] . '</p><p>Полный текст материала.</p>', ':d' => $i * 2, ':s2' => $n[1]]);
    $created['news'] += $newsIns->rowCount();
}

// --- Записи пользовательских типов ---
$typeId = static function (string $slug) use ($pdo): ?int {
    $stmt = $pdo->prepare('SELECT id FROM content_types WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $slug]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
};
$entryIns = $pdo->prepare(
    "INSERT INTO content_entries (type_id, title, slug, status, data, created_at)
     SELECT :tid, :t, :s, 'published', :d, NOW()
     FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM content_entries WHERE type_id = :tid2 AND slug = :s2)"
);
$seedEntries = static function (string $typeSlug, array $rows) use ($entryIns, $typeId, &$created): void {
    $tid = $typeId($typeSlug);
    if ($tid === null) {
        return;
    }
    foreach ($rows as $r) {
        $entryIns->execute([
            ':tid' => $tid, ':t' => $r['title'], ':s' => $r['slug'],
            ':d' => json_encode($r['data'], JSON_UNESCAPED_UNICODE),
            ':tid2' => $tid, ':s2' => $r['slug'],
        ]);
        $created[$typeSlug] += $entryIns->rowCount();
    }
};

$seedEntries('documenty', [
    ['title' => 'Приказ №112 об утверждении регламента', 'slug' => 'prikaz-112', 'data' => ['doc_number' => '112', 'doc_date' => '2026-05-14', 'category' => 'Приказы', 'summary' => 'Об утверждении регламента предоставления государственных услуг.']],
    ['title' => 'Постановление №34 о мерах поддержки', 'slug' => 'postanovlenie-34', 'data' => ['doc_number' => '34', 'doc_date' => '2026-04-02', 'category' => 'Постановления', 'summary' => 'О мерах по улучшению качества обслуживания граждан.']],
    ['title' => 'Приказ №118 о структуре организации', 'slug' => 'prikaz-118', 'data' => ['doc_number' => '118', 'doc_date' => '2026-05-28', 'category' => 'Приказы', 'summary' => 'Об утверждении организационной структуры.']],
    ['title' => 'Регламент рассмотрения обращений', 'slug' => 'reglament-obrashcheniy', 'data' => ['doc_number' => '7-Р', 'doc_date' => '2026-03-10', 'category' => 'Регламенты', 'summary' => 'Порядок и сроки рассмотрения обращений граждан.']],
    ['title' => 'Отчёт о деятельности за год', 'slug' => 'otchet-god', 'data' => ['doc_number' => 'ОТ-2026', 'doc_date' => '2026-01-20', 'category' => 'Отчёты', 'summary' => 'Годовой отчёт о результатах деятельности.']],
    ['title' => 'Положение об антикоррупционной политике', 'slug' => 'polozhenie-antikorrupciya', 'data' => ['doc_number' => '5-П', 'doc_date' => '2026-02-15', 'category' => 'Положения', 'summary' => 'Основные принципы противодействия коррупции.']],
]);

$seedEntries('vakansii', [
    ['title' => 'Ведущий специалист отдела ИТ', 'slug' => 'vedushchiy-it', 'data' => ['department' => 'Отдел информационных технологий', 'salary' => 'по договорённости', 'deadline' => '2026-08-31', 'requirements' => 'Высшее образование, опыт от 3 лет, знание PHP/MySQL.', 'duties' => 'Сопровождение и развитие информационных систем.']],
    ['title' => 'Юрисконсульт', 'slug' => 'yuriskonsult', 'data' => ['department' => 'Юридический отдел', 'salary' => 'от 8 000 000 сум', 'deadline' => '2026-08-20', 'requirements' => 'Высшее юридическое образование, опыт от 2 лет.', 'duties' => 'Правовое сопровождение деятельности организации.']],
    ['title' => 'Специалист по кадрам', 'slug' => 'specialist-kadry', 'data' => ['department' => 'Отдел кадров', 'salary' => 'от 6 000 000 сум', 'deadline' => '2026-09-10', 'requirements' => 'Опыт кадрового делопроизводства.', 'duties' => 'Ведение кадрового учёта и документации.']],
    ['title' => 'Пресс-секретарь', 'slug' => 'press-sekretar', 'data' => ['department' => 'Пресс-служба', 'salary' => 'по итогам собеседования', 'deadline' => '2026-08-05', 'requirements' => 'Опыт в СМИ или PR, грамотная речь.', 'duties' => 'Взаимодействие со СМИ, ведение новостей сайта.']],
]);

$seedEntries('tendery', [
    ['title' => 'Поставка компьютерной техники', 'slug' => 'postavka-tekhniki', 'data' => ['tender_number' => 'T-2026-014', 'budget' => '350 000 000 сум', 'start_date' => '2026-06-01', 'deadline' => '2026-07-15', 'summary' => 'Закупка рабочих станций и периферии.']],
    ['title' => 'Ремонт административного здания', 'slug' => 'remont-zdaniya', 'data' => ['tender_number' => 'T-2026-019', 'budget' => '1 200 000 000 сум', 'start_date' => '2026-06-10', 'deadline' => '2026-08-01', 'summary' => 'Капитальный ремонт помещений.']],
    ['title' => 'Услуги охраны объектов', 'slug' => 'uslugi-ohrany', 'data' => ['tender_number' => 'T-2026-021', 'budget' => '480 000 000 сум', 'start_date' => '2026-06-20', 'deadline' => '2026-07-30', 'summary' => 'Физическая охрана административных объектов.']],
    ['title' => 'Разработка мобильного приложения', 'slug' => 'razrabotka-prilozheniya', 'data' => ['tender_number' => 'T-2026-025', 'budget' => '600 000 000 сум', 'start_date' => '2026-07-01', 'deadline' => '2026-08-20', 'summary' => 'Создание мобильного приложения для граждан.']],
]);

// --- Команда (руководство) ---
$hasTeam = (bool) $pdo->query("SHOW TABLES LIKE 'team_members'")->fetchColumn();
if ($hasTeam) {
    $team = [
        ['Ахмедов Рустам Каримович', 'Директор'],
        ['Юлдашева Нилуфар Азизовна', 'Заместитель директора'],
        ['Каримов Бехзод Шухратович', 'Начальник юридического отдела'],
        ['Исмоилова Дилноза Фарходовна', 'Руководитель пресс-службы'],
    ];
    $teamIns = $pdo->prepare(
        "INSERT INTO team_members (name, position, status, sort_order, created_at)
         SELECT :n, :p, 'published', :o, NOW()
         FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM team_members WHERE name = :n2)"
    );
    foreach ($team as $i => $t) {
        $teamIns->execute([':n' => $t[0], ':p' => $t[1], ':o' => $i, ':n2' => $t[0]]);
        $created['team'] += $teamIns->rowCount();
    }
}

fwrite(STDOUT, "Демо-контент добавлен:\n");
foreach ($created as $section => $n) {
    fwrite(STDOUT, sprintf("  %-12s +%d\n", $section, $n));
}
fwrite(STDOUT, "Готово. Записи можно редактировать/удалять в админке.\n");
