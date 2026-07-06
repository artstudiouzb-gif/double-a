<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Хелперы разметки админки. Пока — единое поле выбора изображения с превью и
 * выбором из медиабиблиотеки (используется во всех типах контента).
 */
final class AdminUi
{
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
}
