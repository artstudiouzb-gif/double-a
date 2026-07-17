<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Хелперы разметки админки. Пока — единое поле выбора изображения с превью и
 * выбором из медиабиблиотеки (используется во всех типах контента).
 */
final class AdminUi
{
    /** Линейные иконки (16px, stroke=currentColor) для кнопок и меток. */
    private const ICONS = [
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'home' => '<path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'filter' => '<path d="M22 3H2l8 9.5V19l4 2v-8.5L22 3z"/>',
        'reset' => '<path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'layout' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
        'block' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M15 21V9"/>',
    ];

    /**
     * Инлайновая иконка для кнопки/метки. Неизвестное имя — пустая строка
     * (кнопка просто останется без иконки).
     */
    public static function icon(string $name, int $size = 15): string
    {
        $inner = self::ICONS[$name] ?? '';
        if ($inner === '') {
            return '';
        }

        return '<svg class="btn__icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
            . 'aria-hidden="true" focusable="false">' . $inner . '</svg>';
    }

    /**
     * Поле изображения: превью + URL-инпут + кнопка «Медиабиблиотека» + очистка,
     * опционально с companion-инпутом загрузки файла (FileReader-превью).
     *
     * @param string $urlName  имя поля со ссылкой (то, что читает контроллер)
     * @param string $urlValue текущее значение (URL)
     * @param array{label?:string,hint?:string,file?:?string,accept?:string,id?:string} $opts
     */
    public static function imageField(string $urlName, string $urlValue, array $opts = []): string
    {
        $label = $opts['label'] ?? 'Изображение';
        $hint = $opts['hint'] ?? '';
        $fileName = $opts['file'] ?? null;
        $accept = $opts['accept'] ?? 'image/*';
        $id = $opts['id'] ?? 'imgfld_' . preg_replace('/[^a-z0-9_]/i', '_', $urlName);

        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
        $hasImg = trim($urlValue) !== '';

        $html = '<div class="form-field image-field" data-image-field>';
        $html .= '<label for="' . $esc($id) . '">' . $esc($label) . '</label>';
        $html .= '<div class="image-field__row">';

        // Превью.
        $html .= '<div class="image-field__preview" data-image-preview>';
        if ($hasImg) {
            $html .= '<img src="' . $esc($urlValue) . '" alt="">';
        } else {
            $html .= '<span class="image-field__placeholder" aria-hidden="true">'
                . '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
                . '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M5 18l5-5 4 4 3-3 2 2"/></svg></span>';
        }
        $html .= '</div>';

        // Управление.
        $html .= '<div class="image-field__main">';
        $html .= '<div class="image-field__controls">';
        $html .= '<input type="text" id="' . $esc($id) . '" name="' . $esc($urlName) . '" value="' . $esc($urlValue) . '"'
            . ' data-image-input placeholder="URL или выбор из медиабиблиотеки">';
        $html .= '<button type="button" class="btn btn--small" data-media-pick data-media-target="#' . $esc($id) . '">Медиабиблиотека</button>';
        $html .= '<button type="button" class="btn btn--small" data-image-clear title="Очистить" aria-label="Очистить">&times;</button>';
        $html .= '</div>';

        if ($fileName !== null) {
            $html .= '<div class="image-field__upload">';
            $html .= '<input type="file" name="' . $esc($fileName) . '" accept="' . $esc($accept) . '" data-image-file>';
            $html .= '<span class="form-hint">…или загрузите файл с компьютера.</span>';
            $html .= '</div>';
        }
        if ($hint !== '') {
            $html .= '<span class="form-hint">' . $esc($hint) . '</span>';
        }
        $html .= '</div></div></div>';

        return $html;
    }

    /**
     * Поле выбора цвета с галочкой «по умолчанию». Значение читается
     * контроллером через BlockController::color() — при включённой галочке
     * $name_off цвет сбрасывается (color-input всегда шлёт значение).
     */
    public static function colorField(string $name, ?string $value, string $label, string $defaultHex = '#173a63', string $offLabel = 'По умолчанию'): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
        $val = ($value !== null && $value !== '') ? $value : $defaultHex;
        $off = ($value === null || $value === '');

        $html = '<div class="form-field colorfield">';
        $html .= '<label for="' . $esc($name) . '">' . $esc($label) . '</label>';
        $html .= '<input type="color" id="' . $esc($name) . '" name="' . $esc($name) . '" value="' . $esc($val) . '">';
        $html .= '<label class="colorfield__off"><input type="checkbox" name="' . $esc($name) . '_off" value="1"'
            . ($off ? ' checked' : '') . '> ' . $esc($offLabel) . '</label>';
        $html .= '</div>';

        return $html;
    }
}
