<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;

/**
 * Управление дизайном сайта («тема-билдер»): готовые конфигурации (пресеты) и
 * точная настройка визуальных параметров. Значения хранятся в settings
 * (design_*), применяются на фронтенде через CSS-переменные и классы <body>.
 * Источник истины для админ-панели (карточки-опции) и для рендера.
 */
final class DesignSettings
{
    /**
     * Опции точной настройки: ключ => [label, hint, choices[value=>label], default].
     * Каждая опция рендерится в админке набором карточек-переключателей.
     */
    /**
     * Готовые палитры: значение опции palette => [подпись, основной, акцент].
     * 'custom' сохраняет цвета, заданные вручную в «Настройках».
     */
    public const PALETTES = [
        'gov_blue' => ['Гос-синий', '#173a63', '#17999b'],
        'classic_red' => ['Классика', '#1a1a1a', '#e63946'],
        'emerald' => ['Изумруд', '#14532d', '#059669'],
        'graphite' => ['Графит', '#111827', '#374151'],
        'violet' => ['Индиго', '#312e81', '#6d28d9'],
        'custom' => ['Свои цвета', '', ''],
    ];

    /**
     * Каталог Google-шрифтов с поддержкой кириллицы:
     * slug => [подпись, CSS-стек, параметр family для css2 API].
     */
    public const GOOGLE_FONTS = [
        'pt-serif' => ['PT Serif (антиква)', "'PT Serif', Georgia, serif", 'PT+Serif:wght@400;700'],
        'playfair' => ['Playfair Display (антиква)', "'Playfair Display', Georgia, serif", 'Playfair+Display:wght@500;700'],
        'lora' => ['Lora (антиква)', "'Lora', Georgia, serif", 'Lora:wght@400;600;700'],
        'merriweather' => ['Merriweather (антиква)', "'Merriweather', Georgia, serif", 'Merriweather:wght@400;700'],
        'noto-serif' => ['Noto Serif (антиква)', "'Noto Serif', Georgia, serif", 'Noto+Serif:wght@400;600;700'],
        'ibm-plex-serif' => ['IBM Plex Serif (антиква)', "'IBM Plex Serif', Georgia, serif", 'IBM+Plex+Serif:wght@400;600;700'],
        'cormorant' => ['Cormorant Garamond (антиква)', "'Cormorant Garamond', Georgia, serif", 'Cormorant+Garamond:wght@500;600;700'],
        'pt-sans' => ['PT Sans', "'PT Sans', system-ui, sans-serif", 'PT+Sans:wght@400;700'],
        'inter' => ['Inter', "'Inter', system-ui, sans-serif", 'Inter:wght@400;600;700'],
        'montserrat' => ['Montserrat', "'Montserrat', system-ui, sans-serif", 'Montserrat:wght@400;600;700'],
        'roboto' => ['Roboto', "'Roboto', system-ui, sans-serif", 'Roboto:wght@400;500;700'],
        'open-sans' => ['Open Sans', "'Open Sans', system-ui, sans-serif", 'Open+Sans:wght@400;600;700'],
        'noto-sans' => ['Noto Sans', "'Noto Sans', system-ui, sans-serif", 'Noto+Sans:wght@400;600;700'],
        'source-sans' => ['Source Sans 3', "'Source Sans 3', system-ui, sans-serif", 'Source+Sans+3:wght@400;600;700'],
        'ibm-plex-sans' => ['IBM Plex Sans', "'IBM Plex Sans', system-ui, sans-serif", 'IBM+Plex+Sans:wght@400;600;700'],
        'manrope' => ['Manrope', "'Manrope', system-ui, sans-serif", 'Manrope:wght@400;600;700'],
        'rubik' => ['Rubik', "'Rubik', system-ui, sans-serif", 'Rubik:wght@400;500;700'],
        'jost' => ['Jost', "'Jost', system-ui, sans-serif", 'Jost:wght@400;500;700'],
        'raleway' => ['Raleway', "'Raleway', system-ui, sans-serif", 'Raleway:wght@400;600;700'],
        'exo2' => ['Exo 2', "'Exo 2', system-ui, sans-serif", 'Exo+2:wght@400;600;700'],
        'golos' => ['Golos Text', "'Golos Text', system-ui, sans-serif", 'Golos+Text:wght@400;600;700'],
    ];

