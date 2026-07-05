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

        $templateFile = dirname(__DIR__, 2) . '/templates/blocks/' . $type . '.php';
        $html = is_file($templateFile)
            ? self::renderTemplate($templateFile, $data, $blockId)
            : '<!-- Неизвестный тип блока: ' . htmlspecialchars($type, ENT_QUOTES) . ' -->';

        $scopedCss = '';
        if (!empty($block['custom_css'])) {
            $scopedCss = CssScoper::scope((string) $block['custom_css'], '#block-' . $blockId);
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

    private static function enrichData(string $type, array $data): array
    {
        if ($type === 'form' && !empty($data['form_id'])) {
            $form = FormDef::findById((int) $data['form_id']);
            if ($form !== null) {
                $data['form'] = $form;
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
