<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\FormDef;

final class BlockRenderer
{
    /**
     * Дефолтная структура данных для каждого типа блока. Служит источником
     * истины и для конструктора (BlockController), и для рендера: сохранённые
     * JSON сливаются с этими дефолтами, поэтому изменение набора полей блока в
     * будущем не приводит к обращению к несуществующим ключам (задача 27).
     */
    public const DEFAULTS = [
        'text' => ['title' => '', 'content' => ''],
        'html' => ['html' => ''],
        'cta' => ['title' => '', 'text' => '', 'button_text' => '', 'button_url' => '', 'bg_color' => '', 'text_color' => '', 'button_color' => ''],
        'advantages' => ['title' => '', 'items' => []],
        'slider' => ['slides' => []],
        'gallery' => ['title' => '', 'images' => []],
        'form' => ['form_id' => null],
        'columns' => ['columns' => 2, 'gap' => 'medium'],
        'testimonials' => ['title' => '', 'items' => []],
        'counters' => ['title' => '', 'card_bg' => '', 'text_color' => '', 'items' => []],
        'team_list' => ['title' => '', 'limit' => 0],
        'projects_list' => ['title' => '', 'limit' => 3],
        'news_latest' => ['title' => 'Последние новости', 'limit' => 3],
        'partners' => ['title' => 'Партнёры', 'items' => []],
        'banner' => ['title' => '', 'text' => '', 'image' => '', 'button_text' => '', 'button_url' => '', 'bg_color' => '', 'text_color' => '', 'button_color' => ''],
        'subscribe' => ['title' => 'Подписка на новости', 'text' => 'Получайте дайджест новостей на почту раз в неделю.', 'button_text' => 'Подписаться'],
        'faq' => ['title' => '', 'items' => []],
        'contact_cards' => ['title' => '', 'items' => []],
        'hero' => ['title' => '', 'eyebrow' => '', 'subtitle' => '', 'bg_type' => '', 'image' => '', 'video_url' => '', 'youtube_url' => '', 'bg_color' => '', 'width' => 'full', 'height' => 'regular', 'custom_height' => '720px', 'overlay_color' => '#0b1a30', 'overlay_opacity' => 55, 'text_position' => 'left', 'text_width' => '', 'text_color' => '', 'button_color' => '', 'panel_enabled' => false, 'panel_color' => '#0b1a30', 'panel_opacity' => 0, 'button_text' => '', 'button_url' => '', 'button2_text' => '', 'button2_url' => '', 'video_button_text' => '', 'video_button_url' => '', 'show_map' => false, 'map_caption' => ''],
        'categories_grid' => ['title' => '', 'items' => []],
        'media_materials' => ['title' => '', 'items' => []],
        'cards_grid' => ['title' => '', 'all_text' => '', 'all_url' => '', 'columns' => 5, 'card_bg' => '', 'text_color' => '', 'items' => []],
        'image_cards' => ['title' => '', 'all_text' => '', 'all_url' => '', 'source' => 'manual', 'limit' => 6, 'items' => []],
        'media_gallery' => ['title' => '', 'all_text' => '', 'all_url' => '', 'source' => 'manual', 'limit' => 8, 'items' => []],
        'news_feature' => ['title' => 'Новости и аналитика', 'all_text' => 'Все новости', 'all_url' => '', 'limit' => 6],
        'person_cards' => ['title' => '', 'all_text' => '', 'all_url' => '', 'items' => []],
        'timeline' => ['title' => '', 'items' => [], 'button_text' => '', 'button_url' => '', 'cta_title' => '', 'cta_text' => '', 'cta_button_text' => '', 'cta_button_url' => '', 'cta_image' => ''],
        'news_docs' => ['news_title' => 'Актуальные новости', 'news_all_text' => 'Все новости', 'news_all_url' => '', 'limit' => 3, 'docs_title' => 'Документы', 'docs_all_text' => 'Все документы', 'docs_all_url' => '', 'docs' => []],
        'cta_band' => ['title' => '', 'text' => '', 'icon_svg' => '', 'button_text' => '', 'button_url' => '', 'bg_color' => '', 'text_color' => '', 'button_color' => ''],
        'person_profile' => ['photo' => '', 'name' => '', 'position' => '', 'text' => '', 'phone' => '', 'phone_label' => 'Приёмная:', 'email' => '', 'email_label' => 'E-mail:', 'button_text' => '', 'button_url' => ''],
        'feature_band' => ['title' => '', 'items' => []],
        'bio_education' => ['bio_title' => 'Биография', 'bio_text' => '', 'career' => [], 'edu_title' => 'Образование', 'edu_items' => [], 'extra_title' => '', 'extra_text' => '', 'quote_text' => '', 'quote_author' => ''],
        'anchor_nav' => ['items' => []],
        'stages' => ['title' => '', 'all_text' => '', 'all_url' => '', 'items' => []],
        'text_image' => ['title' => '', 'text' => '', 'image' => '', 'items' => []],
        'docs_list' => ['title' => '', 'all_text' => '', 'all_url' => '', 'columns' => 4, 'items' => []],
        'map_point' => ['title' => '', 'image' => '', 'embed_url' => '', 'card_title' => '', 'address' => '', 'button_text' => '', 'button_url' => ''],
        'org_structure' => ['title' => '', 'head_title' => 'Директор', 'head_name' => '', 'head_url' => '', 'side_items' => '', 'branches' => [], 'footnote' => ''],
    ];