    /** Шрифтовые пресеты: значение опции font_style => [подпись, CSS-стек]. */
    public const FONTS = [
        'pt' => ['PT Serif / PT Sans (гос)', "'PT Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"],
        'inter' => ['Inter', "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"],
        'system' => ['Системный', "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"],
        'serif' => ['С засечками', "Georgia, 'Times New Roman', serif"],
        'custom' => ['Свой шрифт', ''],
    ];

    public const OPTIONS = [
        'palette' => [
            'label' => 'Цветовая палитра',
            'hint' => 'Основной и акцентный цвета сайта. «Свои цвета» — значения из «Настроек».',
            'group' => 'Цвета и шрифт',
            'choices' => ['gov_blue' => 'Гос-синий', 'classic_red' => 'Классика', 'emerald' => 'Изумруд', 'graphite' => 'Графит', 'violet' => 'Индиго', 'custom' => 'Свои цвета'],
            'default' => 'custom',
        ],
        'font_style' => [
            'label' => 'Шрифт сайта',
            'hint' => '«Свой шрифт» — значение из «Настроек» (включая локальный @font-face).',
            'group' => 'Цвета и шрифт',
            'choices' => ['inter' => 'Inter', 'system' => 'Системный', 'serif' => 'С засечками', 'custom' => 'Свой шрифт'],
            'default' => 'custom',
        ],
        'container' => [
            'label' => 'Ширина контейнера',
            'hint' => 'Максимальная ширина основного содержимого. Ниже можно задать свою точную ширину.',
            'group' => 'Общие',
            'choices' => ['narrow' => 'Узкий', 'standard' => 'Стандарт', 'wide' => 'Широкий', 'ultra' => 'Очень широкий', 'full' => 'На всю ширину'],
            'default' => 'standard',
        ],
        'radius' => [
            'label' => 'Скругление углов',
            'hint' => 'Радиус карточек, кнопок и полей.',
            'group' => 'Общие',
            'choices' => ['none' => 'Прямые', 'small' => 'Малое', 'medium' => 'Среднее', 'large' => 'Большое'],
            'default' => 'medium',
        ],
        'card_gap' => [
            'label' => 'Отступ между карточками',
            'hint' => 'Расстояние между элементами в сетках.',
            'group' => 'Общие',
            'choices' => ['xs' => '8px', 'sm' => '16px', 'md' => '24px', 'lg' => '32px'],
            'default' => 'md',
        ],
        'density' => [
            'label' => 'Плотность секций',
            'hint' => 'Вертикальные отступы между секциями страницы.',
            'group' => 'Общие',
            'choices' => ['compact' => 'Компактно', 'standard' => 'Стандарт', 'spacious' => 'Просторно'],
            'default' => 'standard',
        ],
        'font_size' => [
            'label' => 'Размер шрифта',
            'hint' => 'Базовый размер основного текста сайта.',
            'group' => 'Типографика',
            'choices' => ['sm' => 'Мельче', 'md' => 'Стандарт', 'lg' => 'Крупнее', 'xl' => 'Очень крупный'],
            'default' => 'md',
        ],
        'line_height' => [
            'label' => 'Межстрочный интервал',
            'hint' => 'Высота строки основного текста.',
            'group' => 'Типографика',
            'choices' => ['tight' => 'Плотный', 'normal' => 'Стандарт', 'relaxed' => 'Просторный'],
            'default' => 'normal',
        ],
        'button' => [
            'label' => 'Форма кнопок',
            'hint' => 'Стиль углов у кнопок и CTA.',
            'group' => 'Общие',
            'choices' => ['square' => 'Прямые', 'rounded' => 'Скруглённые', 'pill' => 'Капсула'],
            'default' => 'rounded',
        ],
        'card_style' => [
            'label' => 'Стиль карточек',
            'hint' => 'Тень и глубина карточек контента.',
            'group' => 'Общие',
            'choices' => ['flat' => 'Плоские', 'soft' => 'Мягкая тень', 'elevated' => 'Приподнятые'],
            'default' => 'soft',
        ],
        'sidebar_position' => [
            'label' => 'Боковая колонка при прокрутке',
            'hint' => 'Поведение сайдбара страниц с боковой колонкой.',
            'group' => 'Общие',
            'choices' => ['floating' => 'Плавающая', 'fixed' => 'Неподвижная'],
            'default' => 'floating',
        ],
        'catalog_layout' => [
            'label' => 'Шаблон списка разделов',
            'hint' => 'Как выводятся карточки в каталоге (Документы/Вакансии/Тендеры).',
            'group' => 'Каталог',
            'choices' => ['cards_lg' => 'Большие карточки', 'cards_sm' => 'Компактные карточки', 'list' => 'Списком'],
            'default' => 'cards_lg',
        ],
        'header_style' => [
            'label' => 'Стиль шапки',
            'hint' => 'Оформление верхней шапки сайта.',
            'group' => 'Шапка',
            'choices' => ['light' => 'Светлая', 'dark' => 'Тёмная', 'accent' => 'Цветная'],
            'default' => 'light',
        ],
        'header_sticky' => [
            'label' => 'Фиксированная шапка',
            'hint' => 'Шапка остаётся вверху при прокрутке.',
            'group' => 'Шапка',
            'choices' => ['on' => 'Включена', 'off' => 'Выключена'],
            'default' => 'on',
        ],
        'search_type' => [
            'label' => 'Тип поиска',
            'hint' => 'Поле поиска в строке шапки или выпадающая панель по клику.',
            'group' => 'Шапка',
            'choices' => ['inline' => 'В строке', 'overlay' => 'Выпадающий'],
            'default' => 'inline',
        ],
        'detail_layout' => [
            'label' => 'Шаблон детальной страницы',
            'hint' => 'Как показывать карточку записи каталога.',
            'group' => 'Каталог',
            'choices' => ['plain' => 'В одну колонку', 'sidebar' => 'С боковой панелью'],
            'default' => 'plain',
        ],
        'footer_style' => [
            'label' => 'Футер',
            'hint' => 'Простой копирайт или многоколоночный подвал.',
            'group' => 'Футер',
            'choices' => ['minimal' => 'Минимальный', 'columns' => 'Колонками'],
            'default' => 'columns',
        ],
        'mobile_menu' => [
            'label' => 'Меню на мобильных',
            'hint' => 'Как показывать главное меню на телефонах.',
            'group' => 'Мобильная версия',
            'choices' => ['burger' => 'Бургер-меню', 'wrap' => 'В строку (перенос)'],
            'default' => 'burger',
        ],
        'mobile_header' => [
            'label' => 'Мобильная шапка',
            'hint' => 'Поведение шапки на телефонах при прокрутке.',
            'group' => 'Мобильная версия',
            'choices' => ['fixed' => 'Фиксированная', 'static' => 'Обычная'],
            'default' => 'fixed',
        ],
    ];

