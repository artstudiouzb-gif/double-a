<?php

declare(strict_types=1);

require __DIR__ . '/../app/Core/Cli.php';
\App\Core\Cli::assertCli();

require __DIR__ . '/../app/Core/bootstrap.php';

use App\Core\Database;
use App\Models\Setting;
use App\Models\Page;
use App\Models\Block;

$pdo = Database::pdo();

echo "=== Запуск расширенного сидера DOUBLE A SOLUTIONS (Многостраничный режим) ===\n";

// 1. Дизайн-настройки в БД
$settings = [
    'design_site_template' => 'double_a',
    'design_palette' => 'double_a',
    'site_name' => 'DOUBLE A SOLUTIONS',
];

foreach ($settings as $key => $val) {
    $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = :v2');
    $stmt->execute([':k' => $key, ':v' => $val, ':v2' => $val]);
}
echo "✓ Настройки темы успешно записаны.\n";

// 2. Список страниц для создания/обновления
$pagesToCreate = [
    'home' => [
        'title' => 'DOUBLE A SOLUTIONS — регуляторный консалтинг',
        'is_home' => 1,
        'layout_type' => 'no_sidebar',
    ],
    'o-nas' => [
        'title' => 'О компании',
        'is_home' => 0,
        'layout_type' => 'no_sidebar',
    ],
    'services' => [
        'title' => 'Услуги',
        'is_home' => 0,
        'layout_type' => 'no_sidebar',
    ],
    'kontakty' => [
        'title' => 'Контакты',
        'is_home' => 0,
        'layout_type' => 'no_sidebar',
    ],
];

// Clean up old subpages from the database
$pdo->exec("DELETE FROM pages WHERE slug IN ('services-market-entry', 'services-permits', 'services-export', 'services-iso')");

$pageIds = [];

