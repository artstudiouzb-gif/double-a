<?php

declare(strict_types=1);

use App\Core\BlockRenderer;

/**
 * Загруженное в обложку фото не должно теряться из-за того, что список
 * «Фон секции» остался на «Без фона»: у блока из готовой сборки фона нет,
 * форма показывает «Без фона», и сохранение записывало none поверх снимка.
 */
test('Обложка: фото при типе «Без фона» включает фон-изображение при сохранении', function () {
    $save = static function (array $post): string {
        // Та же нормализация, что в BlockController::collectData() для hero.
        $bgType = (string) ($post['bg_type'] ?? 'image');
        $bgType = in_array($bgType, ['none', 'image', 'video', 'youtube'], true) ? $bgType : 'image';
        $youtubeUrl = trim((string) ($post['youtube_url'] ?? ''));
        $videoUrl = trim((string) ($post['video_url'] ?? ''));
        if ($bgType === 'none' && \App\Core\Video::youtubeId($youtubeUrl) !== null) {
            $bgType = 'youtube';
        } elseif ($bgType === 'none' && $videoUrl !== '') {
            $bgType = 'video';
        } elseif ($bgType === 'none' && trim((string) ($post['image'] ?? '')) !== '') {
            $bgType = 'image';
        }

        return $bgType;
    };

    assert_same('image', $save(['bg_type' => 'none', 'image' => '/uploads/public/cover.jpg']));
    // Без фото «Без фона» остаётся выбором редактора.
    assert_same('none', $save(['bg_type' => 'none', 'image' => '']));
    assert_same('none', $save(['bg_type' => 'none', 'image' => '   ']));
    assert_same('youtube', $save(['bg_type' => 'none', 'youtube_url' => 'https://www.youtube.com/watch?v=s_lKTkRGKc8']));
    assert_same('video', $save(['bg_type' => 'none', 'video_url' => '/uploads/public/hero.mp4']));
    // Видео-режимы не перебиваются постером.
    assert_same('video', $save(['bg_type' => 'video', 'image' => '/uploads/public/poster.jpg']));
    assert_same('youtube', $save(['bg_type' => 'youtube', 'image' => '/uploads/public/poster.jpg']));

    $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/BlockController.php');
    assert_contains("Video::youtubeId(\$youtubeUrl) !== null", $src);
    assert_contains("if (\$bgType === 'none' && trim((string) (\$_POST['image'] ?? '')) !== '')", $src);

    $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/admin.js');
    assert_contains("bgSelect.value = 'youtube'", $js);
    assert_contains("target.matches('[name=\"youtube_url\"]')", $js);
});

test('Обложка: с фото рендерится медиа-вариант, без фото — заголовочная зона', function () {
    $withPhoto = BlockRenderer::render([
        'id' => 960, 'type' => 'hero', 'custom_css' => '',
        'data' => json_encode(['title' => 'Заголовок', 'bg_type' => 'image', 'image' => '/uploads/public/cover.jpg']),
    ]);
    assert_contains('cover.jpg', $withPhoto['html'], 'фото должно попасть в разметку');
    assert_contains('block-hero--media', $withPhoto['html']);

    // Явный выбор «Без фона» шаблон уважает, даже если в поле осталось фото:
    // иначе у тех, кто сознательно убрал фон, снимок вернулся бы сам собой.
    // Создать такое состояние заново больше нельзя — сохранение переключает
    // тип на «Фото» (см. тест выше); блоки, сохранённые до правки, чинятся
    // повторным сохранением.
    $explicitNone = BlockRenderer::render([
        'id' => 961, 'type' => 'hero', 'custom_css' => '',
        'data' => json_encode(['title' => 'Заголовок', 'bg_type' => 'none', 'image' => '/uploads/public/cover.jpg']),
    ]);
    assert_not_contains('cover.jpg', $explicitNone['html']);

    // Блоки без bg_type (старый формат) определяют фон по заполненным полям.
    $legacy = BlockRenderer::render([
        'id' => 962, 'type' => 'hero', 'custom_css' => '',
        'data' => json_encode(['title' => 'Заголовок', 'image' => '/uploads/public/cover.jpg']),
    ]);
    assert_contains('cover.jpg', $legacy['html'], 'старый блок без bg_type должен показывать фото');
    assert_contains('block-hero--media', $legacy['html']);
});
