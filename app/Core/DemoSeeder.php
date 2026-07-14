<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Демо-наполнение сайта: новости, документы, вакансии, тендеры, руководство,
 * типовые госстраницы (О организации, Руководство, Структура, Антикоррупция) и
 * пункты меню. Идемпотентно — записи создаются только если их ещё нет (по
 * slug/url). Вызывается и из CLI (database/seed_demo.php), и из админки.
 */
final class DemoSeeder
{
    /** @return array<string,int> счётчики добавленного по разделам */
    public static function run(PDO $pdo): array
    {
        $c = ['assets' => 0, 'home' => 0, 'news' => 0, 'documenty' => 0, 'vakansii' => 0, 'tendery' => 0, 'team' => 0, 'pages' => 0, 'menu' => 0];

        self::seedAssets($pdo, $c);
        self::seedNews($pdo, $c);
        self::seedEntries($pdo, $c);
        self::seedTeam($pdo, $c);
        self::seedHome($pdo, $c);
        self::seedPages($pdo, $c);
        self::seedMenu($pdo, $c);

        return $c;
    }

    /** Абсолютный путь каталога публичных загрузок. */
    private static function uploadsDir(): string
    {
        $dir = (string) Config::get('paths.public_uploads', '');
        return $dir !== '' ? rtrim($dir, '/') : \dirname(__DIR__, 2) . '/public/uploads/public';
    }

    /**
     * Копирует демо-изображения из database/demo_assets в каталог публичных
     * загрузок и регистрирует их в медиабиблиотеке (таблица files). Нужно,
     * чтобы демо-главная и карточки показывали реальные картинки после чистой
     * установки (сами загрузки в репозиторий не входят).
     */
    private static function seedAssets(PDO $pdo, array &$c): void
    {
        $src = \dirname(__DIR__, 2) . '/database/demo_assets';
        $dest = self::uploadsDir();
        if (!is_dir($src)) {
            return;
        }
        if (!is_dir($dest)) {
            @mkdir($dest, 0775, true);
        }

        $hasFiles = (bool) $pdo->query("SHOW TABLES LIKE 'files'")->fetchColumn();
        $fileIns = $hasFiles ? $pdo->prepare(
            "INSERT INTO files (original_name, stored_name, mime_type, size, access_type, uploaded_by, created_at)
             SELECT :n, :s, 'image/jpeg', :sz, 'public', NULL, NOW()
             FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM files WHERE stored_name = :s2)"
        ) : null;

