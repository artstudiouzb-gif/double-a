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
        'cta' => ['title' => '', 'text' => '', 'button_text' => '', 'button_url' => ''],
        'advantages' => ['title' => '', 'items' => []],
        'slider' => ['slides' => []],
        'gallery' => ['title' => '', 'images' => []],
        'form' => ['form_id' => null],
        'columns' => ['columns' => 2, 'gap' => 'medium'],
        'testimonials' => ['title' => '', 'items' => []],
        'counters' => ['title' => '', 'items' => []],
        'team_list' => ['title' => '', 'limit' => 0],
        'projects_list' => ['title' => '', 'limit' => 3],
    ];

    public static function defaultsFor(string $type): array
    {
        return self::DEFAULTS[$type] ?? [];
    }

    /**
     * @param array<string, mixed> $block
     * @return array{html: string, css: string}
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
        $reveal = !empty($data['_reveal']) ? ' data-reveal' : '';

        $wrapped = sprintf(
            '<section id="block-%d" class="cms-block cms-block--%s cms-block--space-%s" data-block-type="%s"%s>%s</section>',
            $blockId,
            htmlspecialchars($type, ENT_QUOTES),
            htmlspecialchars($spacing, ENT_QUOTES),
            htmlspecialchars($type, ENT_QUOTES),
            $reveal,
            $html
        );

        return ['html' => $wrapped, 'css' => $scopedCss];
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @return array{html: string, css: string, assets: array<int, string>}
     */
    public static function renderPage(array $blocks): array
    {
        $htmlParts = [];
        $cssParts = [];
        $assets = [];

        foreach ($blocks as $block) {
            $rendered = self::render($block);
            $htmlParts[] = $rendered['html'];
            if ($rendered['css'] !== '') {
                $cssParts[] = "/* block #{$block['id']} ({$block['type']}) */\n" . $rendered['css'];
            }
            $type = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $block['type'])) ?? '';
            $assets[$type] = true;
        }

        return [
            'html' => implode("\n", $htmlParts),
            'css' => implode("\n\n", $cssParts),
            'assets' => array_keys($assets),
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
            $children = \App\Models\Block::childrenOf((int) $block['id']);
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
            $items = \App\Models\TeamMember::published();
            $limit = (int) ($data['limit'] ?? 0);
            $data['members'] = $limit > 0 ? array_slice($items, 0, $limit) : $items;
        }
        if ($type === 'projects_list') {
            $items = \App\Models\Project::published();
            $limit = (int) ($data['limit'] ?? 0);
            $data['projects'] = $limit > 0 ? array_slice($items, 0, $limit) : $items;
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
