<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;

/**
 * Самодостаточная капча для публичных форм: PNG генерируется GD, код живёт
 * в сессии (одноразовый, с TTL), внешних сервисов нет — важно для гос-сайтов.
 * Проверка нечувствительна к регистру; алфавит без неоднозначных символов
 * (0/O, 1/l/I). Любая попытка проверки сжигает код — перебор невозможен.
 */
final class Captcha
{
    private const ALPHABET = '23456789ABCDEFHKMNPRSTUVWXYZ';
    private const LENGTH = 5;
    private const TTL = 600; // секунд на ввод
    private const SESSION_KEY = '_captcha';

    /** Пути к TTF на типовых серверах; без шрифта — фолбэк на встроенный GD. */
    private const FONT_CANDIDATES = [
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/liberation-sans/LiberationSans-Bold.ttf',
    ];

    public static function isEnabled(): bool
    {
        return Setting::get('captcha_enabled', '1') === '1';
    }

    /** Генерирует код, кладёт его в сессию и возвращает (для рендера PNG). */
    public static function issue(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        $_SESSION[self::SESSION_KEY] = [
            'hash' => hash('sha256', strtoupper($code)),
            'expires' => time() + self::TTL,
        ];

        return $code;
    }

    /**
     * Проверяет ввод пользователя. Код одноразовый: удаляется при ЛЮБОЙ
     * попытке (успех/провал/просрочка), повторная отправка требует новую
     * картинку.
     */
    public static function verify(?string $input): bool
    {
        $state = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);

        if (!is_array($state) || ($state['expires'] ?? 0) < time()) {
            return false;
        }
        $input = strtoupper(trim((string) $input));
        if (strlen($input) !== self::LENGTH) {
            return false;
        }

        return hash_equals((string) $state['hash'], hash('sha256', $input));
    }

    /** PNG с кодом: волновое смещение, поворот символов, линии и точки-шум. */
    public static function png(string $code): string
    {
        $w = 190;
        $h = 62;
        $im = imagecreatetruecolor($w, $h);

        $bg = imagecolorallocate($im, 244, 246, 249);   // --gov-bg
        $navy = imagecolorallocate($im, 23, 58, 99);    // --gov-navy
        $teal = imagecolorallocate($im, 18, 128, 127);  // --gov-teal-text
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);

        // Шум: точки и две дуги.
        $noise = imagecolorallocatealpha($im, 23, 58, 99, 96);
        for ($i = 0; $i < 140; $i++) {
            imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), $noise);
        }
        for ($i = 0; $i < 2; $i++) {
            imagearc($im, random_int(0, $w), random_int(0, $h), random_int($w, $w * 2), random_int($h, $h * 2), 0, 360, $noise);
        }

        $font = null;
        foreach (self::FONT_CANDIDATES as $candidate) {
            if (is_file($candidate)) {
                $font = $candidate;
                break;
            }
        }

        $len = strlen($code);
        $step = (int) (($w - 30) / $len);
        for ($i = 0; $i < $len; $i++) {
            $ch = $code[$i];
            $color = ($i % 2 === 0) ? $navy : $teal;
            $x = 18 + $i * $step + random_int(-3, 3);
            $y = (int) ($h / 2 + sin($i * 1.4 + random_int(0, 3)) * 7);
            if ($font !== null && function_exists('imagettftext')) {
                imagettftext($im, 26, random_int(-18, 18), $x, $y + 12, $color, $font, $ch);
            } else {
                // Фолбэк без TTF: встроенный шрифт GD, увеличенный вдвое.
                $g = imagecreatetruecolor(9, 15);
                $gbg = imagecolorallocate($g, 244, 246, 249);
                imagefilledrectangle($g, 0, 0, 9, 15, $gbg);
                imagestring($g, 5, 0, 0, $ch, imagecolorallocate($g, 23, 58, 99));
                imagecopyresized($im, $g, $x, $y - 10, 0, 0, 27, 45, 9, 15);
                imagedestroy($g);
            }
        }

        ob_start();
        imagepng($im);
        imagedestroy($im);

        return (string) ob_get_clean();
    }

    /** Разметка поля для публичной формы (картинка + обновление + ввод). */
    public static function field(string $inputId): string
    {
        return '<div class="block-form__field block-form__captcha">'
            . '<label for="' . htmlspecialchars($inputId, ENT_QUOTES) . '">Код с картинки</label>'
            . '<div class="captcha-row">'
            . '<img class="captcha-row__img" src="/captcha.png?ts=' . time() . '" width="190" height="62" alt="Защитный код">'
            . '<button type="button" class="captcha-row__refresh" aria-label="Обновить код"'
            . ' onclick="var i=this.parentNode.querySelector(\'img\');i.src=\'/captcha.png?ts=\'+Date.now();">&#8635;</button>'
            . '<input type="text" id="' . htmlspecialchars($inputId, ENT_QUOTES) . '" name="_captcha" inputmode="latin"'
            . ' autocomplete="off" spellcheck="false" maxlength="5" required aria-describedby="' . htmlspecialchars($inputId, ENT_QUOTES) . '-hint">'
            . '</div>'
            . '<span class="form-hint" id="' . htmlspecialchars($inputId, ENT_QUOTES) . '-hint">Введите символы с картинки (регистр не важен).</span>'
            . '</div>';
    }
}
