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
        'divider' => 'Разделитель',
    ];

    /** Элементы, которые можно повторять в зонах (визуальные). Прочие — уникальны. */
    private const REPEATABLE = ['divider'];

    public const DEFAULTS = [
        'layout' => 'stacked',                // stacked | inline | centered | drawer
        'logo_position' => 'left',            // left | center
        'menu_position' => 'right',           // left | center | right
        // Конструктор: раскладка элементов по зонам верхнего ряда.
        'elements' => [
            'left' => [],
            'center' => [],
            'right' => ['search', 'language', 'theme', 'a11y'],
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

    private static function mergeDefaults(array $config): array
    {
        $result = self::DEFAULTS;

        $result['layout'] = in_array($config['layout'] ?? '', self::LAYOUTS, true)
            ? $config['layout'] : self::DEFAULTS['layout'];

        $result['logo_position'] = in_array($config['logo_position'] ?? '', ['left', 'center'], true)
            ? $config['logo_position'] : self::DEFAULTS['logo_position'];

        $result['menu_position'] = in_array($config['menu_position'] ?? '', ['left', 'center', 'right'], true)
            ? $config['menu_position'] : self::DEFAULTS['menu_position'];

        // Конструктор: раскладка элементов по зонам. Оставляем только известные
        // типы; неповторяемые элементы (поиск, языки и т.п.) — по одному на всю
        // шапку (первое вхождение выигрывает), разделители можно повторять.
        if (isset($config['elements']) && is_array($config['elements'])) {
            $seen = [];
            $zones = ['left' => [], 'center' => [], 'right' => []];
            foreach (array_keys($zones) as $zone) {
                foreach ((array) ($config['elements'][$zone] ?? []) as $el) {
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
            $result['elements'] = $zones;
        } else {
            $result['elements'] = self::DEFAULTS['elements'];
        }

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

        return $result;
    }
}
