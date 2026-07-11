<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;

/**
 * Чтение и запись конфигурации шапки сайта (mini-app конструктор).
 * Хранится JSON-строкой в settings['header_config']. Любые прочитанные
 * данные сливаются с дефолтной структурой, поэтому старые/неполные JSON
 * никогда не приводят к ошибкам обращения к отсутствующим ключам.
 */
final class HeaderConfig
{
    /** Доступные макеты шапки (десктоп + мобильный). */
    public const LAYOUTS = ['stacked', 'inline', 'centered', 'drawer'];

    /**
     * Элементы-«кирпичики» верхнего ряда шапки для конструктора (расставляются
     * по зонам left/center/right). Логотип и меню размещаются отдельно
     * (logo_position и layout соответственно). value => подпись для админки.
     */
    public const ELEMENTS = [
        'search' => 'Поиск',
        'language' => 'Переключатель языков',
        'social' => 'Соцсети',
        'button' => 'Кнопка (CTA)',
        'theme' => 'Тёмная тема',
        'a11y' => 'Версия для слабовидящих',
        'phone' => 'Телефон',
        'email' => 'E-mail',
        'snippet' => 'Сниппет (текст/HTML)',
        'divider' => 'Разделитель',
    ];

    /** Стили верхней/утилитарной полосы шапки. */
    public const BAR_STYLES = ['navy', 'light', 'teal'];

    /** Высоты секций шапки. */
    public const HEIGHTS = ['slim', 'normal', 'tall'];

    /** Разделительные линии секций: во всю ширину / по ширине контейнера / нет. */
    public const BORDER_MODES = ['full', 'container', 'none'];

    /** Элементы, которые можно повторять в зонах (визуальные). Прочие — уникальны
     *  в пределах одной секции (но могут повторяться в разных секциях). */
    private const REPEATABLE = ['divider'];

    public const DEFAULTS = [
        'layout' => 'stacked',                // stacked | inline | centered | drawer
        'logo_position' => 'left',            // left | center
        'menu_position' => 'right',           // left | center | right
        // Поведение шапки: липкая (следует за прокруткой) и прозрачная
        // (накладывается на первый экран, при прокрутке становится сплошной).
        'sticky' => false,
        'transparent' => false,
        // Светлый (белый) вариант логотипа для прозрачной шапки.
        'logo_light' => '',
        // Логотип для конкретного языка (код => URL). Переопределяет общий
        // логотип (Настройки → Логотип) на страницах этого языка.
        'logo_by_lang' => [],
        // Светлый логотип для конкретного языка (прозрачная шапка).
        'logo_light_by_lang' => [],
        // Конструктор: раскладка элементов по зонам верхнего ряда (десктоп).
        'elements' => [
            'left' => [],
            'center' => [],
            'right' => ['search', 'language', 'theme', 'a11y'],
        ],
        // Отдельная раскладка для мобильной версии (компактнее по умолчанию).
        'elements_mobile' => [
            'left' => [],
            'center' => [],
            'right' => ['search', 'language'],
        ],
        'language_switcher' => [
            'enabled' => true,
            'format' => 'code',               // code | name | flag
        ],
        'social_buttons' => [],               // [{network, url}]
        'cta' => [
            'enabled' => false,
            'text' => '',
            'url' => '',
            'style' => 'filled',              // filled | outline
        ],
        // Pro Max: верхняя утилитарная полоса над шапкой (top section).
        'topbar' => [
            'enabled' => false,
            'style' => 'navy',                // из BAR_STYLES
            'show_mobile' => false,
            'height' => 'normal',             // из HEIGHTS
            'zones' => ['left' => [], 'center' => [], 'right' => []],
        ],
        // Высота основной секции (логотип + утилиты).
        'middlebar' => ['height' => 'normal'],
        // Pro Max: нижняя полоса (bottom section) — элементы рядом с меню.
        'bottombar' => [
            'height' => 'normal',
            'zones' => ['left' => [], 'center' => [], 'right' => []],
        ],
        // Разделительные линии между секциями.
        'borders' => 'full',
        // Значения для элементов «Телефон» и «E-mail».
        'contacts' => [
            'phone' => '',
            'email' => '',
        ],
        // Произвольный сниппет (HTML проходит санитайзер при сохранении).
        'snippet' => '',
    ];

    public static function get(): array
    {
        $raw = Setting::get('header_config', '');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return self::mergeDefaults($decoded);
    }

    public static function save(array $config): void
    {
        $clean = self::mergeDefaults($config);
        Setting::set('header_config', json_encode($clean, JSON_UNESCAPED_UNICODE));
    }

    /** Публичная нормализация конфигурации (валидация значений) без записи в БД. */
    public static function normalize(array $config): array
    {
        return self::mergeDefaults($config);
    }

    /**
     * Нормализует раскладку элементов по зонам: только известные типы;
     * неповторяемые (поиск, языки и т.п.) — по одному на всю шапку (первое
     * вхождение выигрывает), разделители можно повторять.
     *
     * @param array<string, mixed> $raw
     * @return array{left: list<string>, center: list<string>, right: list<string>}
     */
    /**
     * Карта «код языка => URL логотипа». Оставляем только активные языки и
     * непустые значения. Ключи фильтруем по формату кода языка.
     *
     * @return array<string,string>
     */
    private static function normalizeLangMap(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $active = [];
        try {
            $active = \App\Models\Language::activeCodes();
        } catch (\Throwable $e) {
            // Таблица языков недоступна — не фильтруем по активности.
        }
        $map = [];
        foreach ($raw as $code => $url) {
            $code = strtolower(trim((string) $code));
            if ($code === '' || !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $code)) {
                continue;
            }
            if ($active !== [] && !in_array($code, $active, true)) {
                continue;
            }
            $url = trim((string) $url);
            if ($url !== '') {
                $map[$code] = $url;
            }
        }

