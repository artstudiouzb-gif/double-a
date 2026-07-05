<?php

declare(strict_types=1);

namespace App\Core;

final class Slug
{
    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public static function make(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $transliterated = strtr($text, self::TRANSLIT);
        $ascii = preg_replace('/[^a-z0-9]+/u', '-', $transliterated) ?? '';
        $ascii = trim($ascii, '-');
        $ascii = preg_replace('/-+/', '-', $ascii) ?? '';

        return $ascii !== '' ? $ascii : 'item-' . bin2hex(random_bytes(3));
    }
}