foreach ($pagesToCreate as $slug => $p) {
    $stmt = $pdo->prepare('SELECT id FROM pages WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();

    if ($row) {
        $id = (int) $row['id'];
        $stmt = $pdo->prepare('UPDATE pages SET title = :title, is_home = :is_home, layout_type = :lt, status = "published" WHERE id = :id');
        $stmt->execute([
            ':title' => $p['title'],
            ':is_home' => $p['is_home'],
            ':lt' => $p['layout_type'],
            ':id' => $id
        ]);
        echo "✓ Обновлена страница: /{$slug} (ID: {$id})\n";
    } else {
        $stmt = $pdo->prepare('INSERT INTO pages (title, slug, status, is_home, layout_type, hide_chrome, transparent_header, created_at) VALUES (:title, :slug, "published", :is_home, :lt, 0, 0, NOW())');
        $stmt->execute([
            ':title' => $p['title'],
            ':slug' => $slug,
            ':is_home' => $p['is_home'],
            ':lt' => $p['layout_type']
        ]);
        $id = (int) $pdo->lastInsertId();
        echo "✓ Создана новая страница: /{$slug} (ID: {$id})\n";
    }

    $pageIds[$slug] = $id;

    // Очищаем существующие блоки этой страницы
    $stmt = $pdo->prepare('DELETE FROM blocks WHERE page_id = :pid');
    $stmt->execute([':pid' => $id]);
}

// 3. Добавление блоков для каждой страницы

// ==========================================
// ГЛАВНАЯ СТРАНИЦА (/)
// ==========================================
$homeId = $pageIds['home'];

$homeBlocks = [
    // 1. Hero
    [
        'title' => '01. Hero Section',
        'html' => <<<'HTML'
<section class="hero" id="hero">
  <div class="hero-glow-2" aria-hidden="true"></div>
  <div class="wrap hero-grid">
    <div>
      <div class="eyebrow" style="color:var(--gold)">Единая точка входа на новые рынки</div>
      <h1>От требований — <em>к работающему бизнесу.</em></h1>
      <p class="hero-copy">Сопровождаем компании при выходе на рынок Узбекистана и развитии экспорта в СНГ и ЕС: аналитика, регуляторная стратегия, разрешения, стандарты и запуск проектов.</p>
      <div class="hero-actions">
        <a class="btn primary" href="/kontakty"><span>Обсудить проект</span><span class="arrow">↗</span></a>
        <a class="btn outline" href="/services">Выбрать услугу</a>
      </div>
      <div class="hero-foot">
        <div><i></i><span>Регуляторная логика до начала работ</span></div>
        <div><i></i><span>Один координатор по проекту</span></div>
      </div>
    </div>
    <div class="route-card" aria-label="International market route">
      <div class="route-top"><span>Маршрут проекта</span><span class="live">Узбекистан · СНГ · ЕС</span></div>
      <div class="map">
        <svg viewBox="0 0 500 280" aria-hidden="true">
          <defs><pattern id="dots" width="18" height="18" patternUnits="userSpaceOnUse"><circle cx="1" cy="1" r="1" fill="rgba(255,255,255,.12)"/></pattern></defs>
          <rect width="500" height="280" fill="url(#dots)"/>
          <path d="M70 110 C160 35, 240 78, 300 140 S415 185,455 130" fill="none" stroke="rgba(226,191,117,.72)" stroke-width="1.8" stroke-dasharray="5 7"/>
          <path d="M300 140 C260 190,205 195,130 190" fill="none" stroke="rgba(97,201,168,.65)" stroke-width="1.5" stroke-dasharray="4 7"/>
          <circle cx="300" cy="140" r="7" fill="#e2bf75"/>
          <circle cx="300" cy="140" r="16" fill="none" stroke="rgba(226,191,117,.3)"/>
        </svg>
        <span class="map-city eu">EU</span><span class="map-city cis">CIS</span><span class="map-city tashkent">TASHKENT</span><span class="map-city china">ASIA</span>
      </div>
      <div class="route-stages">
        <div class="stage"><b>01</b><span>Анализ рынка</span></div>
        <div class="stage"><b>02</b><span>Требования</span></div>
        <div class="stage"><b>03</b><span>Разрешения</span></div>
        <div class="stage"><b>04</b><span>Запуск и рост</span></div>
      </div>
    </div>
  </div>
</section>
HTML
    ],
    // 2. Trust Strip
    [
        'title' => '02. Trust Strip',
        'html' => <<<'HTML'
<section class="trust-strip">
  <div class="wrap trust-grid">
    <div class="trust-intro"><b>Комплексная модель сопровождения</b><span>Разработка индивидуальной дорожной карты проекта</span></div>
    <div class="metric"><strong>9</strong><span>Языков сайта</span></div>
    <div class="metric"><strong>10</strong><span>Направлений услуг</span></div>
    <div class="metric"><strong>12</strong><span>Целевых отраслей</span></div>
    <div class="metric"><strong>1</strong><span>Координатор проекта</span></div>
  </div>
</section>
HTML
    ],
    // 3. Service Advisor Wizard
    [
        'title' => '03. Service Advisor',
        'html' => <<<'HTML'
<section class="section soft" id="advisor-section">
  <div class="wrap" style="max-width: 800px">
    <div style="text-align: center; margin-bottom: 50px">
      <h2 style="font-size: 36px; margin-top: 10px">Подобрать услугу за 60 секунд</h2>
      <p style="color: var(--muted); margin-top: 10px">Ответьте на 2 простых вопроса для составления предварительного маршрута вашего проекта.</p>
    </div>
    
    <div class="advisor-card" id="serviceAdvisor">
      <!-- Step 1 -->
      <div class="advisor-step active" data-step="1">
        <div class="advisor-title">Шаг 1: Какова ваша сфера деятельности?</div>
        <div class="advisor-desc">Это поможет сузить перечень регуляторных требований.</div>
        <div class="advisor-options">
          <button type="button" class="advisor-opt" data-value="agro">Сельское хозяйство / Удобрения / СЗР</button>
          <button type="button" class="advisor-opt" data-value="food">Пищевой бизнес / БАДы</button>
          <button type="button" class="advisor-opt" data-value="cosmetic">Косметика и парфюмерия</button>
          <button type="button" class="advisor-opt" data-value="vet">Ветеринарные препараты</button>
        </div>
      </div>
      <!-- Step 2 -->
      <div class="advisor-step" data-step="2">
        <div class="advisor-title">Шаг 2: Какая ваша основная цель?</div>
        <div class="advisor-desc">Выберите целевое действие на рынке.</div>
        <div class="advisor-options">
          <button type="button" class="advisor-opt" data-value="import">Импорт и реализация продукции</button>
          <button type="button" class="advisor-opt" data-value="production">Локализация производства</button>
          <button type="button" class="advisor-opt" data-value="export">Выход на экспорт (СНГ/ЕС)</button>
          <button type="button" class="advisor-opt" data-value="iso">Сертификация ISO / Аккредитация</button>
        </div>
      </div>
      <!-- Step 3: Result -->
      <div class="advisor-step" data-step="3">
        <div class="advisor-title">Рекомендуемый маршрут проекта</div>
        <div class="advisor-desc">Наш экспертный алгоритм сформировал следующие шаги:</div>
        <div class="advisor-result" id="advisorResultText">
          <!-- Result text will be injected here -->
        </div>
        <div style="margin-top:30px;display:flex;gap:12px;flex-wrap:wrap">
          <a class="btn primary" href="/kontakty">Обсудить с координатором</a>
          <button type="button" class="btn ghost" id="advisorResetBtn" style="color:var(--navy);border-color:var(--line)">Начать заново</button>
        </div>
      </div>
      <!-- Controls -->
      <div class="advisor-controls" id="advisorControls">
        <button type="button" class="btn ghost" id="advisorPrevBtn" style="visibility:hidden;color:var(--navy);border-color:var(--line);min-height:46px;padding:0 20px;font-size:12px">Назад</button>
        <button type="button" class="btn ink" id="advisorNextBtn" style="min-height:46px;padding:0 20px;font-size:12px" disabled>Продолжить</button>
      </div>
    </div>
  </div>
</section>
HTML
    ],
    // 4. Services Grid
    [
        'title' => '04. Services Highlights',
        'html' => <<<'HTML'
<section class="section" id="services">
  <div class="wrap">
    <div class="services-split">
      <div class="services-left">
        <div class="eyebrow" style="color:var(--gold)">Направления экспертизы</div>
        <h2 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Всё необходимое для <em>запуска, соответствия и роста</em></h2>
        <p style="color:var(--muted);font-size:16px;line-height:1.6">Каждое направление деятельности DOUBLE A SOLUTIONS — это детально проработанный регламент действий для успешного коммерческого результата.</p>
        <div style="margin-top:35px">
          <a class="btn primary" href="/kontakty">Обсудить проект с экспертом</a>
        </div>
      </div>
      <div class="services-right">
        <a class="service quick" href="#service-market" data-value="market">
          <span class="service-no">01</span>
          <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Выход на рынок</h3>
          <p style="color:var(--muted);font-size:16px;line-height:1.6">Анализ конкурентной среды, пошлин и барьеров. Разработка оптимальной юридической модели присутствия.</p>
          <span class="go">↗</span>
        </a>
        <a class="service quick" href="#service-permits" data-value="permits">
          <span class="service-no">02</span>
          <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Разрешительные документы</h3>
          <p style="color:var(--muted);font-size:16px;line-height:1.6">Государственная регистрация БАД, удобрений, пестицидов (СЗР), косметики и ветеринарной продукции под ключ.</p>
          <span class="go">↗</span>
        </a>
        <a class="service quick" href="#service-export" data-value="export">
          <span class="service-no">03</span>
          <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Экспортное сопровождение</h3>
          <p style="color:var(--muted);font-size:16px;line-height:1.6">Приведение производства, маркировки и упаковки продукции к требованиям регламентов ЕС и СНГ.</p>
          <span class="go">↗</span>
        </a>
        <a class="service quick" href="#service-iso" data-value="iso">
          <span class="service-no">04</span>
          <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Международные стандарты</h3>
          <p style="color:var(--muted);font-size:16px;line-height:1.6">Внедрение систем ISO 9001, HACCP, GMP, подготовка испытательных лабораторий к аккредитации по ISO 17025.</p>
          <span class="go">↗</span>
        </a>
      </div>
    </div>
  </div>
</section>
HTML
    ],
    // 5. Industries (Sectors)
    [
        'title' => '05. Industries Section',
        'html' => <<<'HTML'
<section class="section soft" id="industries">
  <div class="wrap">
    <div class="section-head-centered">
      <h2>Понимаем продукт, а не только регламенты</h2>
      <p>Отраслевой контекст определяет тонкости лабораторных испытаний, досье и стратегии запуска.</p>
    </div>
    <div class="sectors">
      <a class="pill quick" href="#service-permits" data-value="permits">Сельское хозяйство ↗</a>
      <a class="pill quick" href="#service-permits" data-value="permits">Пищевая промышленность ↗</a>
      <a class="pill quick" href="#service-permits" data-value="permits">Химическая отрасль ↗</a>
      <a class="pill quick" href="#service-permits" data-value="permits">Удобрения и СЗР ↗</a>
      <a class="pill quick" href="#service-permits" data-value="permits">Косметика и парфюмерия ↗</a>
      <a class="pill quick" href="#service-permits" data-value="permits">БАДы ↗</a>
      <a class="pill quick" href="#service-permits" data-value="permits">Ветеринария ↗</a>
      <a class="pill quick" href="#service-iso" data-value="iso">Лаборатории & Тесты ↗</a>
      <a class="pill quick" href="#service-iso" data-value="iso">HoReCa ↗</a>
      <a class="pill quick" href="#service-export" data-value="export">Экспорт и импорт ↗</a>
    </div>
  </div>
</section>
HTML
    ],
    // 6. Method
    [
        'title' => '06. Method Section',
        'html' => <<<'HTML'
<section class="section dark">
  <div class="wrap journey">
    <div class="journey-intro">
      <div class="eyebrow" style="color:var(--gold)">Метод DOUBLE A</div>
      <h2>Два анализа. <em>Один маршрут.</em></h2>
      <p>Мы параллельно оцениваем коммерческую целесообразность запуска и жесткие регуляторные рамки, формируя единый бесшовный план.</p>
      <a class="btn primary" href="/kontakty" style="margin-top:20px">Получить консультацию</a>
    </div>
    <div class="timeline">
      <div class="timeline-step">
        <div class="timeline-dot">1</div>
        <div class="timeline-content">
          <h3>Диагностика проекта</h3>
          <p>Анализ состава продукции, имеющихся сертификатов и коммерческих целей.</p>
        </div>
      </div>
      <div class="timeline-step">
        <div class="timeline-dot">2</div>
        <div class="timeline-content">
          <h3>Market & Regulatory Fit</h3>
          <p>Определение точного списка пошлин, испытаний, барьеров и каналов продаж.</p>
        </div>
      </div>
      <div class="timeline-step">
        <div class="timeline-dot">3</div>
        <div class="timeline-content">
          <h3>Дорожная карта</h3>
          <p>Разработка последовательного плана действий с указанием бюджетов и сроков.</p>
        </div>
      </div>
      <div class="timeline-step">
        <div class="timeline-dot">4</div>
        <div class="timeline-content">
          <h3>Сопровождение запуска</h3>
          <p>Подача досье в ведомства, организация тестов и контроль до выдачи лицензий.</p>
        </div>
      </div>
    </div>
  </div>
</section>
HTML
    ],
    // 7. Cases Portfolio
    [
        'title' => '07. Cases Section',
        'html' => <<<'HTML'
<section class="section" id="cases">
  <div class="wrap">
    <div class="section-head">
      <h2>Проекты, которыми мы гордимся</h2>
      <p>Отражение нашего реального опыта в решении сложных задач для международного бизнеса.</p>
    </div>
    <div class="case-controls">
      <button class="filter active" data-filter="all">Все проекты</button>
      <button class="filter" data-filter="agro">Сельское хозяйство</button>
      <button class="filter" data-filter="food">Пищевая отрасль</button>
      <button class="filter" data-filter="lab">Лаборатории</button>
    </div>
    <div class="cases-grid">
      <article class="case" data-category="agro">
        <div class="case-visual"><span class="tag">Регистрация</span><b>Регуляторная карта для СЗР</b></div>
        <div class="case-body">
          <div class="case-meta"><span>Импорт из КНР</span><span>АГРО</span></div>
          <p>Полный аудит досье производителя, организация полевых испытаний пестицидов и внесение препарата в госреестр за 9 месяцев.</p>
          <a href="/kontakty">Обсудить аналогичный проект ↗</a>
        </div>
      </article>
      <article class="case" data-category="food">
        <div class="case-visual"><span class="tag">Экспорт</span><b>Подготовка пищевого завода к ЕС</b></div>
        <div class="case-body">
          <div class="case-meta"><span>Экспорт в Германию</span><span>FOOD</span></div>
          <p>Внедрение процедур HACCP на производстве сухофруктов, переработка упаковки под евростандарты и получение сертификата происхождения.</p>
          <a href="/kontakty">Обсудить аналогичный проект ↗</a>
        </div>
      </article>
      <article class="case" data-category="lab">
        <div class="case-visual"><span class="tag">Аккредитация</span><b>Внедрение ISO/IEC 17025 в лаборатории</b></div>
        <div class="case-body">
          <div class="case-meta"><span>Узбекистан</span><span>LAB</span></div>
          <p>Разработка руководства по качеству, обучение внутренних аудиторов и успешное прохождение государственной аккредитации химической лаборатории.</p>
          <a href="/kontakty">Обсудить аналогичный проект ↗</a>
        </div>
      </article>
    </div>
  </div>
</section>
HTML
    ],
    // 8. FAQ
    [
        'title' => '08. FAQ Section',
        'html' => <<<'HTML'
<section class="section soft" id="faq">
  <div class="wrap faq-layout">
    <div class="faq-intro">
      <h2>Ответы на ключевые вопросы</h2>
      <p>Мы собрали ответы на базовые вопросы клиентов о регулировании рынков в Узбекистане.</p>
    </div>
    <div>
      <div class="faq-item">
        <button class="faq-q">Сколько времени занимает государственная регистрация БАД? <i>+</i></button>
        <div class="faq-a"><p>Полный цикл государственной регистрации БАД в органах санитарно-эпидемиологического благополучия (СЭС) занимает в среднем от 2 до 4 месяцев, в зависимости от комплектности исходного досье и длительности лабораторных исследований.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-q">Обязательно ли учреждать местную компанию для получения разрешений? <i>+</i></button>
        <div class="faq-a"><p>Для большинства видов продукции (косметика, удобрения, ветпрепараты) заявителем на государственную регистрацию может выступать иностранный завод-производитель. Однако для физического импорта и дистрибуции вам потребуется резидент Узбекистана.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-q">Какова роль полевых испытаний при регистрации удобрений? <i>+</i></button>
        <div class="faq-a"><p>Полевые испытания являются обязательным условием для включения любого пестицида или агрохимиката в Государственный реестр РУз. Испытания проводятся в течение одного полного вегетационного периода на опытных станциях Минсельхоза.</p></div>
      </div>
    </div>
  </div>
</section>
HTML
    ]
];

foreach ($homeBlocks as $idx => $b) {
    Block::create($homeId, 'ru', 'html', $b['title'], ['html' => $b['html']], '', null, 0);
}


// ==========================================
// СТРАНИЦА «О КОМПАНИИ» (/o-nas)
// ==========================================
$aboutId = $pageIds['o-nas'];

$aboutBlocks = [
    [
        'title' => '01. Page Header',
        'html' => <<<'HTML'
<section class="page-header">
  <div class="wrap">
    <div class="eyebrow" style="color:var(--gold)">О компании</div>
    <h1>Соединяем правила и бизнес-практику</h1>
    <p>DOUBLE A SOLUTIONS — независимый консалтинговый партнер для локального и международного бизнеса в Центральной Азии.</p>
  </div>
</section>
HTML
    ],
    [
        'title' => '02. Rich Text & Values',
        'html' => <<<'HTML'
<section class="section">
  <div class="wrap about-grid">
    <div class="about-copy">
      <h2>Наша миссия — сделать выход на рынок предсказуемым</h2>
      <p>Мы не пишем теоретических отчетов. Наша команда детально анализирует состав вашей продукции, производственные условия и имеющиеся сертификаты, чтобы проложить максимально короткий и легальный путь к получению разрешений и запуску продаж.</p>
      <p>Мы гордимся тем, что строим честные отношения с клиентами: мы никогда не даем ложных обещаний «ускорить процедуры за 3 дня», а сразу предупреждаем обо всех возможных барьерах, пошлинах и реальных сроках.</p>
      
      <div class="values">
        <div class="value">
          <b>Честность и открытость</b>
          <span>Объективная оценка рисков и прозрачное ведение дел.</span>
        </div>
        <div class="value">
          <b>Практическая экспертиза</b>
          <span>Опыт работы в контролирующих органах и лабораториях.</span>
        </div>
      </div>
    </div>
    
    <div class="expert-card">
      <div class="expert-kicker">Ведущий эксперт</div>
      <div>
        <h3>Алишер Агзамов</h3>
        <p>12+ лет опыта в сфере стандартизации, сертификации и международной торговли. Лично руководит сложными проектами в сфере агрохимии и пищевой безопасности.</p>
      </div>
      <div class="expert-tags">
        <span>Стандарты ISO</span>
        <span>Карантин растений</span>
        <span>Санитарный контроль</span>
      </div>
    </div>
  </div>
</section>
HTML
    ]
];

foreach ($aboutBlocks as $idx => $b) {
    Block::create($aboutId, 'ru', 'html', $b['title'], ['html' => $b['html']], '', null, 0);
}


// ==========================================
// СТРАНИЦА «УСЛУГИ» (/services)
// ==========================================
$servicesId = $pageIds['services'];

$servicesBlocks = [
    [
        'title' => '01. Page Header',
        'html' => <<<'HTML'
<section class="page-header">
  <div class="wrap">
    <div class="eyebrow" style="color:var(--gold)">Направления экспертизы</div>
    <h1>Четкие маршруты для ваших задач</h1>
    <p>Каждое направление деятельности DOUBLE A SOLUTIONS — это детально проработанный регламент действий для успешного коммерческого результата.</p>
  </div>
</section>
HTML
    ],
    [
        'title' => '02. Services Detail Grid',
        'html' => <<<'HTML'
<section class="section">
  <div class="wrap">
    <div class="services-grid">
      <a class="service quick" href="#service-market" data-value="market">
        <span class="service-no">01</span>
        <h3>Выход на рынок Узбекистана</h3>
        <p>Анализ конкурентов, пошлин, емкости рынка и подготовка юридической схемы работы.</p>
        <span class="go">↗</span>
      </a>
      <a class="service quick" href="#service-permits" data-value="permits">
        <span class="service-no">02</span>
        <h3>Разрешительные документы</h3>
        <p>Регистрация удобрений, СЗР, пищевой продукции, БАД, косметики и ветпрепаратов.</p>
        <span class="go">↗</span>
      </a>
      <a class="service quick" href="#service-export" data-value="export">
        <span class="service-no">03</span>
        <h3>Экспортное сопровождение</h3>
        <p>Подготовка производственных линий и упаковки для беспрепятственного экспорта в ЕС и СНГ.</p>
        <span class="go">↗</span>
      </a>
      <a class="service quick" href="#service-iso" data-value="iso">
        <span class="service-no">04</span>
        <h3>Международные стандарты</h3>
        <p>Разработка СОП, ХАССП (ISO 22000), GMP, подготовка лабораторий к аккредитации по ISO 17025.</p>
        <span class="go">↗</span>
      </a>
    </div>
  </div>
</section>
HTML
    ]
];

foreach ($servicesBlocks as $idx => $b) {
    Block::create($servicesId, 'ru', 'html', $b['title'], ['html' => $b['html']], '', null, 0);
}


// Detailed subpages are no longer needed because all services detailed descriptions
// are handled inline on the same page dynamically via modal triggers.


// ==========================================
// СТРАНИЦА «КОНТАКТЫ» (/kontakty)
// ==========================================
$contactId = $pageIds['kontakty'];

$contactBlocks = [
    [
        'title' => '01. Page Header',
        'html' => <<<'HTML'
<section class="page-header">
  <div class="wrap">
    <div class="eyebrow" style="color:var(--gold)">Связь с нами</div>
    <h1>Контакты</h1>
    <p>Обсудите ваш проект с нашими координаторами напрямую.</p>
  </div>
</section>
HTML
    ],
    [
        'title' => '02. Contact Grid & Form',
        'html' => <<<'HTML'
<section class="section">
  <div class="wrap contact-grid">
    <div class="contact-copy">
      <h2>Наш офис в Ташкенте</h2>
      <p>Мы всегда рады видеть вас в нашем офисе для персонального обсуждения регуляторных и экспортных стратегий.</p>
      
      <div class="contact-list">
        <div><b>Телефон приемной:</b><br><a href="tel:+998712000000" style="font-size:18px;font-weight:600;color:var(--navy)">+998 (71) 200-00-00</a></div>
        <div><b>Электронная почта:</b><br><a href="mailto:info@doublea.uz" style="font-size:16px;font-weight:500;color:var(--navy)">info@doublea.uz</a></div>
        <div><b>Адрес офиса:</b><br>100084, Ташкент, Юнусабадский район, ул. Амира Темура, 107Б (Бизнес-центр)</div>
      </div>
    </div>
    
    <form class="form" method="post" action="/forms/contact/submit">
      <input type="hidden" name="csrf_token" value="">
      <input type="hidden" name="hp_ts" value="">
      <div style="position:absolute;left:-9999px;" aria-hidden="true"><input type="text" name="hp_website" tabindex="-1" autocomplete="off"></div>
      
      <div>
        <label>Ваше имя</label>
        <input type="text" name="name" required placeholder="Константин">
      </div>
      <div>
        <label>Контактный телефон</label>
        <input type="tel" name="phone" required placeholder="+998 (90) 123-45-67">
      </div>
      <div class="full">
        <label>Электронная почта</label>
        <input type="email" name="email" required placeholder="k.ivanov@company.com">
      </div>
      <div class="full">
        <label>Суть проекта / категория товаров</label>
        <textarea name="message" required placeholder="Какая продукция, требуется импорт, экспорт или сертификация..."></textarea>
      </div>
      <div class="full consent">
        Нажимая кнопку «Отправить запрос», вы соглашаетесь на обработку персональных данных.
      </div>
      <div class="full">
        <button class="btn primary" type="submit" style="width:100%">Отправить запрос</button>
      </div>
    </form>
  </div>
</section>
HTML
    ]
];

foreach ($contactBlocks as $idx => $b) {
    Block::create($contactId, 'ru', 'html', $b['title'], ['html' => $b['html']], '', null, 0);
}


// ==========================================
// 4. ОЧИСТКА И СОЗДАНИЕ МЕНЮ
// ==========================================
$pdo->exec('DELETE FROM menu_items');

$menuItemsList = [
    ['title' => 'Услуги', 'val' => '/services', 'so' => 1],
    ['title' => 'О компании', 'val' => '/o-nas', 'so' => 2],
    ['title' => 'Кейсы', 'val' => '/projects', 'so' => 3],
    ['title' => 'Новости', 'val' => '/news', 'so' => 4],
    ['title' => 'Контакты', 'val' => '/kontakty', 'so' => 5],
];

foreach ($menuItemsList as $m) {
    $stmt = $pdo->prepare('INSERT INTO menu_items (lang, title, url_type, url_value, sort_order, is_active, created_at) VALUES ("ru", :title, "custom", :val, :so, 1, NOW())');
    $stmt->execute([
        ':title' => $m['title'],
        ':val' => $m['val'],
        ':so' => $m['so']
    ]);
}
echo "✓ Новые пункты меню настроены.\n";

// 5. Очистка кэша всех измененных страниц
foreach ($pageIds as $slug => $id) {
    \App\Core\Cache::forgetPrefix('page:' . $id);
}
echo "✓ Кэш всех измененных страниц сброшен.\n";

echo "=== Многостраничный портал DOUBLE A SOLUTIONS успешно развернут! ===\n";