        return $map;
    }

    private static function normalizeZones(array $raw): array
    {
        $seen = [];
        $zones = ['left' => [], 'center' => [], 'right' => []];
        foreach (array_keys($zones) as $zone) {
            foreach ((array) ($raw[$zone] ?? []) as $el) {
                $el = (string) $el;
                if (!isset(self::ELEMENTS[$el])) {
                    continue;
                }
                if (!in_array($el, self::REPEATABLE, true)) {
                    if (isset($seen[$el])) {
                        continue;
                    }
                    $seen[$el] = true;
                }
                $zones[$zone][] = $el;
            }
        }

        return $zones;
    }

    private static function mergeDefaults(array $config): array
    {
        $result = self::DEFAULTS;

        $result['layout'] = in_array($config['layout'] ?? '', self::LAYOUTS, true)
            ? $config['layout'] : self::DEFAULTS['layout'];

        $result['logo_position'] = in_array($config['logo_position'] ?? '', ['left', 'center'], true)
            ? $config['logo_position'] : self::DEFAULTS['logo_position'];

        $result['menu_position'] = in_array($config['menu_position'] ?? '', ['left', 'center', 'right'], true)
            ? $config['menu_position'] : self::DEFAULTS['menu_position'];

        $result['sticky'] = !empty($config['sticky']);
        $result['transparent'] = !empty($config['transparent']);
        $result['logo_light'] = trim((string) ($config['logo_light'] ?? ''));
        $result['logo_by_lang'] = self::normalizeLangMap($config['logo_by_lang'] ?? null);
        $result['logo_light_by_lang'] = self::normalizeLangMap($config['logo_light_by_lang'] ?? null);

        // Конструктор: раскладки элементов по зонам для десктопа и мобильного.
        $result['elements'] = isset($config['elements']) && is_array($config['elements'])
            ? self::normalizeZones($config['elements'])
            : self::DEFAULTS['elements'];
        $result['elements_mobile'] = isset($config['elements_mobile']) && is_array($config['elements_mobile'])
            ? self::normalizeZones($config['elements_mobile'])
            : self::DEFAULTS['elements_mobile'];

        $ls = (array) ($config['language_switcher'] ?? []);
        $result['language_switcher'] = [
            'enabled' => !empty($ls['enabled']),
            'format' => in_array($ls['format'] ?? '', ['code', 'name', 'flag'], true) ? $ls['format'] : 'code',
        ];

        $result['social_buttons'] = [];
        foreach ((array) ($config['social_buttons'] ?? []) as $btn) {
            $network = trim((string) ($btn['network'] ?? ''));
            $url = trim((string) ($btn['url'] ?? ''));
            if ($network !== '' && $url !== '') {
                $result['social_buttons'][] = ['network' => $network, 'url' => $url];
            }
        }

        $cta = (array) ($config['cta'] ?? []);
        $result['cta'] = [
            'enabled' => !empty($cta['enabled']),
            'text' => trim((string) ($cta['text'] ?? '')),
            'url' => trim((string) ($cta['url'] ?? '')),
            'style' => in_array($cta['style'] ?? '', ['filled', 'outline'], true) ? $cta['style'] : 'filled',
        ];

        // Pro Max: секции top/bottom.
        $height = static fn (mixed $v): string => in_array($v, self::HEIGHTS, true) ? $v : 'normal';
        $topbar = (array) ($config['topbar'] ?? []);
        $result['topbar'] = [
            'enabled' => !empty($topbar['enabled']),
            'style' => in_array($topbar['style'] ?? '', self::BAR_STYLES, true) ? $topbar['style'] : 'navy',
            'show_mobile' => !empty($topbar['show_mobile']),
            'height' => $height($topbar['height'] ?? ''),
            'zones' => isset($topbar['zones']) && is_array($topbar['zones'])
                ? self::normalizeZones($topbar['zones'])
                : self::DEFAULTS['topbar']['zones'],
        ];
        $result['middlebar'] = ['height' => $height(((array) ($config['middlebar'] ?? []))['height'] ?? '')];
        $bottombar = (array) ($config['bottombar'] ?? []);
        $result['bottombar'] = [
            'height' => $height($bottombar['height'] ?? ''),
            'zones' => isset($bottombar['zones']) && is_array($bottombar['zones'])
                ? self::normalizeZones($bottombar['zones'])
                : self::DEFAULTS['bottombar']['zones'],
        ];
        $result['borders'] = in_array($config['borders'] ?? '', self::BORDER_MODES, true)
            ? $config['borders'] : 'full';

        $contacts = (array) ($config['contacts'] ?? []);
        $result['contacts'] = [
            'phone' => trim((string) ($contacts['phone'] ?? '')),
            'email' => trim((string) ($contacts['email'] ?? '')),
        ];

        // Сниппет: строгий allowlist-санитайзер (без <script>/on*-обработчиков).
        $snippet = (string) ($config['snippet'] ?? '');
        $result['snippet'] = $snippet !== '' ? HtmlSanitizer::sanitize($snippet) : '';

        return $result;
    }
}