    /**
     * Готовые конфигурации: применяют набор опций одним кликом.
     */
    public const PRESETS = [
        'classic' => [
            'label' => 'Классический',
            'desc' => 'Строгий официальный стиль, умеренные отступы.',
            'values' => ['container' => 'standard', 'radius' => 'small', 'card_gap' => 'sm', 'density' => 'standard', 'font_size' => 'md', 'line_height' => 'normal', 'button' => 'rounded', 'card_style' => 'soft', 'sidebar_position' => 'floating', 'catalog_layout' => 'cards_lg', 'header_style' => 'light', 'header_sticky' => 'on', 'search_type' => 'inline', 'detail_layout' => 'plain', 'footer_style' => 'columns', 'mobile_menu' => 'burger', 'mobile_header' => 'fixed', 'palette' => 'gov_blue', 'font_style' => 'system'],
        ],
        'modern' => [
            'label' => 'Современный',
            'desc' => 'Крупные скругления, воздух, акцентная шапка.',
            'values' => ['container' => 'wide', 'radius' => 'large', 'card_gap' => 'md', 'density' => 'spacious', 'font_size' => 'lg', 'line_height' => 'relaxed', 'button' => 'pill', 'card_style' => 'elevated', 'sidebar_position' => 'floating', 'catalog_layout' => 'cards_lg', 'header_style' => 'accent', 'header_sticky' => 'on', 'search_type' => 'overlay', 'detail_layout' => 'sidebar', 'footer_style' => 'columns', 'mobile_menu' => 'burger', 'mobile_header' => 'fixed', 'palette' => 'violet', 'font_style' => 'inter'],
        ],
        'minimal' => [
            'label' => 'Минимал',
            'desc' => 'Прямые углы, максимум воздуха, список в каталоге.',
            'values' => ['container' => 'narrow', 'radius' => 'none', 'card_gap' => 'md', 'density' => 'spacious', 'font_size' => 'md', 'line_height' => 'normal', 'button' => 'square', 'card_style' => 'flat', 'sidebar_position' => 'fixed', 'catalog_layout' => 'list', 'header_style' => 'light', 'header_sticky' => 'off', 'search_type' => 'overlay', 'detail_layout' => 'plain', 'footer_style' => 'minimal', 'mobile_menu' => 'burger', 'mobile_header' => 'static', 'palette' => 'graphite', 'font_style' => 'serif'],
        ],
        'compact' => [
            'label' => 'Компактный',
            'desc' => 'Плотная сетка, маленькие карточки — много данных.',
            'values' => ['container' => 'standard', 'radius' => 'small', 'card_gap' => 'xs', 'density' => 'compact', 'font_size' => 'sm', 'line_height' => 'tight', 'button' => 'rounded', 'card_style' => 'soft', 'sidebar_position' => 'fixed', 'catalog_layout' => 'cards_sm', 'header_style' => 'light', 'header_sticky' => 'on', 'search_type' => 'inline', 'detail_layout' => 'sidebar', 'footer_style' => 'columns', 'mobile_menu' => 'burger', 'mobile_header' => 'fixed', 'palette' => 'classic_red', 'font_style' => 'system'],
        ],
    ];