    /**
     * Ближайшая граница расписания среди отрисованных блоков — заполняется в
     * render(), сбрасывается в renderPage() и отдаётся вызывающему как
     * expires_at, чтобы кэш страницы не пережил свою же дату показа.
     */
    private static ?int $nextBoundary = null;

    /**
     * Режим предпросмотра в админке. На сайте незаполненный блок просто не
     * выводится (иначе на странице зияет пустая секция с отступами), а
     * редактору вместо него показывается заметка: блок добавлен, но пуст —
     * иначе «ничего не появилось» читается как поломка.
     */
    private static bool $previewMode = false;

    /**
     * Заголовок первого уровня на странице должен быть один: экранный диктор
     * по нему понимает, о чём страница. Обложка, баннер и профиль персоны
     * претендуют на h1 — первому из них он и достаётся, остальным h2.
     */
    private static bool $h1Used = false;

    /**
     * Типы, чей заголовок может быть заголовком страницы. Баннер сюда не
     * входит: это рекламная врезка, и h1 ей не по чину (в тёмном варианте
     * шаблона там и так всегда был h2).
     *
     * @var list<string>
     */
    private const H1_BLOCKS = ['hero', 'person_profile'];

    /**
     * Сообщает рендеру, что h1 на странице уже занят (например, шапкой самой
     * страницы), чтобы блоки не добавляли второй.
     */
    public static function markH1Used(): void
    {
        self::$h1Used = true;
    }

    public static function setPreviewMode(bool $on): void
    {
        self::$previewMode = $on;
    }

