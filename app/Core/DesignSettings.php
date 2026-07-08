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
    public const OPTIONS = [
        'container' => [
            'label' => 'Ширина контейнера',
            'hint' => 'Максимальная ширина основного содержимого.',
            'group' => 'Общие',
            'choices' => ['narrow' => 'Узкий', 'standard' => 'Стандарт', 'wide' => 'Широкий'],
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
            'values' => ['container' => 'standard', 'radius' => 'small', 'card_gap' => 'sm', 'density' => 'standard', 'button' => 'rounded', 'card_style' => 'soft', 'sidebar_position' => 'floating', 'catalog_layout' => 'cards_lg', 'header_style' => 'light', 'header_sticky' => 'on', 'search_type' => 'inline', 'detail_layout' => 'plain', 'footer_style' => 'columns', 'mobile_menu' => 'burger', 'mobile_header' => 'fixed'],
        ],
        'modern' => [
            'label' => 'Современный',
            'desc' => 'Крупные скругления, воздух, акцентная шапка.',
            'values' => ['container' => 'wide', 'radius' => 'large', 'card_gap' => 'md', 'density' => 'spacious', 'button' => 'pill', 'card_style' => 'elevated', 'sidebar_position' => 'floating', 'catalog_layout' => 'cards_lg', 'header_style' => 'accent', 'header_sticky' => 'on', 'search_type' => 'overlay', 'detail_layout' => 'sidebar', 'footer_style' => 'columns', 'mobile_menu' => 'burger', 'mobile_header' => 'fixed'],
        ],
        'minimal' => [
            'label' => 'Минимал',
            'desc' => 'Прямые углы, максимум воздуха, список в каталоге.',
            'values' => ['container' => 'narrow', 'radius' => 'none', 'card_gap' => 'md', 'density' => 'spacious', 'button' => 'square', 'card_style' => 'flat', 'sidebar_position' => 'fixed', 'catalog_layout' => 'list', 'header_style' => 'light', 'header_sticky' => 'off', 'search_type' => 'overlay', 'detail_layout' => 'plain', 'footer_style' => 'minimal', 'mobile_menu' => 'burger', 'mobile_header' => 'static'],
        ],
        'compact' => [
            'label' => 'Компактный',
            'desc' => 'Плотная сетка, маленькие карточки — много данных.',
            'values' => ['container' => 'standard', 'radius' => 'small', 'card_gap' => 'xs', 'density' => 'compact', 'button' => 'rounded', 'card_style' => 'soft', 'sidebar_position' => 'fixed', 'catalog_layout' => 'cards_sm', 'header_style' => 'light', 'header_sticky' => 'on', 'search_type' => 'inline', 'detail_layout' => 'sidebar', 'footer_style' => 'columns', 'mobile_menu' => 'burger', 'mobile_header' => 'fixed'],
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
    public static function save(array $input): void
    {
        foreach (self::OPTIONS as $key => $opt) {
            $val = self::sanitize($key, (string) ($input[$key] ?? ''));
            Setting::set('design_' . $key, (string) $val);
        }
    }

    /** Применяет готовую конфигурацию. */
    public static function applyPreset(string $preset): bool
    {
        if (!isset(self::PRESETS[$preset])) {
            return false;
        }
        self::save(self::PRESETS[$preset]['values']);
        Setting::set('design_preset', $preset);

        return true;
    }

    /**
     * CSS-переменные для фронтенда на основе текущих значений.
     * @param array<string,string> $v
     */
    public static function cssVariables(array $v): string
    {
        $container = ['narrow' => '1080px', 'standard' => '1200px', 'wide' => '1360px'][$v['container']] ?? '1200px';
        $radius = ['none' => '0px', 'small' => '8px', 'medium' => '14px', 'large' => '22px'][$v['radius']] ?? '14px';
        $gap = ['xs' => '8px', 'sm' => '16px', 'md' => '24px', 'lg' => '32px'][$v['card_gap']] ?? '24px';
        $section = ['compact' => '28px', 'standard' => '46px', 'spacious' => '72px'][$v['density']] ?? '46px';
        $btn = ['square' => '0px', 'rounded' => '10px', 'pill' => '999px'][$v['button']] ?? '10px';
        $shadow = [
            'flat' => 'none',
            'soft' => '0 1px 3px rgba(16,24,40,.06), 0 6px 18px rgba(16,24,40,.05)',
            'elevated' => '0 10px 30px rgba(16,24,40,.12)',
        ][$v['card_style'] ?? 'soft'] ?? 'none';

        return sprintf(
            ':root{--container-max:%s;--radius:%s;--radius-sm:calc(%s * .6);--card-gap:%s;--section-pad:%s;--btn-radius:%s;--card-shadow:%s;}',
            $container,
            $radius,
            $radius,
            $gap,
            $section,
            $btn,
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