    /** Текущие значения всех опций (из settings, с дефолтами). @return array<string,string> */
    public static function current(): array
    {
        $values = [];
        foreach (self::OPTIONS as $key => $opt) {
            $stored = (string) Setting::get('design_' . $key, '');
            $values[$key] = isset($opt['choices'][$stored]) ? $stored : $opt['default'];
        }

        return $values;
    }

    /** Проверяет и нормализует одно значение опции. */
    public static function sanitize(string $key, string $value): ?string
    {
        if (!isset(self::OPTIONS[$key])) {
            return null;
        }
        return isset(self::OPTIONS[$key]['choices'][$value]) ? $value : self::OPTIONS[$key]['default'];
    }

    /** Сохраняет набор значений (только известные опции). @param array<string,mixed> $input */
    /**
     * Своя ширина контейнера (design_container_custom): '' если не задана/
     * невалидна. Принимает 640–2400 (px), px/rem/vw/% с единицей, или число.
     */
    public static function containerCustom(): string
    {
        $raw = trim((string) Setting::get('design_container_custom', ''));
        return self::normalizeWidth($raw);
    }

    /** Нормализует пользовательскую ширину или возвращает '' при невалидной. */
    public static function normalizeWidth(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{2,4}(px|rem|vw|%)$/', $raw)) {
            return $raw;
        }
        if (preg_match('/^\d{3,4}$/', $raw)) {
            $n = (int) $raw;
            return ($n >= 640 && $n <= 2400) ? $n . 'px' : '';
        }
        return '';
    }

    public static function save(array $input): void
    {
        foreach (self::OPTIONS as $key => $opt) {
            $val = self::sanitize($key, (string) ($input[$key] ?? ''));
            Setting::set('design_' . $key, (string) $val);
        }
        // Своя ширина контейнера — отдельное свободное поле (не из choices).
        Setting::set('design_container_custom', self::normalizeWidth(trim((string) ($input['container_custom'] ?? ''))));

        // Материализация палитры/шрифта в реальные настройки сайта
        // (color_primary/color_accent/font_family, их читает фронтенд).
        // 'custom' ничего не трогает — остаются значения из «Настроек».
        $palette = (string) Setting::get('design_palette', 'custom');
        if ($palette !== 'custom' && isset(self::PALETTES[$palette])) {
            Setting::set('color_primary', self::PALETTES[$palette][1]);
            Setting::set('color_accent', self::PALETTES[$palette][2]);
        }
        $font = (string) Setting::get('design_font_style', 'custom');
        if ($font !== 'custom' && isset(self::FONTS[$font])) {
            Setting::set('font_family', self::FONTS[$font][1]);
        }

        // Google-шрифты: отдельные роли «заголовки» и «текст». Пустое значение
        // возвращает роль к стандартному стеку (PT Serif / PT Sans).
        foreach (['heading' => ['font_heading', "'PT Serif', Georgia, 'Times New Roman', serif"],
                  'body' => ['font_family', "'PT Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"]] as $role => [$target, $default]) {
            if (!array_key_exists('font_google_' . $role, $input)) {
                continue;
            }
            $slug = (string) $input['font_google_' . $role];
            $prev = (string) Setting::get('design_font_google_' . $role, '');
            if ($slug !== '' && isset(self::GOOGLE_FONTS[$slug])) {
                Setting::set('design_font_google_' . $role, $slug);
                Setting::set($target, self::GOOGLE_FONTS[$slug][1]);
            } elseif ($slug === '' && $prev !== '') {
                Setting::set('design_font_google_' . $role, '');
                Setting::set($target, $default);
            }
        }
    }

    /**
     * Ссылка на css2 Google Fonts для выбранных ролей (или null, если
     * Google-шрифты не используются). Кириллица включена в css2 по умолчанию.
     */
    public static function googleFontsHref(): ?string
    {
        $families = [];
        foreach (['heading', 'body'] as $role) {
            $slug = (string) Setting::get('design_font_google_' . $role, '');
            if ($slug !== '' && isset(self::GOOGLE_FONTS[$slug])) {
                $families[self::GOOGLE_FONTS[$slug][2]] = true;
            }
        }
        if ($families === []) {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?family='
            . implode('&family=', array_keys($families)) . '&display=swap';
    }

    /** Применяет готовую конфигурацию (встроенную или пользовательскую «user:slug»). */
    public static function applyPreset(string $preset): bool
    {
        if (str_starts_with($preset, 'user:')) {
            return self::applyUserPreset(substr($preset, 5));
        }
        if (!isset(self::PRESETS[$preset])) {
            return false;
        }
        self::save(self::PRESETS[$preset]['values']);
        Setting::set('design_preset', $preset);

        return true;
    }

    // --- Пользовательские конфигурации (сохранённые администратором) ---

    private const USER_PRESETS_KEY = 'design_user_presets';
    private const USER_PRESETS_MAX = 10;

    /** @return array<string,array{label:string,values:array<string,string>,colors?:array<int,string>}> */
    public static function userPresets(): array
    {
        $json = Setting::get(self::USER_PRESETS_KEY, '');
        $data = $json !== '' ? json_decode($json, true) : null;

        return is_array($data) ? $data : [];
    }

    /**
     * Сохраняет ТЕКУЩИЕ настройки дизайна как именованную конфигурацию.
     * Вместе с опциями снапшотится ручная тройка цвет/акцент/шрифт — чтобы
     * пресет с палитрой «Свои цвета» восстанавливался в точности.
     * Возвращает slug или null (пустое имя / превышен лимит).
     */
    public static function saveUserPreset(string $name): ?string
    {
        $name = mb_substr(trim($name), 0, 40);
        if ($name === '') {
            return null;
        }

        $presets = self::userPresets();
        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)) ?: 'preset';
        $slug = trim($slug, '-') ?: 'preset';
        if (!isset($presets[$slug]) && count($presets) >= self::USER_PRESETS_MAX) {
            return null;
        }

        $presets[$slug] = [
            'label' => $name,
            'values' => self::current(),
            'colors' => [
                Setting::get('color_primary', ''),
                Setting::get('color_accent', ''),
                Setting::get('font_family', ''),
            ],
        ];
        Setting::set(self::USER_PRESETS_KEY, json_encode($presets, JSON_UNESCAPED_UNICODE));
        Setting::set('design_preset', 'user:' . $slug);

        return $slug;
    }

    public static function deleteUserPreset(string $slug): bool
    {
        $presets = self::userPresets();
        if (!isset($presets[$slug])) {
            return false;
        }
        unset($presets[$slug]);
        Setting::set(self::USER_PRESETS_KEY, json_encode($presets, JSON_UNESCAPED_UNICODE));
        if (Setting::get('design_preset', '') === 'user:' . $slug) {
            Setting::set('design_preset', '');
        }

        return true;
    }

    public static function applyUserPreset(string $slug): bool
    {
        $presets = self::userPresets();
        if (!isset($presets[$slug])) {
            return false;
        }
        $preset = $presets[$slug];
        self::save((array) ($preset['values'] ?? []));

        // Палитра «Свои цвета»: восстанавливаем снапшот ручных значений.
        $values = (array) ($preset['values'] ?? []);
        $colors = (array) ($preset['colors'] ?? []);
        if (($values['palette'] ?? '') === 'custom' && count($colors) === 3) {
            if ($colors[0] !== '') { Setting::set('color_primary', (string) $colors[0]); }
            if ($colors[1] !== '') { Setting::set('color_accent', (string) $colors[1]); }
        }
        if (($values['font_style'] ?? '') === 'custom' && ($colors[2] ?? '') !== '') {
            Setting::set('font_family', (string) $colors[2]);
        }

        Setting::set('design_preset', 'user:' . $slug);

        return true;
    }

    /**
     * CSS-переменные для фронтенда на основе текущих значений.
     * @param array<string,string> $v
     */
    public static function cssVariables(array $v): string
    {
        $container = ['narrow' => '1080px', 'standard' => '1200px', 'wide' => '1360px', 'ultra' => '1560px', 'full' => 'none'][$v['container']] ?? '1200px';
        // Своя точная ширина имеет приоритет над пресетом (число трактуем как px).
        $custom = self::containerCustom();
        if ($custom !== '') {
            $container = $custom;
        }
        $radius = ['none' => '0px', 'small' => '8px', 'medium' => '14px', 'large' => '22px'][$v['radius']] ?? '14px';
        $gap = ['xs' => '8px', 'sm' => '16px', 'md' => '24px', 'lg' => '32px'][$v['card_gap']] ?? '24px';
        $section = ['compact' => '28px', 'standard' => '46px', 'spacious' => '72px'][$v['density']] ?? '46px';
        $btn = ['square' => '0px', 'rounded' => '10px', 'pill' => '999px'][$v['button']] ?? '10px';
        $fontSize = ['sm' => '15px', 'md' => '16px', 'lg' => '17px', 'xl' => '18px'][$v['font_size'] ?? 'md'] ?? '16px';
        $lineHeight = ['tight' => '1.45', 'normal' => '1.6', 'relaxed' => '1.8'][$v['line_height'] ?? 'normal'] ?? '1.6';
        $shadow = [
            'flat' => 'none',
            'soft' => '0 1px 3px rgba(16,24,40,.06), 0 6px 18px rgba(16,24,40,.05)',
            'elevated' => '0 10px 30px rgba(16,24,40,.12)',
        ][$v['card_style'] ?? 'soft'] ?? 'none';

        return sprintf(
            ':root{--container-max:%s;--radius:%s;--radius-sm:calc(%s * .6);--card-gap:%s;--section-pad:%s;--btn-radius:%s;--base-font-size:%s;--base-line-height:%s;--card-shadow:%s;}',
            $container,
            $radius,
            $radius,
            $gap,
            $section,
            $btn,
            $fontSize,
            $lineHeight,
            $shadow
        );
    }

    /** Классы для <body>, включающие макет каталога и стиль шапки. @param array<string,string> $v */
    public static function bodyClasses(array $v): string
    {
        return trim(sprintf(
            'design-catalog-%s design-header-%s design-sidebar-%s design-cards-%s design-search-%s design-detail-%s design-footer-%s design-mmenu-%s design-mheader-%s%s',
            preg_replace('/[^a-z_]/', '', $v['catalog_layout']),
            preg_replace('/[^a-z]/', '', $v['header_style']),
            preg_replace('/[^a-z]/', '', $v['sidebar_position'] ?? 'floating'),
            preg_replace('/[^a-z]/', '', $v['card_style'] ?? 'soft'),
            preg_replace('/[^a-z]/', '', $v['search_type'] ?? 'inline'),
            preg_replace('/[^a-z]/', '', $v['detail_layout'] ?? 'plain'),
            preg_replace('/[^a-z]/', '', $v['footer_style'] ?? 'columns'),
            preg_replace('/[^a-z]/', '', $v['mobile_menu'] ?? 'burger'),
            preg_replace('/[^a-z]/', '', $v['mobile_header'] ?? 'fixed'),
            $v['header_sticky'] === 'on' ? ' design-header-sticky' : ''
        ));
    }
}
