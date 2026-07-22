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
    // 1. Hero (Simplified)
    [
        'title' => '01. Hero Section',
        'html' => <<<'HTML'
<section class="hero" id="hero">
  <div class="hero-glow-2" aria-hidden="true"></div>
  <div class="wrap hero-grid">
    <div>
      <div class="eyebrow" style="color:var(--gold)">Единая точка входа на новые рынки</div>
      <h1 style="margin: 20px 0 28px;">От требований — <em>к работающему бизнесу.</em></h1>
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
    <div class="route-card" aria-label="Карта присутствия в Узбекистане">
      <div class="route-top"><span>presence.uz</span><span class="live">live</span></div>
      <div class="map uz-map">
        <svg viewBox="0 0 1000 620" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false">
          <defs>
            <pattern id="uzDots" width="26" height="26" patternUnits="userSpaceOnUse"><circle cx="1.4" cy="1.4" r="1.4" fill="rgba(255,255,255,.09)"/></pattern>
          </defs>
          <rect x="0" y="0" width="1000" height="620" fill="url(#uzDots)"/>
          <path class="uz-land" d="M 48 262 C 44 218, 82 192, 126 194 C 178 150, 262 130, 344 142 C 428 150, 498 150, 566 158 C 628 144, 690 150, 740 174 C 790 178, 814 196, 812 222 C 848 214, 892 232, 908 262 C 930 268, 938 296, 918 314 C 902 330, 872 330, 852 320 C 838 342, 806 350, 782 338 C 774 360, 742 368, 720 356 C 706 386, 672 402, 648 392 C 648 424, 636 468, 618 486 C 606 500, 588 494, 586 470 C 584 440, 596 410, 604 392 C 578 402, 548 396, 536 372 C 500 384, 452 380, 430 356 C 388 366, 336 356, 318 330 C 274 342, 212 340, 182 320 C 148 336, 100 332, 82 306 C 56 306, 44 288, 48 262 Z"/>
          <path class="uz-route" d="M 180 278 L 400 300 L 548 300 L 700 252 L 828 272" fill="none" pathLength="1"/>
          <path class="uz-route uz-route--branch" d="M 548 300 L 606 452" fill="none" pathLength="1"/>
          <g class="uz-pin"><circle class="uz-pin-dot" cx="180" cy="278" r="6"/></g>
          <g class="uz-pin"><circle class="uz-pin-dot" cx="400" cy="300" r="6"/></g>
          <g class="uz-pin"><circle class="uz-pin-dot" cx="548" cy="300" r="6"/></g>
          <g class="uz-pin uz-pin--cap"><circle class="uz-pin-ring" cx="700" cy="252" r="15"/><circle class="uz-pin-dot" cx="700" cy="252" r="8"/></g>
          <g class="uz-pin"><circle class="uz-pin-dot" cx="828" cy="272" r="6"/></g>
          <g class="uz-pin"><circle class="uz-pin-dot" cx="606" cy="452" r="6"/></g>
          <text class="uz-label" x="118" y="270" text-anchor="end">Нукус</text>
          <text class="uz-label" x="386" y="334" text-anchor="middle">Бухара</text>
          <text class="uz-label" x="566" y="360" text-anchor="middle">Самарканд</text>
          <text class="uz-label uz-label--cap" x="716" y="238" text-anchor="start">Ташкент</text>
          <text class="uz-label" x="846" y="268" text-anchor="start">Андижан</text>
          <text class="uz-label" x="622" y="486" text-anchor="start">Термез</text>
        </svg>
      </div>
      <div class="route-stages">
        <div class="stage"><b>01</b>Анализ рынка</div>
        <div class="stage"><b>02</b>Требования</div>
        <div class="stage"><b>03</b>Разрешения</div>
        <div class="stage"><b>04</b>Запуск и рост</div>
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
    // 3. Services Highlights
    [
        'title' => '03. Services Highlights',
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
        <a class="service quick" href="/services" data-value="market">
          <span class="service-no">01</span>
          <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Выход на рынок</h3>
          <p style="color:var(--muted);font-size:16px;line-height:1.6">Анализ конкурентной среды, пошлин и барьеров. Разработка оптимальной юридической модели присутствия.</p>
          <span class="go">↗</span>
        </a>
        <a class="service quick" href="/services" data-value="permits">
          <span class="service-no">02</span>
          <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Разрешительные документы</h3>
          <p style="color:var(--muted);font-size:16px;line-height:1.6">Государственная регистрация БАД, удобрений, пестицидов (СЗР), косметики и ветеринарной продукции под ключ.</p>
          <span class="go">↗</span>
        </a>
        <a class="service quick" href="/services" data-value="export">
          <span class="service-no">03</span>
          <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Экспортное сопровождение</h3>
          <p style="color:var(--muted);font-size:16px;line-height:1.6">Приведение производства, маркировки и упаковки продукции к требованиям регламентов ЕС и СНГ.</p>
          <span class="go">↗</span>
        </a>
        <a class="service quick" href="/services" data-value="iso">
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
    // 4. Industries (Sectors)
    [
        'title' => '04. Industries Section',
        'html' => <<<'HTML'
<section class="section soft" id="industries">
  <div class="wrap">
    <div class="section-head-centered">
      <h2>Понимаем продукт, а не только регламенты</h2>
      <p>Отраслевой контекст определяет тонкости лабораторных испытаний, досье и стратегии запуска.</p>
    </div>
    <div class="sectors-grid">
      <a class="sector-card quick" href="/services" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">01</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Сельское хозяйство</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Регистрация СЗР, удобрений, дефолиантов и координация полевых тестов.</p>
      </a>
      <a class="sector-card quick" href="/services" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">02</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Пищевая промышленность</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Сертификация БАД, продуктов питания, регламенты ХАССП и гигиена СЭС.</p>
      </a>
      <a class="sector-card quick" href="/services" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">03</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Химическая отрасль</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Паспорта безопасности (MSDS), регистрация химвеществ и прекурсоров.</p>
      </a>
      <a class="sector-card quick" href="/services" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">04</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Косметика и парфюмерия</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Испытания безопасности, СЭЗ заключения и GMP стандарты производства.</p>
      </a>
      <a class="sector-card quick" href="/services" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">05</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Ветеринария</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Регистрация кормовых добавок, вакцин и ветпрепаратов в Комитете ветеринарии.</p>
      </a>
      <a class="sector-card quick" href="/services" data-value="iso">
        <div class="sector-top">
          <span class="sector-num">06</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Лаборатории & Тесты</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Внедрение ISO 17025, калибровка оборудования и государственная аккредитация.</p>
      </a>
    </div>
  </div>
</section>
HTML
    ],
    // 5. FAQ
    [
        'title' => '05. FAQ Section',
        'html' => <<<'HTML'
<section class="section" id="faq">
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
    ],
    // 6. Testimonials
    [
        'title' => '06. Testimonials',
        'html' => <<<'HTML'
<div class="block-testimonials">
    <h2 class="block-testimonials__title">Что о нас говорят клиенты</h2>
    <div class="block-testimonials__track" tabindex="0" role="group">
        <figure class="testimonial">
            <blockquote class="testimonial__quote">DOUBLE A SOLUTIONS обеспечили бесшовный выход нашей продукции на рынок Узбекистана.</blockquote>
            <figcaption class="testimonial__author">
                <span class="testimonial__name">Джон Смит</span>
                <span class="testimonial__company">Глобальный Директор, AgriCorp</span>
            </figcaption>
        </figure>
        <figure class="testimonial">
            <blockquote class="testimonial__quote">Высочайший уровень экспертизы и абсолютная прозрачность. Рекомендуем.</blockquote>
            <figcaption class="testimonial__author">
                <span class="testimonial__name">Елена Власова</span>
                <span class="testimonial__company">CEO, PharmaTech</span>
            </figcaption>
        </figure>
        <figure class="testimonial">
            <blockquote class="testimonial__quote">Сократили сроки получения разрешений на 3 месяца благодаря их стратегии.</blockquote>
            <figcaption class="testimonial__author">
                <span class="testimonial__name">Азиз Рахимов</span>
                <span class="testimonial__company">Директор по развитию, FoodGroup</span>
            </figcaption>
        </figure>
    </div>
</div>
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
    <div class="services-editorial-list">
      <!-- 01. Market Entry -->
      <article class="service-editorial-item">
        <div class="service-editorial-left">
          <span class="service-editorial-num">01</span>
          <h2>Выход на рынок Узбекистана</h2>
          <p>Комплексная стратегия запуска коммерческой деятельности, анализ регуляторных рисков и разработка оптимальной юридической модели присутствия.</p>
          <a class="btn primary" href="/kontakty">Обсудить проект</a>
        </div>
        <div class="service-editorial-right">
          <div class="service-sublist-title">Что входит в услугу</div>
          <div class="service-subitems">
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg></span><h4>Маркетинговые исследования</h4></div>
              <p>Оценка емкости рынка, анализ цен конкурентов и выявление надежных дистрибьюторских сетей.</p>
            </div>
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></span><h4>Анализ таможенных барьеров</h4></div>
              <p>Расчет пошлин, акцизов, импортного НДС и определение нетарифных мер регулирования.</p>
            </div>
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg></span><h4>Юридическое структурирование</h4></div>
              <p>Выбор оптимальной формы присутствия (дочернее общество, СП, представительство) и налогового режима.</p>
            </div>
          </div>
          <div class="service-metrics-grid">
            <div class="service-metric">
              <b>Сроки реализации</b>
              <span>От 15 до 30 рабочих дней</span>
            </div>
            <div class="service-metric">
              <b>Итоговый результат</b>
              <span>Детальный бизнес-план запуска</span>
            </div>
          </div>
        </div>
      </article>

      <!-- 02. Permits & Registrations -->
      <article class="service-editorial-item">
        <div class="service-editorial-left">
          <span class="service-editorial-num">02</span>
          <h2>Разрешительные документы</h2>
          <p>Профессиональное сопровождение государственной регистрации и обязательной сертификации регулируемых категорий продукции под ключ.</p>
          <a class="btn primary" href="/kontakty">Начать регистрацию</a>
        </div>
        <div class="service-editorial-right">
          <div class="service-sublist-title">Отраслевая экспертиза</div>
          <div class="service-subitems">
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5z"></path><line x1="16" y1="8" x2="2" y2="22"></line><line x1="17.5" y1="15" x2="9" y2="15"></line></svg></span><h4>Сельское хозяйство и агрохимия</h4></div>
              <p>Регистрация минеральных удобрений, СЗР и пестицидов. Организация полевых и токсикологических испытаний.</p>
            </div>
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg></span><h4>Пищевая продукция и БАД</h4></div>
              <p>Получение гигиенических заключений СЭС РУз, проведение лабораторных тестов безопасности.</p>
            </div>
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg></span><h4>Косметика и парфюмерия</h4></div>
              <p>Разработка технических досье, декларирование соответствия, прохождение санитарно-эпидемиологического контроля.</p>
            </div>
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></span><h4>Ветеринария и ветпрепараты</h4></div>
              <p>Регистрация препаратов и кормовых добавок в Комитете ветеринарии и развития животноводства РУз.</p>
            </div>
          </div>
          <div class="service-metrics-grid">
            <div class="service-metric">
              <b>Сроки ведения</b>
              <span>От 45 до 90 рабочих дней</span>
            </div>
            <div class="service-metric">
              <b>Итоговый документ</b>
              <span>Регистрационное удостоверение РУз</span>
            </div>
          </div>
        </div>
      </article>

      <!-- 03. Export Support -->
      <article class="service-editorial-item">
        <div class="service-editorial-left">
          <span class="service-editorial-num">03</span>
          <h2>Экспортное сопровождение</h2>
          <p>Комплексная адаптация продукции, упаковки и технологических процессов для успешного экспорта товаров из Узбекистана в СНГ и ЕС.</p>
          <a class="btn primary" href="/kontakty">Подготовить экспорт</a>
        </div>
        <div class="service-editorial-right">
          <div class="service-sublist-title">Ключевые направления</div>
          <div class="service-subitems">
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><polygon points="12 22.08 12 12 3 6.92 3 17.08 12 22.08"></polygon><polygon points="12 22.08 21 17.08 21 6.92 12 12 12 22.08"></polygon><polygon points="12 12 21 6.92 12 2 3 6.92 12 12"></polygon></svg></span><h4>Аудит упаковки и маркировки</h4></div>
              <p>Приведение этикеток и текстовой информации в соответствие техническим регламентам стран импорта.</p>
            </div>
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></span><h4>Сертификаты происхождения (СТ-1)</h4></div>
              <p>Полноценное оформление сертификатов происхождения СТ-1, Form A, фитосанитарных и ветеринарных документов.</p>
            </div>
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span><h4>Инспекционный аудит</h4></div>
              <p>Предварительный технологический аудит производства перед визитом зарубежных инспекторов.</p>
            </div>
          </div>
          <div class="service-metrics-grid">
            <div class="service-metric">
              <b>Подготовка</b>
              <span>От 20 рабочих дней</span>
            </div>
            <div class="service-metric">
              <b>Итоговый результат</b>
              <span>Зеленый коридор на таможне импорта</span>
            </div>
          </div>
        </div>
      </article>

      <!-- 04. Standards & Accreditation -->
      <article class="service-editorial-item">
        <div class="service-editorial-left">
          <span class="service-editorial-num">04</span>
          <h2>Международные стандарты</h2>
          <p>Внедрение современных систем менеджмента качества и техническая подготовка лабораторий к признанию на мировом уровне.</p>
          <a class="btn primary" href="/kontakty">Внедрить стандарт</a>
        </div>
        <div class="service-editorial-right">
          <div class="service-sublist-title">Стандарты качества</div>
          <div class="service-subitems">
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></span><h4>ХАССП (HACCP / ISO 22000)</h4></div>
              <p>Внедрение систем контроля рисков и пищевой безопасности для производств и экспортеров.</p>
            </div>
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></span><h4>Косметическое GMP (ISO 22716)</h4></div>
              <p>Внедрение надлежащей производственной практики на парфюмерно-косметических предприятиях.</p>
            </div>
            <div class="service-subitem">
              <div class="service-subitem-header"><span class="service-subitem-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg></span><h4>Аккредитация лабораторий (ISO 17025)</h4></div>
              <p>Разработка СОП, методик калибровки, проведение МСИ и аудит готовности к аккредитации.</p>
            </div>
          </div>
          <div class="service-metrics-grid">
            <div class="service-metric">
              <b>Внедрение</b>
              <span>От 30 до 60 рабочих дней</span>
            </div>
            <div class="service-metric">
              <b>Итоговый статус</b>
              <span>Международный сертификат соответствия</span>
            </div>
          </div>
        </div>
      </article>
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
