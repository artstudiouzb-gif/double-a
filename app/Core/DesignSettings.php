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
     * 'custom' использует ручные цвета из этого же раздела «Дизайн».
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
        'inter-tight' => ['Inter Tight', "'Inter Tight', system-ui, sans-serif", 'Inter+Tight:wght@400;500;600;700'],
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
            'hint' => 'Основной и акцентный цвета сайта. «Свои цвета» — ручные значения ниже.',
            'group' => 'Цвета и шрифт',
            'choices' => ['gov_blue' => 'Гос-синий', 'classic_red' => 'Классика', 'emerald' => 'Изумруд', 'graphite' => 'Графит', 'violet' => 'Индиго', 'custom' => 'Свои цвета'],
            'default' => 'custom',
        ],
        'font_style' => [
            'label' => 'Шрифт сайта',
            'hint' => 'Основной шрифт выбирается в едином списке базовых, внешних и собственных шрифтов ниже.',
            'group' => 'Цвета и шрифт',
            'choices' => ['pt' => 'PT Sans', 'inter' => 'Inter', 'system' => 'Системный', 'serif' => 'С засечками', 'custom' => 'Свой шрифт'],
            'default' => 'custom',
        ],
        'site_template' => [
            'label' => 'Шаблон сайта',
            'hint' => 'Основной визуальный стиль и сетка сайта. «Официальный» — классическая гос-тема с засечками. «Современный» — трендовый дизайн без засечек с просторным расположением блоков и мягкими тенями.',
            'group' => 'Общие',
            'choices' => ['gov' => 'Официальный', 'modern_gov' => 'Современный'],
            'default' => 'gov',
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
            'hint' => 'Радиус карточек и крупных блоков. Ниже можно задать точное значение.',
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
        'type_scale' => [
            'label' => 'Масштаб заголовков',
            'hint' => 'Плавающие — размер плавно растёт с шириной экрана. Статичные — фиксированный размер (десктоп) с одним мобильным брейкпоинтом.',
            'group' => 'Типографика',
            'choices' => ['fluid' => 'Плавающие', 'static' => 'Статичные'],
            'default' => 'fluid',
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
            'values' => ['site_template' => 'gov', 'container' => 'standard', 'radius' => 'small', 'card_gap' => 'sm', 'density' => 'standard', 'font_size' => 'md', 'line_height' => 'normal', 'button' => 'rounded', 'card_style' => 'soft', 'sidebar_position' => 'floating', 'catalog_layout' => 'cards_lg', 'header_style' => 'light', 'header_sticky' => 'on', 'search_type' => 'inline', 'detail_layout' => 'plain', 'footer_style' => 'columns', 'mobile_menu' => 'burger', 'mobile_header' => 'fixed', 'type_scale' => 'fluid', 'palette' => 'gov_blue', 'font_style' => 'system'],
        ],
        'modern' => [
            'label' => 'Современный',
            'desc' => 'Крупные скругления, воздух, акцентная шапка.',
            'values' => ['site_template' => 'modern_gov', 'container' => 'wide', 'radius' => 'large', 'card_gap' => 'md', 'density' => 'spacious', 'font_size' => 'lg', 'line_height' => 'relaxed', 'button' => 'pill', 'card_style' => 'elevated', 'sidebar_position' => 'floating', 'catalog_layout' => 'cards_lg', 'header_style' => 'accent', 'header_sticky' => 'on', 'search_type' => 'overlay', 'detail_layout' => 'sidebar', 'footer_style' => 'columns', 'mobile_menu' => 'burger', 'mobile_header' => 'fixed', 'type_scale' => 'fluid', 'palette' => 'violet', 'font_style' => 'inter'],
        ],
        'minimal' => [
            'label' => 'Минимал',
            'desc' => 'Прямые углы, максимум воздуха, список в каталоге.',
            'values' => ['site_template' => 'gov', 'container' => 'narrow', 'radius' => 'none', 'card_gap' => 'md', 'density' => 'spacious', 'font_size' => 'md', 'line_height' => 'normal', 'button' => 'square', 'card_style' => 'flat', 'sidebar_position' => 'fixed', 'catalog_layout' => 'list', 'header_style' => 'light', 'header_sticky' => 'off', 'search_type' => 'overlay', 'detail_layout' => 'plain', 'footer_style' => 'minimal', 'mobile_menu' => 'burger', 'mobile_header' => 'static', 'type_scale' => 'fluid', 'palette' => 'graphite', 'font_style' => 'serif'],
        ],
        'compact' => [
            'label' => 'Компактный',
            'desc' => 'Плотная сетка, маленькие карточки — много данных.',
            'values' => ['site_template' => 'gov', 'container' => 'standard', 'radius' => 'small', 'card_gap' => 'xs', 'density' => 'compact', 'font_size' => 'sm', 'line_height' => 'tight', 'button' => 'rounded', 'card_style' => 'soft', 'sidebar_position' => 'fixed', 'catalog_layout' => 'cards_sm', 'header_style' => 'light', 'header_sticky' => 'on', 'search_type' => 'inline', 'detail_layout' => 'sidebar', 'footer_style' => 'columns', 'mobile_menu' => 'burger', 'mobile_header' => 'fixed', 'type_scale' => 'fluid', 'palette' => 'classic_red', 'font_style' => 'system'],
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

    /**
     * Единое значение выбора основного шрифта для формы дизайна.
     * Старые design_font_style/design_font_google_body остаются форматом
     * хранения, поэтому обновление не требует миграции базы.
     */
    public static function bodyFontChoice(): string
    {
        $google = (string) Setting::get('design_font_google_body', '');
        if ($google !== '' && isset(self::GOOGLE_FONTS[$google])) {
            return 'google:' . $google;
        }

        $style = (string) Setting::get('design_font_style', 'custom');
        return 'style:' . (isset(self::FONTS[$style]) ? $style : 'custom');
    }

    /**
     * Нормализует единый выбор шрифта в совместимые внутренние поля.
     * @return array{font_style:string,font_google_body:string}
     */
    public static function normalizeBodyFontChoice(string $choice): array
    {
        if (str_starts_with($choice, 'google:')) {
            $slug = substr($choice, 7);
            if (isset(self::GOOGLE_FONTS[$slug])) {
                return ['font_style' => 'system', 'font_google_body' => $slug];
            }
        }
        if (str_starts_with($choice, 'style:')) {
            $style = substr($choice, 6);
            if (isset(self::FONTS[$style])) {
                return ['font_style' => $style, 'font_google_body' => ''];
            }
        }

        return ['font_style' => 'custom', 'font_google_body' => ''];
    }

    /** Точный базовый размер текста, 12–24px; пусто — значение пресета. */
    public static function fontSizeCustom(): string
    {
        return self::normalizePixelValue((string) Setting::get('design_font_size_custom', ''), 12, 24);
    }

    public static function normalizeFontSize(string $raw): string
    {
        return self::normalizePixelValue($raw, 12, 24);
    }

    /** Точное скругление, 0–48px; пусто — значение пресета. */
    public static function radiusCustom(): string
    {
        return self::normalizePixelValue((string) Setting::get('design_radius_custom', ''), 0, 48);
    }

    public static function normalizeRadius(string $raw): string
    {
        return self::normalizePixelValue($raw, 0, 48);
    }

    /**
     * Размеры шрифта по элементам: ключ формы fs_* => [подпись, CSS-селектор,
     * placeholder-значение темы]. Пустое значение — размер темы не трогаем.
     * Правила выводятся с !important, чтобы предсказуемо перекрывать
     * компонентные clamp()-размеры тем (панель a11y всё равно сильнее).
     */
    public const TYPO_SIZES = [
        'fs_h1' => ['Заголовок H1', 'h1', '42'],
        'fs_h2' => ['Заголовок H2', 'h2', '32'],
        'fs_h3' => ['Заголовок H3', 'h3', '24'],
        'fs_h4' => ['Заголовок H4', 'h4', '20'],
        'fs_h5' => ['Заголовок H5', 'h5', '18'],
        'fs_small' => ['Мелкий текст (small)', 'small', '13'],
        'fs_btn' => ['Кнопки', '.block-cta__button, .btn-cta, .btn', '15'],
        'fs_menu' => ['Главное меню', '.site-menu__link', '13'],
        'fs_topbar' => ['Верхняя панель', '.site-topbar', '13'],
    ];

    /** Заданные размеры по элементам: ключ => '17px' или '' (не задан). @return array<string,string> */
    public static function typographySizes(): array
    {
        $sizes = [];
        foreach (self::TYPO_SIZES as $key => $_) {
            $sizes[$key] = self::normalizeFsSize((string) Setting::get('design_' . $key, ''));
        }

        return $sizes;
    }

    /** Нормализует размер шрифта элемента (8–96px); '' — не задан/невалиден. */
    public static function normalizeFsSize(string $raw): string
    {
        return self::normalizePixelValue($raw, 8, 96);
    }

    /** CSS-правила для заданных размеров по элементам ('' — ничего не задано). */
    public static function typographyCss(): string
    {
        $rules = '';
        foreach (self::typographySizes() as $key => $size) {
            if ($size !== '') {
                $rules .= self::TYPO_SIZES[$key][1] . '{font-size:' . $size . ' !important;}';
            }
        }

        return $rules;
    }

    /** Точный межстрочный интервал, 1–2.5 (без единиц); пусто — значение пресета. */
    public static function lineHeightCustom(): string
    {
        return self::normalizeLineHeight((string) Setting::get('design_line_height_custom', ''));
    }

    public static function normalizeLineHeight(string $raw): string
    {
        $raw = trim(str_replace(',', '.', $raw));
        if ($raw === '' || !preg_match('/^\d(?:\.\d{1,2})?$/', $raw)) {
            return '';
        }
        $value = (float) $raw;
        if ($value < 1 || $value > 2.5) {
            return '';
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private static function normalizePixelValue(string $raw, float $min, float $max): string
    {
        $raw = strtolower(trim(str_replace(',', '.', $raw)));
        $raw = preg_replace('/px$/', '', $raw) ?? '';
        if ($raw === '' || !preg_match('/^\d{1,3}(?:\.\d)?$/', $raw)) {
            return '';
        }
        $value = (float) $raw;
        if ($value < $min || $value > $max) {
            return '';
        }
        $normalized = rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

        return $normalized . 'px';
    }

    /**
     * Ручные значения внешнего вида. Хранятся отдельно от материализованных
     * рабочих ключей цветов и font_family, чтобы готовый пресет не затирал настройки
     * пользователя при последующем возврате к варианту «Свои…».
     *
     * @return array{color_primary:string,color_accent:string,font_family:string}
     */
    public static function customAppearance(): array
    {
        return [
            'color_primary' => SettingsValidator::hexColor(
                (string) Setting::get('design_custom_color_primary', Setting::get('color_primary', '#173a63')),
                '#173a63'
            ),
            'color_accent' => SettingsValidator::hexColor(
                (string) Setting::get('design_custom_color_accent', Setting::get('color_accent', '#17999b')),
                '#17999b'
            ),
            'font_family' => (string) Setting::get(
                'design_custom_font_family',
                Setting::get('font_family', "'PT Sans', system-ui, sans-serif")
            ),
        ];
    }

    /** @return array{bg_primary:string,bg_surface:string,text_main:string,text_muted:string,border_color:string} */
    public static function semanticColors(): array
    {
        $defaults = [
            'bg_primary' => '#ffffff',
            'bg_surface' => '#f4f5f7',
            'text_main' => '#1a1a1a',
            'text_muted' => '#666666',
            'border_color' => '#e1e3e8',
        ];
        $colors = [];
        foreach ($defaults as $key => $fallback) {
            $colors[$key] = SettingsValidator::hexColor(
                (string) Setting::get('design_semantic_' . $key, $fallback),
                $fallback
            );
        }

        return $colors;
    }

    /** @return array{space_small:string,space_premium:string,space_max:string} */
    public static function semanticSpacings(): array
    {
        $defaults = [
            'space_small' => 'clamp(14px, 2.5vw, 24px)',
            'space_premium' => 'clamp(28px, 4vw, 56px)',
            'space_max' => 'clamp(40px, 5vw, 76px)',
        ];
        $spacings = [];
        foreach ($defaults as $key => $fallback) {
            $spacings[$key] = SettingsValidator::safeCssValue(
                (string) Setting::get('design_spacing_' . $key, $fallback),
                $fallback
            );
        }

        return $spacings;
    }

    public static function save(array $input): void
    {
        // Новая форма присылает один выбор вместо двух конкурирующих полей.
        // Прямые font_style/font_google_body по-прежнему принимаются от пресетов
        // и старых форм.
        if (array_key_exists('font_body_choice', $input)) {
            $input = array_merge($input, self::normalizeBodyFontChoice((string) $input['font_body_choice']));
        }
        foreach (self::OPTIONS as $key => $opt) {
            $val = self::sanitize($key, (string) ($input[$key] ?? ''));
            Setting::set('design_' . $key, (string) $val);
        }
        // Своя ширина контейнера — отдельное свободное поле (не из choices).
        Setting::set('design_container_custom', self::normalizeWidth(trim((string) ($input['container_custom'] ?? ''))));
        if (array_key_exists('font_size_custom', $input)) {
            Setting::set('design_font_size_custom', self::normalizeFontSize((string) $input['font_size_custom']));
        }
        if (array_key_exists('radius_custom', $input)) {
            Setting::set('design_radius_custom', self::normalizeRadius((string) $input['radius_custom']));
        }
        if (array_key_exists('line_height_custom', $input)) {
            Setting::set('design_line_height_custom', self::normalizeLineHeight((string) $input['line_height_custom']));
        }
        foreach (array_keys(self::TYPO_SIZES) as $fsKey) {
            if (array_key_exists($fsKey, $input)) {
                Setting::set('design_' . $fsKey, self::normalizeFsSize((string) $input[$fsKey]));
            }
        }
        if (array_key_exists('menu_divider_color', $input)) {
            Setting::set('design_menu_divider_color', SettingsValidator::hexColor((string) $input['menu_divider_color'], '#ffffff'));
        }
        if (array_key_exists('menu_divider_color_use', $input)) {
            Setting::set('design_menu_divider_color_use', (string) $input['menu_divider_color_use'] === '1' ? '1' : '0');
        } else {
            Setting::set('design_menu_divider_color_use', '0');
        }
        if (array_key_exists('menu_divider_thickness', $input)) {
            Setting::set('design_menu_divider_thickness', self::normalizePixelValue((string) $input['menu_divider_thickness'], 0, 10));
        }
        if (array_key_exists('menu_divider_height', $input)) {
            Setting::set('design_menu_divider_height', self::normalizePixelValue((string) $input['menu_divider_height'], 2, 100));
        }

        // Ручные цвета и шрифт сохраняются отдельно от активного пресета.
        // При первом сохранении старые рабочие ключи цветов и font_family используются как
        // значения по умолчанию — миграция базы не требуется.
        if (array_key_exists('color_primary', $input)) {
            Setting::set('design_custom_color_primary', SettingsValidator::hexColor(
                (string) $input['color_primary'],
                self::customAppearance()['color_primary']
            ));
        }
        if (array_key_exists('color_accent', $input)) {
            Setting::set('design_custom_color_accent', SettingsValidator::hexColor(
                (string) $input['color_accent'],
                self::customAppearance()['color_accent']
            ));
        }
        $semantic = self::semanticColors();
        foreach ($semantic as $key => $current) {
            if (array_key_exists($key, $input)) {
                Setting::set(
                    'design_semantic_' . $key,
                    SettingsValidator::hexColor((string) $input[$key], $current)
                );
            }
        }
        $spacings = self::semanticSpacings();
        foreach ($spacings as $key => $current) {
            if (array_key_exists($key, $input)) {
                Setting::set(
                    'design_spacing_' . $key,
                    SettingsValidator::safeCssValue((string) $input[$key], $current)
                );
            }
        }

        if (array_key_exists('font_family', $input)) {
            $family = mb_substr(trim((string) $input['font_family']), 0, 200);
            if ($family !== '') {
                Setting::set('design_custom_font_family', $family);
            }
        }
        if (array_key_exists('font_face_name', $input)) {
            $face = preg_replace('/[^a-zA-Z0-9 _-]/', '', trim((string) $input['font_face_name'])) ?? '';
            Setting::set('font_face_name', mb_substr($face, 0, 80));
        }
        if (array_key_exists('font_url', $input)) {
            $url = mb_substr(trim((string) $input['font_url']), 0, 500);
            Setting::set('font_url', $url === '' || UrlGuard::isSafeLink($url) ? $url : '');
        }
        if (array_key_exists('default_theme', $input)) {
            $theme = in_array($input['default_theme'], ['light', 'dark', 'auto'], true)
                ? (string) $input['default_theme']
                : 'light';
            Setting::set('default_theme', $theme);
        }

        // Запоминаем выбранные Google-шрифты до материализации. Пустое
        // значение отключает Google-шрифт для соответствующей роли.
        foreach (['heading', 'body'] as $role) {
            $inputKey = 'font_google_' . $role;
            if (!array_key_exists($inputKey, $input)) {
                continue;
            }
            $slug = (string) $input[$inputKey];
            Setting::set(
                'design_font_google_' . $role,
                $slug !== '' && isset(self::GOOGLE_FONTS[$slug]) ? $slug : ''
            );
        }

        // Материализация палитры/шрифта в реальные настройки сайта
        // (color_primary/color_accent/font_family, их читает фронтенд).
        $custom = self::customAppearance();
        $palette = (string) Setting::get('design_palette', 'custom');
        if ($palette !== 'custom' && isset(self::PALETTES[$palette])) {
            Setting::set('color_primary', self::PALETTES[$palette][1]);
            Setting::set('color_accent', self::PALETTES[$palette][2]);
        } else {
            Setting::set('color_primary', $custom['color_primary']);
            Setting::set('color_accent', $custom['color_accent']);
        }
        $font = (string) Setting::get('design_font_style', 'custom');
        if ($font !== 'custom' && isset(self::FONTS[$font])) {
            Setting::set('font_family', self::FONTS[$font][1]);
        } else {
            Setting::set('font_family', $custom['font_family']);
        }

        // Google-шрифты имеют явный приоритет над базовой ролью. Отключение
        // Google-шрифта текста возвращает выбранный выше пресет/свой стек.
        $headingSlug = (string) Setting::get('design_font_google_heading', '');
        Setting::set(
            'font_heading',
            $headingSlug !== '' && isset(self::GOOGLE_FONTS[$headingSlug])
                ? self::GOOGLE_FONTS[$headingSlug][1]
                : "'PT Serif', Georgia, 'Times New Roman', serif"
        );
        $bodySlug = (string) Setting::get('design_font_google_body', '');
        if ($bodySlug !== '' && isset(self::GOOGLE_FONTS[$bodySlug])) {
            Setting::set('font_family', self::GOOGLE_FONTS[$bodySlug][1]);
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
        // Встроенный пресет должен полностью определять типографику, поэтому
        // отключаем ранее выбранные Google-шрифты, которые иначе имели бы
        // приоритет над шрифтом пресета.
        self::save(array_merge(self::PRESETS[$preset]['values'], [
            'font_google_heading' => '',
            'font_google_body' => '',
            'font_size_custom' => '',
            'radius_custom' => '',
            'line_height_custom' => '',
        ], array_fill_keys(array_keys(self::TYPO_SIZES), '')));
        Setting::set('design_preset', $preset);

        return true;
    }

    // --- Пользовательские конфигурации (сохранённые администратором) ---

    private const USER_PRESETS_KEY = 'design_user_presets';
    private const USER_PRESETS_MAX = 10;

    /** @return array<string,array{label:string,values:array<string,string>,colors?:array<int,string>,appearance?:array<string,string>}> */
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

        $custom = self::customAppearance();
        $semantic = self::semanticColors();
        $presets[$slug] = [
            'label' => $name,
            'values' => self::current(),
            'colors' => [
                $custom['color_primary'],
                $custom['color_accent'],
                $custom['font_family'],
            ],
            'appearance' => [
                'color_primary' => $custom['color_primary'],
                'color_accent' => $custom['color_accent'],
                'font_family' => $custom['font_family'],
                'font_face_name' => Setting::get('font_face_name', ''),
                'font_url' => Setting::get('font_url', ''),
                'default_theme' => Setting::get('default_theme', 'light'),
                'font_google_heading' => Setting::get('design_font_google_heading', ''),
                'font_google_body' => Setting::get('design_font_google_body', ''),
                'font_size_custom' => Setting::get('design_font_size_custom', ''),
                'radius_custom' => Setting::get('design_radius_custom', ''),
                'line_height_custom' => Setting::get('design_line_height_custom', ''),
            ] + array_combine(
                array_keys(self::TYPO_SIZES),
                array_map(static fn (string $k): string => (string) Setting::get('design_' . $k, ''), array_keys(self::TYPO_SIZES))
            ) + [
                'bg_primary' => $semantic['bg_primary'],
                'bg_surface' => $semantic['bg_surface'],
                'text_main' => $semantic['text_main'],
                'text_muted' => $semantic['text_muted'],
                'border_color' => $semantic['border_color'],
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
        $values = (array) ($preset['values'] ?? []);
        $colors = (array) ($preset['colors'] ?? []);
        $appearance = (array) ($preset['appearance'] ?? []);
        // Сначала восстанавливаем ручные значения, затем применяем через
        // обычный save. Так они сохраняются и после переключения пресетов.
        if (($values['palette'] ?? '') === 'custom' && count($colors) === 3) {
            if ($colors[0] !== '') { Setting::set('design_custom_color_primary', (string) $colors[0]); }
            if ($colors[1] !== '') { Setting::set('design_custom_color_accent', (string) $colors[1]); }
        }
        if (($values['font_style'] ?? '') === 'custom' && ($colors[2] ?? '') !== '') {
            Setting::set('design_custom_font_family', (string) $colors[2]);
        }
        // Новые пресеты хранят весь единый блок оформления; colors остаётся
        // fallback для конфигураций, созданных до унификации.
        $appearanceInput = array_intersect_key($appearance, array_flip(array_merge([
            'color_primary', 'color_accent', 'font_family', 'font_face_name',
            'font_url', 'default_theme', 'font_google_heading', 'font_google_body',
            'font_size_custom', 'radius_custom', 'line_height_custom',
            'bg_primary', 'bg_surface', 'text_main', 'text_muted', 'border_color',
        ], array_keys(self::TYPO_SIZES))));
        // Старые пользовательские конфигурации не знали об этих полях: при
        // их применении сбрасываем текущие переопределения, а не наследуем их.
        $appearanceInput = array_merge([
            'font_google_heading' => '',
            'font_google_body' => '',
            'font_size_custom' => '',
            'radius_custom' => '',
            'line_height_custom' => '',
        ], array_fill_keys(array_keys(self::TYPO_SIZES), ''), $appearanceInput);
        self::save(array_merge($values, $appearanceInput));

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
        $customRadius = self::radiusCustom();
        if ($customRadius !== '') {
            $radius = $customRadius;
        }
        $gap = ['xs' => '8px', 'sm' => '16px', 'md' => '24px', 'lg' => '32px'][$v['card_gap']] ?? '24px';
        $section = ['compact' => '28px', 'standard' => '46px', 'spacious' => '72px'][$v['density']] ?? '46px';
        $btn = ['square' => '0px', 'rounded' => '10px', 'pill' => '999px'][$v['button']] ?? '10px';
        if ($customRadius !== '' && ($v['button'] ?? 'rounded') === 'rounded') {
            $btn = $customRadius;
        }
        $fontSize = ['sm' => '15px', 'md' => '16px', 'lg' => '17px', 'xl' => '18px'][$v['font_size'] ?? 'md'] ?? '16px';
        $customFontSize = self::fontSizeCustom();
        if ($customFontSize !== '') {
            $fontSize = $customFontSize;
        }
        $lineHeight = ['tight' => '1.45', 'normal' => '1.6', 'relaxed' => '1.8'][$v['line_height'] ?? 'normal'] ?? '1.6';
        $customLineHeight = self::lineHeightCustom();
        if ($customLineHeight !== '') {
            $lineHeight = $customLineHeight;
        }
        $shadow = [
            'flat' => 'none',
            'soft' => '0 1px 3px rgba(16,24,40,.06), 0 6px 18px rgba(16,24,40,.05)',
            'elevated' => '0 10px 30px rgba(16,24,40,.12)',
        ][$v['card_style'] ?? 'soft'] ?? 'none';

        $divColor = (string) Setting::get('design_menu_divider_color_use', '0') === '1'
            ? (string) Setting::get('design_menu_divider_color', '')
            : '';
        if ($divColor === '') {
            $divColor = 'color-mix(in srgb, currentColor 35%, transparent)';
        }

        $divThickness = (string) Setting::get('design_menu_divider_thickness', '');
        if ($divThickness === '') {
            $divThickness = '1px';
        }

        $divHeight = (string) Setting::get('design_menu_divider_height', '');
        if ($divHeight === '') {
            $divHeight = '18px';
        }

        // Точечные размеры по элементам (типографика) дописываются после :root —
        // строка целиком выводится внутри <style> в шапке.
        return self::typographyCss() . sprintf(
            ':root{--container-max:%s;--radius:%s;--radius-sm:calc(%s * .6);--card-gap:%s;--section-pad:%s;--btn-radius:%s;--base-font-size:%s;--base-line-height:%s;--card-shadow:%s;--menu-divider-color:%s;--menu-divider-width:%s;--menu-divider-height:%s;}',
            $container,
            $radius,
            $radius,
            $gap,
            $section,
            $btn,
            $fontSize,
            $lineHeight,
            $shadow,
            $divColor,
            $divThickness,
            $divHeight
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
        )) . (($v['type_scale'] ?? 'fluid') === 'static' ? ' design-type-static' : '');
    }
}