        foreach (glob($src . '/*.jpg') ?: [] as $file) {
            $name = basename($file);
            $target = $dest . '/' . $name;
            if (!is_file($target)) {
                @copy($file, $target);
                $c['assets']++;
            }
            if ($fileIns !== null) {
                $fileIns->execute([':n' => $name, ':s' => $name, ':sz' => (int) @filesize($file), ':s2' => $name]);
            }
        }
    }

    /**
     * Демо-главная по эскизу: hero, счётчики, направления, проекты, новости и
     * медиа. Блоки берутся из фикстуры database/demo_assets/home_blocks.json.
     * Идемпотентно: страница создаётся при отсутствии, блоки — только если
     * главная ещё пуста.
     */
    private static function seedHome(PDO $pdo, array &$c): void
    {
        $fixture = \dirname(__DIR__, 2) . '/database/demo_assets/home_blocks.json';
        if (!is_file($fixture)) {
            return;
        }
        $blocks = json_decode((string) file_get_contents($fixture), true);
        if (!is_array($blocks) || $blocks === []) {
            return;
        }

        // Есть ли уже главная страница сайта?
        $homeId = $pdo->query('SELECT id FROM pages WHERE is_home = 1 LIMIT 1')->fetchColumn();
        if ($homeId === false) {
            // Не переносим главную у существующего сайта — создаём, только если её нет.
            $pdo->prepare(
                "INSERT INTO pages (title, slug, status, is_home, layout_type, transparent_header, created_at)
                 SELECT 'Главная', 'home', 'published', 1, 'no_sidebar', 1, NOW()
                 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM pages WHERE slug = 'home')"
            )->execute();
            $homeId = $pdo->query('SELECT id FROM pages WHERE is_home = 1 LIMIT 1')->fetchColumn();
            if ($homeId === false) {
                $homeId = $pdo->query("SELECT id FROM pages WHERE slug = 'home' LIMIT 1")->fetchColumn();
            }
            $c['pages'] += $homeId !== false ? 1 : 0;
        }
        if ($homeId === false) {
            return;
        }
        $homeId = (int) $homeId;

        // Наполняем демо-главную, только если она пуста или содержит нетронутую
        // «стартовую» вёрстку из schema.sql (cta+columns+news_latest). Если
        // редактор уже менял главную — не трогаем, чтобы не стереть работу.
        $count = (int) $pdo->query('SELECT COUNT(*) FROM blocks WHERE page_id = ' . $homeId)->fetchColumn();
        if ($count > 0) {
            $topTypes = $pdo->query('SELECT type FROM blocks WHERE page_id = ' . $homeId . ' AND parent_block_id IS NULL ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN);
            if ($topTypes !== ['cta', 'columns', 'news_latest']) {
                return;
            }
            $pdo->exec('DELETE FROM blocks WHERE page_id = ' . $homeId);
        }

        // Демо-главной нужна прозрачная шапка поверх hero.
        $pdo->prepare('UPDATE pages SET transparent_header = 1, layout_type = ? WHERE id = ?')
            ->execute(['no_sidebar', $homeId]);

        $lang = self::defaultLang($pdo);
        $ins = $pdo->prepare(
            'INSERT INTO blocks (page_id, lang, type, title, data, sort_order, is_active, created_at)
             VALUES (:pid, :lang, :ty, :ti, :d, :so, 1, NOW())'
        );
        $order = 1;
        foreach ($blocks as $b) {
            if (!isset($b['type'])) {
                continue;
            }
            $ins->execute([
                ':pid' => $homeId,
                ':lang' => $lang,
                ':ty' => (string) $b['type'],
                ':ti' => (string) ($b['title'] ?? ''),
                ':d' => json_encode($b['data'] ?? [], JSON_UNESCAPED_UNICODE),
                ':so' => $order++,
            ]);
        }
        $c['home'] = $order - 1;
    }

    private static function seedNews(PDO $pdo, array &$c): void
    {
        $news = [
            ['Запуск обновлённого официального портала', 'zapusk-portala', 'Представлен новый сайт организации с современным дизайном, удобной навигацией и версией для слабовидящих.'],
            ['График приёма граждан на квартал', 'grafik-priema', 'Опубликовано расписание личного приёма граждан руководством организации.'],
            ['Итоги деятельности за год', 'itogi-goda', 'Подведены основные итоги работы и ключевые показатели за отчётный период.'],
            ['Расширен перечень электронных услуг', 'elektronnye-uslugi', 'Теперь больше документов можно получить онлайн без личного визита.'],
            ['Объявлен новый набор специалистов', 'nabor-specialistov', 'Открыты вакансии в нескольких подразделениях. Подробности — в разделе «Вакансии».'],
        ];
        // Обложки берём из демо-изображений (регистрируются в seedAssets).
        $covers = ['/uploads/public/hero-demo.jpg', '/uploads/public/hero-demo-g2.jpg', '/uploads/public/hero-demo-g3.jpg', '/uploads/public/hero-demo-g4.jpg'];
        $ins = $pdo->prepare(
            "INSERT INTO news (title, slug, excerpt, content, image, status, published_at, created_at)
             SELECT :t, :s, :e, :co, :img, 'published', NOW() - INTERVAL :d DAY, NOW()
             FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM news WHERE slug = :s2)"
        );
        foreach ($news as $i => $n) {
            $ins->execute([':t' => $n[0], ':s' => $n[1], ':e' => $n[2], ':co' => '<p>' . $n[2] . '</p><p>Полный текст материала.</p>', ':img' => $covers[$i % count($covers)], ':d' => $i * 2, ':s2' => $n[1]]);
            $c['news'] += $ins->rowCount();
        }
    }

    private static function seedEntries(PDO $pdo, array &$c): void
    {
        $ins = $pdo->prepare(
            "INSERT INTO content_entries (type_id, title, slug, status, data, created_at)
             SELECT :tid, :t, :s, 'published', :d, NOW()
             FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM content_entries WHERE type_id = :tid2 AND slug = :s2)"
        );
        $byType = [
            'documenty' => [
                ['Приказ №112 об утверждении регламента', 'prikaz-112', ['doc_number' => '112', 'doc_date' => '2026-05-14', 'category' => 'Приказы', 'summary' => 'Об утверждении регламента предоставления государственных услуг.']],
                ['Постановление №34 о мерах поддержки', 'postanovlenie-34', ['doc_number' => '34', 'doc_date' => '2026-04-02', 'category' => 'Постановления', 'summary' => 'О мерах по улучшению качества обслуживания граждан.']],
                ['Приказ №118 о структуре организации', 'prikaz-118', ['doc_number' => '118', 'doc_date' => '2026-05-28', 'category' => 'Приказы', 'summary' => 'Об утверждении организационной структуры.']],
                ['Регламент рассмотрения обращений', 'reglament-obrashcheniy', ['doc_number' => '7-Р', 'doc_date' => '2026-03-10', 'category' => 'Регламенты', 'summary' => 'Порядок и сроки рассмотрения обращений граждан.']],
                ['Отчёт о деятельности за год', 'otchet-god', ['doc_number' => 'ОТ-2026', 'doc_date' => '2026-01-20', 'category' => 'Отчёты', 'summary' => 'Годовой отчёт о результатах деятельности.']],
                ['Положение об антикоррупционной политике', 'polozhenie-antikorrupciya', ['doc_number' => '5-П', 'doc_date' => '2026-02-15', 'category' => 'Положения', 'summary' => 'Основные принципы противодействия коррупции.']],
            ],
            'vakansii' => [
                ['Ведущий специалист отдела ИТ', 'vedushchiy-it', ['department' => 'Отдел информационных технологий', 'salary' => 'по договорённости', 'deadline' => '2026-08-31', 'requirements' => 'Высшее образование, опыт от 3 лет, знание PHP/MySQL.', 'duties' => 'Сопровождение и развитие информационных систем.']],
                ['Юрисконсульт', 'yuriskonsult', ['department' => 'Юридический отдел', 'salary' => 'от 8 000 000 сум', 'deadline' => '2026-08-20', 'requirements' => 'Высшее юридическое образование, опыт от 2 лет.', 'duties' => 'Правовое сопровождение деятельности организации.']],
                ['Специалист по кадрам', 'specialist-kadry', ['department' => 'Отдел кадров', 'salary' => 'от 6 000 000 сум', 'deadline' => '2026-09-10', 'requirements' => 'Опыт кадрового делопроизводства.', 'duties' => 'Ведение кадрового учёта и документации.']],
                ['Пресс-секретарь', 'press-sekretar', ['department' => 'Пресс-служба', 'salary' => 'по итогам собеседования', 'deadline' => '2026-08-05', 'requirements' => 'Опыт в СМИ или PR, грамотная речь.', 'duties' => 'Взаимодействие со СМИ, ведение новостей сайта.']],
            ],
            'tendery' => [
                ['Поставка компьютерной техники', 'postavka-tekhniki', ['tender_number' => 'T-2026-014', 'budget' => '350 000 000 сум', 'start_date' => '2026-06-01', 'deadline' => '2026-07-15', 'summary' => 'Закупка рабочих станций и периферии.']],
                ['Ремонт административного здания', 'remont-zdaniya', ['tender_number' => 'T-2026-019', 'budget' => '1 200 000 000 сум', 'start_date' => '2026-06-10', 'deadline' => '2026-08-01', 'summary' => 'Капитальный ремонт помещений.']],
                ['Услуги охраны объектов', 'uslugi-ohrany', ['tender_number' => 'T-2026-021', 'budget' => '480 000 000 сум', 'start_date' => '2026-06-20', 'deadline' => '2026-07-30', 'summary' => 'Физическая охрана административных объектов.']],
                ['Разработка мобильного приложения', 'razrabotka-prilozheniya', ['tender_number' => 'T-2026-025', 'budget' => '600 000 000 сум', 'start_date' => '2026-07-01', 'deadline' => '2026-08-20', 'summary' => 'Создание мобильного приложения для граждан.']],
            ],
        ];
        foreach ($byType as $slug => $rows) {
            $tid = self::typeId($pdo, $slug);
            if ($tid === null) {
                continue;
            }
            foreach ($rows as $r) {
                $ins->execute([':tid' => $tid, ':t' => $r[0], ':s' => $r[1], ':d' => json_encode($r[2], JSON_UNESCAPED_UNICODE), ':tid2' => $tid, ':s2' => $r[1]]);
                $c[$slug] += $ins->rowCount();
            }
        }
    }

    private static function seedTeam(PDO $pdo, array &$c): void
    {
        if (!(bool) $pdo->query("SHOW TABLES LIKE 'team_members'")->fetchColumn()) {
            return;
        }
        $team = [
            ['Ахмедов Рустам Каримович', 'Директор'],
            ['Юлдашева Нилуфар Азизовна', 'Заместитель директора'],
            ['Каримов Бехзод Шухратович', 'Начальник юридического отдела'],
            ['Исмоилова Дилноза Фарходовна', 'Руководитель пресс-службы'],
        ];
        $ins = $pdo->prepare(
            "INSERT INTO team_members (name, position, status, sort_order, created_at)
             SELECT :n, :p, 'published', :o, NOW()
             FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM team_members WHERE name = :n2)"
        );
        foreach ($team as $i => $t) {
            $ins->execute([':n' => $t[0], ':p' => $t[1], ':o' => $i, ':n2' => $t[0]]);
            $c['team'] += $ins->rowCount();
        }
    }

    private static function seedPages(PDO $pdo, array &$c): void
    {
        // Принудительно пересоздаем страницу "о нас", чтобы обновить законодательную основу
        $pdo->exec("DELETE FROM blocks WHERE page_id IN (SELECT id FROM pages WHERE slug = 'o-nas')");
        $pdo->exec("DELETE FROM pages WHERE slug = 'o-nas'");

        // [slug, title, [blocks: [type, title, data]]]
        $pages = [
            ['o-nas', 'Об организации', [
                ['text', 'О нас', [
                    'title' => 'Правовой статус и законодательная основа деятельности',
                    'content' => '<h3>Законодательная основа деятельности</h3><p>Деятельность Агентства осуществляется в строгом соответствии с Конституцией Республики Узбекистан, законами Республики Узбекистан, указами, постановлениями и распоряжениями Президента Республики Узбекистан, постановлениями и распоряжениями Кабинета Министров Республики Узбекистан, а также Положением об Агентстве.</p><div class="gov-card" style="background: var(--gov-bg-alt, #f8f9fa); padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid var(--gov-teal, #008080);"><h4 style="margin-top:0; color: var(--gov-teal-text, #005f5f);">Ключевые нормативно-правовые акты:</h4><ul style="padding-left: 20px; margin-bottom: 0;"><li><strong>Конституция Республики Узбекистан</strong> — высшая правовая основа государственной власти.</li><li><strong>Закон РУз «Об открытости деятельности органов государственной власти и управления»</strong> — гарантирует доступ граждан и организаций к информации о нашей работе.</li><li><strong>Закон РУз «О государственной гражданской службе»</strong> — регулирует вопросы прохождения службы и правового статуса сотрудников.</li><li><strong>Закон РУз «О противодействии коррупции»</strong> — правовая основа обеспечения прозрачности и антикоррупционных мер.</li><li><strong>Закон РУз «Об обращениях физических и юридических лиц»</strong> — регламентирует порядок работы с обращениями граждан.</li></ul></div><h3>Основные задачи и функции Агентства</h3><p>В рамках реализации возложенных полномочий Агентство осуществляет следующие ключевые функции:</p><ul><li><strong>Реализация политики:</strong> Участие в разработке и практическом воплощении единой государственной политики в закрепленной сфере.</li><li><strong>Нормотворчество:</strong> Разработка предложений по совершенствованию нормативно-правовой базы, подготовка проектов законов и нормативных актов.</li><li><strong>Государственные услуги:</strong> Оказание интерактивных и прямых госуслуг населению и бизнесу на принципах прозрачности и оперативности.</li><li><strong>Контроль и регулирование:</strong> Мониторинг соблюдения законодательства в установленной сфере, ведение государственных реестров и систем учета.</li></ul><h3>Регламент и принципы работы</h3><p>Наша деятельность строится на принципах законности, открытости, приоритета прав и законных интересов человека. Все внутренние процессы регламентированы стандартами делопроизводства и Кодексом этики государственных служащих, утвержденным Кабинетом Министров Республики Узбекистан.</p>'
                ]],
            ]],
            ['rukovodstvo', 'Руководство', [
                ['text', 'Введение', ['title' => 'Руководство', 'content' => '<p>Руководящий состав организации.</p>']],
                ['team_list', 'Команда', ['title' => 'Руководящий состав', 'limit' => 0]],
            ]],
            ['struktura', 'Структура', [
                ['text', 'Структура', ['title' => 'Организационная структура', 'content' => '<p>Организация включает профильные подразделения: юридический отдел, отдел информационных технологий, отдел кадров, пресс-службу и другие структурные единицы.</p>']],
            ]],
            ['antikorrupciya', 'Противодействие коррупции', [
                ['text', 'Антикоррупция', ['title' => 'Противодействие коррупции', 'content' => '<p>Организация проводит последовательную антикоррупционную политику. Ознакомиться с нормативными документами можно в разделе «Документы».</p><p>Сообщить о фактах коррупции можно через форму обратной связи.</p>']],
            ]],
        ];
        $pageIns = $pdo->prepare(
            "INSERT INTO pages (title, slug, status, is_home, layout_type, created_at)
             SELECT :t, :s, 'published', 0, 'no_sidebar', NOW()
             FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM pages WHERE slug = :s2)"
        );
        $blockIns = $pdo->prepare(
            'INSERT INTO blocks (page_id, lang, type, title, data, sort_order, is_active, created_at)
             VALUES (:pid, :lang, :ty, :ti, :d, :so, 1, NOW())'
        );
        $lang = self::defaultLang($pdo);
        foreach ($pages as [$slug, $title, $blocks]) {
            $pageIns->execute([':t' => $title, ':s' => $slug, ':s2' => $slug]);
            $c['pages'] += $pageIns->rowCount();
            $pid = self::pageId($pdo, $slug);
            if ($pid === null) {
                continue;
            }
            // Блоки добавляем только если страница пустая (не дублируем).
            $hasBlocks = (int) $pdo->query('SELECT COUNT(*) FROM blocks WHERE page_id = ' . $pid)->fetchColumn() > 0;
            if ($hasBlocks) {
                continue;
            }
            $order = 1;
            foreach ($blocks as [$type, $btitle, $data]) {
                $blockIns->execute([':pid' => $pid, ':lang' => $lang, ':ty' => $type, ':ti' => $btitle, ':d' => json_encode($data, JSON_UNESCAPED_UNICODE), ':so' => $order++]);
            }
        }
    }

    private static function seedMenu(PDO $pdo, array &$c): void
    {
        if (!(bool) $pdo->query("SHOW TABLES LIKE 'menu_items'")->fetchColumn()) {
            return;
        }
        $items = [
            ['Об организации', '/o-nas'],
            ['Руководство', '/rukovodstvo'],
            ['Структура', '/struktura'],
            ['Новости', '/news'],
            ['Документы', '/catalog/documenty'],
            ['Вакансии', '/catalog/vakansii'],
            ['Тендеры', '/catalog/tendery'],
            ['Противодействие коррупции', '/antikorrupciya'],
        ];
        $ins = $pdo->prepare(
            "INSERT INTO menu_items (lang, title, url_type, url_value, sort_order, is_active, created_at)
             SELECT '', :t, 'custom', :u, :o, 1, NOW()
             FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM menu_items WHERE url_value = :u2)"
        );
        foreach ($items as $i => $it) {
            $ins->execute([':t' => $it[0], ':u' => $it[1], ':o' => $i, ':u2' => $it[1]]);
            $c['menu'] += $ins->rowCount();
        }
    }

    private static function typeId(PDO $pdo, string $slug): ?int
    {
        $stmt = $pdo->prepare('SELECT id FROM content_types WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private static function pageId(PDO $pdo, string $slug): ?int
    {
        $stmt = $pdo->prepare('SELECT id FROM pages WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private static function defaultLang(PDO $pdo): string
    {
        try {
            $code = $pdo->query('SELECT code FROM languages WHERE is_default = 1 LIMIT 1')->fetchColumn();
            if ($code !== false && $code !== '') {
                return (string) $code;
            }
        } catch (\Throwable $e) {
            // таблица языков может отсутствовать в минимальной установке
        }

        return 'ru';
    }
}
