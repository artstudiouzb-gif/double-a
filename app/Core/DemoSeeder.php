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
        // Принудительно пересоздаем страницы, чтобы обновить законодательную основу и переводы
        $slugs = ['o-nas', 'rukovodstvo', 'struktura', 'antikorrupciya'];
        foreach ($slugs as $slug) {
            $pdo->exec("DELETE FROM blocks WHERE page_id IN (SELECT id FROM pages WHERE slug = '{$slug}')");
            $pdo->exec("DELETE FROM page_translations WHERE page_id IN (SELECT id FROM pages WHERE slug = '{$slug}')");
            $pdo->exec("DELETE FROM pages WHERE slug = '{$slug}'");
        }

        // Страницы с переводами для 'ru' и 'uz'
        $pages = [
            'o-nas' => [
                'ru' => [
                    'title' => 'Об организации',
                    'blocks' => [
                        ['text', 'О нас', [
                            'title' => 'Правовой статус и законодательная основа деятельности',
                            'content' => '<h3>Законодательная основа и правовой статус</h3><p>Деятельность Агентства стратегического планирования и развития осуществляется в строгом соответствии с Конституцией Республики Узбекистан, законами Республики Узбекистан, а также нормативно-правовыми актами Президента Республики Узбекистан, нацеленными на модернизацию системы государственного управления.</p><div class="gov-card" style="background: var(--gov-bg-alt, #f8f9fa); padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid var(--gov-teal, #008080);"><h4 style="margin-top:0; color: var(--gov-teal-text, #005f5f);">Ключевые нормативно-правовые акты Агентства:</h4><ul style="padding-left: 20px; margin-bottom: 0;"><li><strong>Указ Президента Республики Узбекистан № УП-201 от 30 октября 2025 года</strong> «О мерах по внедрению системы стратегического планирования и развития» — заложил основу для системного прогнозирования развития отраслей и регионов страны.</li><li><strong>Постановление Президента Республики Узбекистан № ПП-394 от 29 декабря 2025 года</strong> «О мерах по организации и эффективному налаживанию системы стратегического планирования и развития на основе новых подходов» — регламентирует структуру, организацию деятельности, полномочия и порядок взаимодействия Агентства с другими министерствами и ведомствами.</li><li><strong>Указ Президента Республики Узбекистан № УП-21 от 16 февраля 2026 года</strong> «О дополнительных мерах по последовательному продолжению реформ и выведению их на новый этап в рамках приоритетных направлений развития страны до 2030 года» — определяет ключевые KPI развития отраслей и координирующую роль Агентства в мониторинге реализации реформ до 2030 года.</li></ul></div><h3>Основные задачи и функции</h3><p>В соответствии с Указами Президента, на Агентство возложены следующие стратегические задачи:</p><ul><li><strong>Стратегическое планирование:</strong> Координация разработки и мониторинга реализации долгосрочных стратегий развития отраслей экономики и регионов.</li><li><strong>Оценка реформ (KPI до 2030 года):</strong> Разработка и внедрение системы ключевых показателей эффективности (KPI) для оценки хода реформ на основе Указа Президента № УП-21.</li><li><strong>Анализ и прогнозирование:</strong> Мониторинг макроэкономических показателей, выявление системных проблем и барьеров на пути реформ с подготовкой аналитических отчетов руководству страны.</li><li><strong>Методологическое руководство:</strong> Внедрение новых подходов и передовых международных стандартов стратегического планирования в деятельность органов исполнительной власти.</li></ul><h3>Регламент и прозрачность деятельности</h3><p>В соответствии с Законом РУз «Об открытости деятельности органов государственной власти и управления», Агентство обеспечивает полную прозрачность процессов разработки и мониторинга стратегий развития. Вся информация о ходе выполнения приоритетных направлений до 2030 года регулярно публикуется на нашем портале для ознакомления граждан, инвесторов и внутренних партнеров.</p>'
                        ]]
                    ]
                ],
                'uz' => [
                    'title' => 'Tashkilot haqida',
                    'blocks' => [
                        ['text', 'Tashkilot haqida', [
                            'title' => 'Huquqiy maqom va faoliyatning qonuniy asoslari',
                            'content' => '<h3>Qonunchilik asosi va huquqiy maqomi</h3><p>Strategik rejalashtirish va rivojlanish agentligi faoliyati O‘zbekiston Respublikasi Konstitutsiyasi, O‘zbekiston Respublikasi qonunlari, shuningdek, davlat boshqaruvi tizimini modernizatsiya qilishga qaratilgan O‘zbekiston Respublikasi Prezidentining normativ-huquqiy hujjatlariga qat’iy muvofiq ravishda amalga oshiriladi.</p><div class="gov-card" style="background: var(--gov-bg-alt, #f8f9fa); padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid var(--gov-teal, #008080);"><h4 style="margin-top:0; color: var(--gov-teal-text, #005f5f);">Agentlik faoliyatining asosiy normativ-huquqiy hujjatlari:</h4><ul style="padding-left: 20px; margin-bottom: 0;"><li><strong>O‘zbekiston Respublikasi Prezidentining 2025-yil 30-oktabrdagi PF-201-son Farmoni</strong> «Strategik rejalashtirish va rivojlanish tizimini joriy etish bo‘yicha tashkiliy chora-tadbirlar to‘g‘risida» — tarmoqlar va hududlarni rivojlantirishni tizimli prognozlashtirish asosi etib belgilandi.</li><li><strong>O‘zbekiston Respublikasi Prezidentining 2025-yil 29-dekabrdagi PQ-394-son qarori</strong> «Strategik rejalashtirish va rivojlanish tizimini yangicha yondashuvlar asosida tashkil etish va samarali yo‘lga qo‘yish chora-tadbirlari to‘g‘risida» — Agentlikning tuzilmasini, faoliyatini tashkil etishni, vakolatlari va boshqa vazirliklar hamda idoralar bilan hamkorlik qilish tartibini belgilaydi.</li><li><strong>O‘zbekiston Respublikasi Prezidentining 2026-yil 16-fevraldagi PF-21-son Farmoni</strong> «Mamlakat taraqqiyotining 2030-yilgacha mo‘ljallangan ustuvor yo‘nalishlari doirasida islohotlarni izchil davom ettirish va yangi bosqichga olib chiqishning qo‘shimcha chora-tadbirlari to‘g‘risida» — islohotlar ijrosini monitoring qilishda Agentlikning muvofiqlashtiruvchi rolini hamda 2030-yilgacha bo‘lgan asosiy KPI ko‘rsatkichlarini belgilaydi.</li></ul></div><h3>Asosiy vazifalar va funksiyalar</h3><p>Prezident Farmonlariga muvofiq, Agentlik zimmasiga quyidagi strategik vazifalar yuklatilgan:</p><ul><li><strong>Strategik rejalashtirish:</strong> Iqtisodiyot tarmoqlari va hududlarni rivojlantirishning uzoq muddatli strategiyalarini ishlab chiqish va amalga oshirilishini muvofiqlashtirish.</li><li><strong>Islohotlarni baholash (2030-yilgacha bo‘lgan KPI):</strong> PF-21-son Farmoni asosida islohotlar ijrosini baholash uchun samaradorlik ko‘rsatkichlari (KPI) tizimini ishlab chiqish va joriy etish.</li><li><strong>Tahlil va prognozlash:</strong> Makroiqtisodiy ko‘rsatkichlarni monitoring qilish, tizimli muammolar va to‘siqlarni aniqlash, tahliliy hisobotlarni davlat rahbariyatiga taqdim etish.</li><li><strong>Metodologik rahbarlik:</strong> Davlat ijro etuvchi hokimiyat organlari faoliyatiga strategik rejalashtirishning yangi yondashuvlari va ilg‘or xalqaro standartlarini joriy etish.</li></ul><h3>Faoliyat reglamenti va shaffoflik</h3><p>O‘zbekiston Respublikasining «Davlat hokimiyati va boshqaruvi organlari faoliyatining ochiqligi to‘g‘risida»gi Qonuniga muvofiq, Agentlik rivojlanish strategiyalarini ishlab chiqish va monitoring qilish jarayonlarining to‘liq shaffofligini ta’minlaydi. 2030-yilgacha bo‘lgan ustuvor yo‘nalishlar ijrosi to‘g‘risidagi ma’lumotlar fuqarolar, investorlar va xalqaro hamkorlar uchun muntazam ravishda portalimizda e’lon qilib boriladi.</p>'
                        ]]
                    ]
                ]
            ],
            'rukovodstvo' => [
                'ru' => [
                    'title' => 'Руководство',
                    'blocks' => [
                        ['text', 'Введение', ['title' => 'Руководство', 'content' => '<p>Руководящий состав организации.</p>']],
                        ['team_list', 'Команда', ['title' => 'Руководящий состав', 'limit' => 0]]
                    ]
                ],
                'uz' => [
                    'title' => 'Rahbariyat',
                    'blocks' => [
                        ['text', 'Kirish', ['title' => 'Rahbariyat', 'content' => '<p>Tashkilotning rahbariyat tarkibi.</p>']],
                        ['team_list', 'Jamoa', ['title' => 'Rahbariyat tarkibi', 'limit' => 0]]
                    ]
                ]
            ],
            'struktura' => [
                'ru' => [
                    'title' => 'Структура',
                    'blocks' => [
                        ['text', 'Структура', ['title' => 'Организационная структура', 'content' => '<p>Организация включает профильные подразделения: юридический отдел, отдел информационных технологий, отдел кадров, пресс-службу и другие структурные единицы.</p>']]
                    ]
                ],
                'uz' => [
                    'title' => 'Tuzilma',
                    'blocks' => [
                        ['text', 'Tuzilma', ['title' => 'Tashkiliy tuzilma', 'content' => '<p>Tashkilot tarkibiga quyidagi ixtisoslashtirilgan bo‘linmalar kiradi: yuridik bo‘lim, axborot texnologiyalari bo‘limi, kadrlar bo‘limi, matbuot xizmati va boshqa tarkibiy tuzilmalar.</p>']]
                    ]
                ]
            ],
            'antikorrupciya' => [
                'ru' => [
                    'title' => 'Противодействие коррупции',
                    'blocks' => [
                        ['text', 'Антикоррупция', ['title' => 'Противодействие коррупции', 'content' => '<p>Организация проводит последовательную антикоррупционную политику. Ознакомиться с нормативными документами можно в разделе «Документы».</p><p>Сообщить о фактах коррупции можно через форму обратной связи.</p>']]
                    ]
                ],
                'uz' => [
                    'title' => 'Korrupsiyaga qarshi kurash',
                    'blocks' => [
                        ['text', 'Korrupsiyaga qarshi kurashish', ['title' => 'Korrupsiyaga qarshi kurashish', 'content' => '<p>Tashkilotda korrupsiyaga qarshi kurashish bo‘yicha tizimli siyosat yuritiladi. Normativ hujjatlar bilan «Hujjatlar» bo‘limida tanishishingiz mumkin.</p><p>Korrupsiya holatlari haqida xabar berish uchun qayta aloqa shaklidan foydalanishingiz mumkin.</p>']]
                    ]
                ]
            ]
        ];

        $pageIns = $pdo->prepare(
            "INSERT INTO pages (title, slug, status, is_home, layout_type, created_at)
             SELECT :t, :s, 'published', 0, 'no_sidebar', NOW()
             FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM pages WHERE slug = :s2)"
        );

        $transIns = $pdo->prepare(
            "INSERT INTO page_translations (page_id, lang, title)
             SELECT :pid, :lang, :title
             FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM page_translations WHERE page_id = :pid2 AND lang = :lang2)"
        );

        $blockIns = $pdo->prepare(
            'INSERT INTO blocks (page_id, lang, type, title, data, sort_order, is_active, created_at)
             VALUES (:pid, :lang, :ty, :ti, :d, :so, 1, NOW())'
        );

        foreach ($pages as $slug => $langData) {
            $defaultTitle = $langData['ru']['title'] ?? '';
            $pageIns->execute([':t' => $defaultTitle, ':s' => $slug, ':s2' => $slug]);
            $c['pages'] += $pageIns->rowCount();
            $pid = self::pageId($pdo, $slug);
            if ($pid === null) {
                continue;
            }

            $hasBlocks = (int) $pdo->query('SELECT COUNT(*) FROM blocks WHERE page_id = ' . $pid)->fetchColumn() > 0;

            foreach ($langData as $lang => $data) {
                // Вставляем перевод страницы
                $transIns->execute([
                    ':pid' => $pid,
                    ':lang' => $lang,
                    ':title' => $data['title'],
                    ':pid2' => $pid,
                    ':lang2' => $lang
                ]);

                if (!$hasBlocks) {
                    $order = 1;
                    foreach ($data['blocks'] as [$type, $btitle, $blockData]) {
                        $blockIns->execute([
                            ':pid' => $pid,
                            ':lang' => $lang,
                            ':ty' => $type,
                            ':ti' => $btitle,
                            ':d' => json_encode($blockData, JSON_UNESCAPED_UNICODE),
                            ':so' => $order++
                        ]);
                    }
                }
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