    /**
     * Видимой считаем секцию, где есть текст или медиа. Только текст проверять
     * нельзя: галерея из одних фотографий текста не содержит.
     */
    public static function isVisuallyEmpty(string $html): bool
    {
        if (preg_match('/<(img|svg|video|iframe|input|button|source)\b/i', $html)) {
            return false;
        }
        if (preg_match('/background-image\s*:\s*url/i', $html)) {
            return false;
        }

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($html))) === '';
    }

    public static function defaultsFor(string $type): array
    {
        return self::DEFAULTS[$type] ?? [];
    }

    /**
     * @param array<string, mixed> $block
     * @return array{html: string, css: string, hidden?: bool, preload_image?: string|null}
     */
    public static function render(array $block): array
    {
        $type = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $block['type'])) ?? '';
        $blockId = (int) $block['id'];
        $data = json_decode((string) ($block['data'] ?? '{}'), true);
        if (!is_array($data)) {
            $data = [];
        }

        // Смердживание с дефолтами по типу блока — устойчивость к старым/
        // неполным JSON-данным.
        $data = array_merge(self::defaultsFor($type), $data);

        // Условия показа (расписание). Границу запоминаем до проверки: блок,
        // который ещё не начался, тоже обязан разморозить кэш к своему старту.
        $boundary = BlockVisibility::boundary($data);
        if ($boundary !== null && (self::$nextBoundary === null || $boundary < self::$nextBoundary)) {
            self::$nextBoundary = $boundary;
        }
        if (!BlockVisibility::isVisible($data)) {
            return ['html' => '', 'css' => '', 'hidden' => true];
        }

        // Уровень заголовка блока: первому претенденту на странице — h1,
        // следующим — h2 (двух h1 на странице быть не должно).
        if (in_array($type, self::H1_BLOCKS, true)) {
            $data['_heading_tag'] = self::$h1Used ? 'h2' : 'h1';
            self::$h1Used = true;
        }

        $data = self::enrichData($type, $data);

        // Блок «columns» (группа 4.1): рендерим вложенные блоки, сгруппированные
        // по колонкам. Дочерние блоки — обычные блоки со своими scoped-стилями.
        $childrenCss = '';
        if ($type === 'columns') {
            [$html, $childrenCss] = self::renderColumns($block, $data);
        } else {
            $templateFile = dirname(__DIR__, 2) . '/templates/blocks/' . $type . '.php';
            $html = is_file($templateFile)
                ? self::renderTemplate($templateFile, $data, $blockId)
                : '<!-- Неизвестный тип блока: ' . htmlspecialchars($type, ENT_QUOTES) . ' -->';
        }

        $scopedCss = '';
        if (!empty($block['custom_css'])) {
            $scopedCss = CssScoper::scope((string) $block['custom_css'], '#block-' . $blockId);
        }
        if ($childrenCss !== '') {
            $scopedCss = $scopedCss !== '' ? $scopedCss . "\n" . $childrenCss : $childrenCss;
        }

        // Дизайн-система: пресет отступов и опция анимации появления.
        // Ключи _spacing/_reveal могут отсутствовать (старые/битые данные) —
        // берём безопасные значения по умолчанию.
        $spacing = (string) ($data['_spacing'] ?? 'premium');
        if (!in_array($spacing, ['none', 'small', 'premium', 'max'], true)) {
            $spacing = 'premium';
        }
        // Анимация появления (группа 4.2). Обратная совместимость: старое
        // булево _reveal=true → {enabled:true, type:'fade'}.
        $revealRaw = $data['_reveal'] ?? null;
        if (is_array($revealRaw)) {
            $revealOn = !empty($revealRaw['enabled']);
            $revealType = (string) ($revealRaw['type'] ?? 'fade');
        } else {
            $revealOn = !empty($revealRaw);
            $revealType = 'fade';
        }
        if (!in_array($revealType, ['fade', 'slide-up', 'slide-left', 'slide-right', 'zoom-in'], true)) {
            $revealType = 'fade';
        }
        $reveal = $revealOn
            ? ' data-reveal data-reveal-type="' . htmlspecialchars($revealType, ENT_QUOTES) . '"'
            : '';

        // Фон секции, полноширинная подложка и независимые отступы сверху/снизу.
        $bg = (string) ($data['_bg'] ?? 'none');
        if (!in_array($bg, ['none', 'light', 'tint', 'navy'], true)) {
            $bg = 'none';
        }
        $fullwidth = !empty($data['_fullwidth']);
        $padMap = ['none' => '0', 'small' => 'var(--space-small)', 'medium' => 'var(--space-premium)', 'large' => 'var(--space-max)'];
        $extraClass = '';
        if ($bg !== 'none') {
            $extraClass .= ' cms-block--bg cms-block--bg-' . $bg;
        }
        if ($fullwidth) {
            $extraClass .= ' cms-block--fullwidth';
        }
        // Ограничение по устройству — только CSS: кэш страницы общий, серверное
        // ветвление по User-Agent сделало бы его непригодным.
        $extraClass .= BlockVisibility::deviceClass($data);
        $styleVars = '';
        $padTop = (string) ($data['_pad_top'] ?? 'default');
        $padBottom = (string) ($data['_pad_bottom'] ?? 'default');
        if (isset($padMap[$padTop])) {
            $styleVars .= '--block-pad-top:' . $padMap[$padTop] . ';';
        }
        if (isset($padMap[$padBottom])) {
            $styleVars .= '--block-pad-bottom:' . $padMap[$padBottom] . ';';
        }
        $styleAttr = $styleVars !== '' ? ' style="' . $styleVars . '"' : '';

        $wrapped = sprintf(
            '<section id="block-%d" class="cms-block cms-block--%s cms-block--space-%s%s" data-block-type="%s"%s%s>%s</section>',
            $blockId,
            htmlspecialchars($type, ENT_QUOTES),
            htmlspecialchars($spacing, ENT_QUOTES),
            $extraClass,
            htmlspecialchars($type, ENT_QUOTES),
            $reveal,
            $styleAttr,
            $html
        );

        $preloadImage = null;
        if ($type === 'hero') {
            $heroImage = trim((string) ($data['image'] ?? ''));
            $heroBgType = (string) ($data['bg_type'] ?? '');
            if ($heroBgType === '') {
                $heroBgType = Video::youtubeId((string) ($data['youtube_url'] ?? '')) !== null
                    ? 'youtube'
                    : (trim((string) ($data['video_url'] ?? '')) !== '' ? 'video' : ($heroImage !== '' ? 'image' : 'none'));
            }
            if ($heroBgType === 'image' && $heroImage !== '') {
                $preloadImage = $heroImage;
            }
        }

        return ['html' => $wrapped, 'css' => $scopedCss, 'preload_image' => $preloadImage];
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array{html: string, css: string, assets: array<int, string>, preload_images: array<int, string>, expires_at: int|null}
     */
    public static function renderPage(array $blocks): array
    {
        $htmlParts = [];
        $cssParts = [];
        $assets = [];
        $preloadImages = [];
        self::$nextBoundary = null;
        self::$h1Used = false;

        foreach ($blocks as $block) {
            $rendered = self::render($block);
            if (!empty($rendered['hidden'])) {
                continue;
            }
            // Незаполненный блок: на сайте пропускаем, в предпросмотре
            // показываем заметку с типом блока — редактор должен понимать,
            // что блок есть, но его нужно наполнить.
            if (self::isVisuallyEmpty($rendered['html'])) {
                if (!self::$previewMode) {
                    continue;
                }
                $htmlParts[] = self::emptyNotice($block);
                continue;
            }
            $htmlParts[] = $rendered['html'];
            if ($rendered['css'] !== '') {
                $cssParts[] = "/* block #{$block['id']} ({$block['type']}) */\n" . $rendered['css'];
            }
            $type = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $block['type'])) ?? '';
            $assets[$type] = true;
            if (!empty($rendered['preload_image']) && $preloadImages === []) {
                // Одного LCP-кандидата достаточно: дополнительные high-priority
                // preload конкурировали бы с CSS и шрифтами первого экрана.
                $preloadImages[] = (string) $rendered['preload_image'];
            }
        }

        return [
            'html' => implode("\n", $htmlParts),
            'css' => implode("\n\n", $cssParts),
            'assets' => array_keys($assets),
            'preload_images' => $preloadImages,
            'expires_at' => self::$nextBoundary,
        ];
    }

    /**
     * Рендер блока «columns»: дочерние блоки группируются по колонкам и
     * рендерятся рекурсивно обычным render() (переиспользование). Вложение
     * columns-в-columns запрещено (такие дети пропускаются).
     *
     * @param array<string,mixed> $block
     * @param array<string,mixed> $data
     * @return array{0:string,1:string} [html, css дочерних блоков]
     */
    private static function renderColumns(array $block, array $data): array
    {
        $count = (int) ($data['columns'] ?? 2);
        if ($count < 2 || $count > 4) {
            $count = 2;
        }
        $gap = (string) ($data['gap'] ?? 'medium');
        if (!in_array($gap, ['small', 'medium', 'large'], true)) {
            $gap = 'medium';
        }

        // Дочерние блоки доступны только при наличии реального id (в рендере из БД).
        $children = [];
        if (!empty($block['id']) && class_exists(\App\Models\Block::class)) {
            $children = \App\Models\Block::childrenOf((int) $block['id'], true);
        }

        // Группируем по колонкам 0..count-1.
        $byColumn = array_fill(0, $count, []);
        foreach ($children as $child) {
            $col = (int) ($child['column_index'] ?? 0);
            if ($col < 0 || $col >= $count) {
                $col = 0;
            }
            // Защита от вложенности columns-в-columns.
            if ((string) $child['type'] === 'columns') {
                continue;
            }
            $byColumn[$col][] = $child;
        }

        $cssParts = [];
        $colsHtml = '';
        for ($i = 0; $i < $count; $i++) {
            $inner = '';
            foreach ($byColumn[$i] as $child) {
                $rendered = self::render($child);
                if (!empty($rendered['hidden'])) {
                    continue;
                }
                $inner .= $rendered['html'];
                if ($rendered['css'] !== '') {
                    $cssParts[] = $rendered['css'];
                }
            }
            $colsHtml .= '<div class="cms-columns__col">' . $inner . '</div>';
        }

        $html = sprintf(
            '<div class="cms-columns cms-columns--%d cms-columns--gap-%s">%s</div>',
            $count,
            htmlspecialchars($gap, ENT_QUOTES),
            $colsHtml
        );

        return [$html, implode("\n", $cssParts)];
    }

    /**
     * Заметка о незаполненном блоке для предпросмотра в админке.
     *
     * @param array<string,mixed> $block
     */
    private static function emptyNotice(array $block): string
    {
        $type = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $block['type'])) ?? '';
        $label = self::TYPE_LABELS[$type] ?? $type;
        $title = trim((string) ($block['title'] ?? ''));

        return sprintf(
            '<section id="block-%d" class="cms-block cms-block--empty-notice" data-block-type="%s">'
            . '<div class="cms-empty-notice"><strong>Блок «%s»%s пока пуст</strong>'
            . '<span>Заполните поля блока — на сайте он появится. Сейчас посетители его не видят.</span>'
            . '<a class="cms-empty-notice__edit" href="/admin/blocks/%d/edit">Заполнить</a></div></section>',
            (int) $block['id'],
            htmlspecialchars($type, ENT_QUOTES),
            htmlspecialchars($label, ENT_QUOTES),
            $title !== '' ? ' (' . htmlspecialchars($title, ENT_QUOTES) . ')' : '',
            (int) $block['id']
        );
    }

    /** Русские названия типов — для сообщений редактору. */
    public const TYPE_LABELS = [
        'text' => 'Текст', 'html' => 'Произвольный HTML', 'cta' => 'Призыв к действию',
        'advantages' => 'Преимущества', 'slider' => 'Слайдер', 'gallery' => 'Галерея',
        'form' => 'Форма', 'columns' => 'Колонки', 'testimonials' => 'Отзывы',
        'counters' => 'Счётчики', 'team_list' => 'Команда', 'projects_list' => 'Проекты',
        'news_latest' => 'Последние новости', 'partners' => 'Партнёры', 'banner' => 'Баннер',
        'subscribe' => 'Подписка', 'faq' => 'Вопросы и ответы', 'contact_cards' => 'Контакты',
        'hero' => 'Обложка', 'categories_grid' => 'Сетка категорий', 'media_materials' => 'Медиаматериалы',
        'cards_grid' => 'Сетка карточек', 'image_cards' => 'Карточки с фото', 'media_gallery' => 'Медиагалерея',
        'news_feature' => 'Новости и аналитика', 'person_cards' => 'Карточки персон', 'timeline' => 'Хронология',
        'news_docs' => 'Новости и документы', 'cta_band' => 'Полоса призыва', 'person_profile' => 'Профиль персоны',
        'feature_band' => 'Полоса преимуществ', 'bio_education' => 'Биография и образование',
        'anchor_nav' => 'Якорная навигация', 'stages' => 'Этапы', 'text_image' => 'Текст с фото',
        'docs_list' => 'Список документов', 'map_point' => 'Карта', 'org_structure' => 'Оргструктура',
    ];

    private static function enrichData(string $type, array $data): array
    {
        if ($type === 'form' && !empty($data['form_id'])) {
            $form = FormDef::findById((int) $data['form_id']);
            if ($form !== null) {
                $data['form'] = $form;
            }
        }

        // Блоки-обёртки над существующими сущностями (группа 4): выводят
        // опубликованные записи команды/проектов, ограниченные limit (0 = все).
        if ($type === 'team_list') {
            $items = \App\Models\TeamMember::published(Locale::current());
            $limit = (int) ($data['limit'] ?? 0);
            $data['members'] = $limit > 0 ? array_slice($items, 0, $limit) : $items;
        }
        if ($type === 'projects_list') {
            $items = \App\Models\Project::published(Locale::current());
            $limit = (int) ($data['limit'] ?? 0);
            $data['projects'] = $limit > 0 ? array_slice($items, 0, $limit) : $items;
        }

        // Блок «Последние новости»: локализованная лента для главной/любой
        // страницы. limit 0 -> 3 (защита от вывода всех новостей блоком).
        if ($type === 'news_latest') {
            $limit = (int) ($data['limit'] ?? 3);
            if ($limit <= 0) {
                $limit = 3;
            }
            $lang = Locale::current();
            $items = [];
            foreach (\App\Models\News::published($limit, 0, $lang) as $row) {
                $items[] = [
                    'title' => (string) $row['title'],
                    'slug' => (string) $row['slug'],
                    'published_at' => (string) ($row['published_at'] ?? ''),
                    'excerpt' => (string) ($row['excerpt'] ?? ''),
                    'cover' => \App\Models\News::getCoverImage($row),
                    'url' => Locale::url('news/' . $row['slug'], $lang),
                ];
            }
            $data['news'] = $items;
            $data['all_url'] = Locale::url('news', $lang);
        }

        // Блок «Новости и аналитика»: крупная главная новость + список (для
        // главной страницы). limit 0 -> 6.
        if ($type === 'news_feature') {
            $limit = (int) ($data['limit'] ?? 6);
            if ($limit <= 0) {
                $limit = 6;
            }
            $lang = Locale::current();
            $items = [];
            foreach (\App\Models\News::published($limit, 0, $lang) as $row) {
                $items[] = [
                    'title' => (string) $row['title'],
                    'slug' => (string) $row['slug'],
                    'published_at' => (string) ($row['published_at'] ?? ''),
                    'excerpt' => (string) ($row['excerpt'] ?? ''),
                    'badge' => trim((string) ($row['badge'] ?? '')),
                    'cover' => \App\Models\News::getCoverImage($row),
                    'url' => Locale::url('news/' . $row['slug'], $lang),
                ];
            }
            $data['news'] = $items;
            if (($data['all_url'] ?? '') === '') {
                $data['all_url'] = Locale::url('news', $lang);
            }
        }

        // Блок «Новости + документы» (две колонки): лента подтягивается из БД,
        // документы — ручной список. limit 0 -> 3.
        if ($type === 'news_docs') {
            $limit = (int) ($data['limit'] ?? 3);
            if ($limit <= 0) {
                $limit = 3;
            }
            $lang = Locale::current();
            $items = [];
            foreach (\App\Models\News::published($limit, 0, $lang) as $row) {
                $items[] = [
                    'title' => (string) $row['title'],
                    'published_at' => (string) ($row['published_at'] ?? ''),
                    'cover' => \App\Models\News::getCoverImage($row),
                    'url' => Locale::url('news/' . $row['slug'], $lang),
                ];
            }
            $data['news'] = $items;
            if (($data['news_all_url'] ?? '') === '') {
                $data['news_all_url'] = Locale::url('news', $lang);
            }
        }

        // Блок «Проекты» (image_cards) с источником «Проекты»: карточки
        // собираются автоматически из опубликованных проектов, помеченных
        // «показать на главном» — без ручного дублирования (задача 42).
        if ($type === 'image_cards' && ($data['source'] ?? 'manual') === 'projects') {
            $lang = Locale::current();
            $limit = (int) ($data['limit'] ?? 6);
            $items = [];
            foreach (\App\Models\Project::forHome($limit, $lang) as $p) {
                $items[] = [
                    'image' => (string) ($p['cover_image'] ?? ''),
                    'title' => (string) $p['title'],
                    'text' => '',
                    'url' => Locale::url('projects/' . $p['slug'], $lang),
                    'metric' => (string) ($p['result_metric'] ?? ''),
                    'metric_label' => (string) ($p['result_label'] ?? ''),
                ];
            }
            $data['items'] = $items;
            if (($data['all_url'] ?? '') === '') {
                $data['all_url'] = Locale::url('projects', $lang);
            }
        }

        // Блок «Медиа» (media_gallery) с автоисточниками: «Фотоальбомы»,
        // «Видео» или оба сразу («media» — тогда в шаблоне появляются
        // вкладки Видео/Фото). Записи берутся из помеченных «показать на главном».
        $mediaSource = (string) ($data['source'] ?? 'manual');
        if ($type === 'media_gallery' && in_array($mediaSource, ['albums', 'videos', 'media'], true)) {
            $lang = Locale::current();
            $limit = (int) ($data['limit'] ?? 8);
            $items = [];
            if ($mediaSource === 'videos' || $mediaSource === 'media') {
                foreach (\App\Models\Video::forHome($limit, $lang) as $v) {
                    $items[] = [
                        'kind' => 'video',
                        'image' => (string) ($v['cover_url'] ?? ''),
                        'title' => (string) $v['title'],
                        'meta' => (string) ($v['duration'] ?? ''),
                        'url' => (string) ($v['video_url'] ?? ''),
                    ];
                }
            }
            if ($mediaSource === 'albums' || $mediaSource === 'media') {
                foreach (\App\Models\PhotoAlbum::forHome($limit, $lang) as $a) {
                    $items[] = [
                        'kind' => 'photo',
                        'image' => \App\Models\PhotoAlbum::coverFor($a),
                        'title' => (string) $a['title'],
                        'meta' => '',
                        'url' => Locale::url('albums/' . $a['slug'], $lang),
                    ];
                }
            }
            $data['items'] = $items;
            if ($mediaSource === 'albums' && ($data['all_url'] ?? '') === '') {
                $data['all_url'] = Locale::url('albums', $lang);
            }
        }

        return $data;
    }

    private static function renderTemplate(string $file, array $data, int $blockId): string
    {
        $render = static function (string $__file, array $data, int $blockId): void {
            extract(['data' => $data, 'blockId' => $blockId], EXTR_SKIP);
            require $__file;
        };

        ob_start();
        $render($file, $data, $blockId);

        return (string) ob_get_clean();
    }
}
