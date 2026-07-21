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
<div class="luxury-divider"><span class="diamond"></span></div>
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
    // 3. Premium Interactive Diagnostics & Tools Section
    [
        'title' => '03. Service Advisor',
        'html' => <<<'HTML'
<div class="luxury-divider"><span class="diamond"></span></div>
<section class="section soft" id="tools">
  <div class="wrap">
    <div class="section-head" style="margin-bottom: 50px">
      <div>
        <div class="eyebrow" style="color:var(--gold)">Интерактивные инструменты</div>
        <h2 style="font-family:var(--serif); font-weight:600; color:var(--navy)">Начните с короткой диагностики</h2>
      </div>
      <p style="color: var(--muted); font-size:16px; line-height:1.6; max-width:600px">Инструменты помогают сформировать первичный запрос. Итоговые требования подтверждаются экспертом после анализа документов.</p>
    </div>
    <div class="tools-layout">
      <!-- Left Card: Permit Advisor -->
      <article class="tool-card">
        <div class="tool-icon" style="background:#f2f6f5; color:var(--emerald); width:48px; height:48px; border-radius:14px; display:grid; place-items:center; font-size:22px">⌁</div>
        <h3 style="font-family:var(--serif); font-size:24px; color:var(--navy); margin: 24px 0 12px">Какие разрешения могут потребоваться?</h3>
        <p style="color: var(--muted); margin-bottom: 24px; font-size:16px">Выберите категорию продукта и планируемое действие для мгновенного отчета.</p>
        <div class="checker">
          <label style="font-size: 13px; color: var(--gold); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; margin-bottom: 8px; display: block">Категория продукта</label>
          <div class="checker-select-wrap">
            <select id="product" aria-label="Категория продукта">
              <option value="" disabled selected>выберите категорию</option>
              <option value="agro">Удобрение / СЗР</option>
              <option value="food">Пищевой продукт / БАД</option>
              <option value="cosmetic">Косметика / Парфюмерия</option>
              <option value="vet">Ветеринарный товар</option>
            </select>
          </div>
          <label style="font-size: 13px; color: var(--gold); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; margin-bottom: 8px; display: block">Планируемое действие</label>
          <div class="checker-select-wrap">
            <select id="action" aria-label="Планируемое действие">
              <option value="import">Импорт и реализация</option>
              <option value="production">Открытие производства в РУз</option>
              <option value="export">Экспорт из Узбекистана</option>
            </select>
          </div>
          <button class="checker-btn" id="checkBtn">Проверить разрешения</button>
        </div>
        <div class="checker-result" id="result"></div>
      </article>

      <!-- Right Card: Service Selector -->
      <article class="tool-card darkcard" style="background:var(--navy); color:#fff; border-radius:28px">
        <div class="tool-icon" style="background:rgba(255,255,255,0.06); color:var(--gold); width:48px; height:48px; border-radius:14px; display:grid; place-items:center; font-size:22px">◎</div>
        <h3 style="font-family:var(--serif); font-size:24px; color:#fff; margin: 24px 0 12px">Подбор услуги за 60 секунд</h3>
        <p style="color: #a4c0b9; margin-bottom: 24px; font-size:16px">Опишите исходную точку — система предложит подходящий маршрут консультации.</p>
        <div class="darkcard-links">
          <button class="darkcard-link quick" data-value="market">Хочу выйти на рынок Узбекистана</button>
          <button class="darkcard-link quick" data-value="permits">Нужно получить разрешения</button>
          <button class="darkcard-link quick" data-value="export">Планирую экспорт</button>
          <button class="darkcard-link quick" data-value="iso">Нужна подготовка к ISO / GMP</button>
        </div>
      </article>
    </div>

    <!-- Bottom Row: Mini tools -->
    <div class="mini-tools-grid">
      <article class="mini-tool-card">
        <b>Личный кабинет</b>
        <span>Документы, сообщения и задачи в одном защищенном цифровом пространстве.</span>
      </article>
      <article class="mini-tool-card">
        <b>Трекер проекта</b>
        <span>Этапы, ответственные стороны и контрольные точки в реальном времени.</span>
      </article>
      <article class="mini-tool-card">
        <b>Безопасная загрузка</b>
        <span>Передача технических заданий и досье с многоуровневым шифрованием.</span>
      </article>
      <article class="mini-tool-card">
        <b>Онлайн-запись</b>
        <span>Бронирование времени видеозвонка с координатором проекта в один клик.</span>
      </article>
    </div>
  </div>
</section>
HTML
    ],
    // 4. Services Grid
    [
        'title' => '04. Services Highlights',
        'html' => <<<'HTML'
<div class="luxury-divider"><span class="diamond"></span></div>
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
<div class="luxury-divider"><span class="diamond"></span></div>
<section class="section soft" id="industries">
  <div class="wrap">
    <div class="section-head-centered">
      <h2>Понимаем продукт, а не только регламенты</h2>
      <p>Отраслевой контекст определяет тонкости лабораторных испытаний, досье и стратегии запуска.</p>
    </div>
    <div class="sectors-grid">
      <a class="sector-card quick" href="#service-permits" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">01</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Сельское хозяйство</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Регистрация СЗР, удобрений, дефолиантов и координация полевых тестов.</p>
      </a>
      <a class="sector-card quick" href="#service-permits" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">02</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Пищевая промышленность</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Сертификация БАД, продуктов питания, регламенты ХАССП и гигиена СЭС.</p>
      </a>
      <a class="sector-card quick" href="#service-permits" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">03</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Химическая отрасль</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Паспорта безопасности (MSDS), регистрация химвеществ и прекурсоров.</p>
      </a>
      <a class="sector-card quick" href="#service-permits" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">04</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Косметика и парфюмерия</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Испытания безопасности, СЭЗ заключения и GMP стандарты производства.</p>
      </a>
      <a class="sector-card quick" href="#service-permits" data-value="permits">
        <div class="sector-top">
          <span class="sector-num">05</span>
          <span class="sector-arrow">↗</span>
        </div>
        <h3 style="font-family:var(--serif);font-weight:600;color:var(--navy)">Ветеринария</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.6">Регистрация кормовых добавок, вакцин и ветпрепаратов в Комитете ветеринарии.</p>
      </a>
      <a class="sector-card quick" href="#service-iso" data-value="iso">
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
    // 6. Method
    [
        'title' => '06. Method Section',
        'html' => <<<'HTML'
<div class="luxury-divider"><span class="diamond"></span></div>
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
        <div class="timeline-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg><span class="step-num">1</span></div>
        <div class="timeline-content">
          <h3>Диагностика проекта</h3>
          <p>Анализ состава продукции, имеющихся сертификатов и коммерческих целей.</p>
        </div>
      </div>
      <div class="timeline-step">
        <div class="timeline-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg><span class="step-num">2</span></div>
        <div class="timeline-content">
          <h3>Market & Regulatory Fit</h3>
          <p>Определение точного списка пошлин, испытаний, барьеров и каналов продаж.</p>
        </div>
      </div>
      <div class="timeline-step">
        <div class="timeline-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg><span class="step-num">3</span></div>
        <div class="timeline-content">
          <h3>Дорожная карта</h3>
          <p>Разработка последовательного плана действий с указанием бюджетов и сроков.</p>
        </div>
      </div>
      <div class="timeline-step">
        <div class="timeline-dot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg><span class="step-num">4</span></div>
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
<div class="luxury-divider"><span class="diamond"></span></div>
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
<div class="luxury-divider"><span class="diamond"></span></div>
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
