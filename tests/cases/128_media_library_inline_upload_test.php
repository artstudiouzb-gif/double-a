<?php

declare(strict_types=1);

test('В медиабиблиотеке можно загрузить новый файл без выхода из формы', function () {
    $root = dirname(__DIR__, 2);
    $footer = (string) file_get_contents($root . '/app/Views/admin/layout/footer.php');
    $js = (string) file_get_contents($root . '/public/assets/js/admin.js');

    assert_contains('data-media-upload-input', $footer);
    assert_contains('data-media-upload-button', $footer);
    assert_contains('data-csrf=', $footer);
    assert_contains("fetch('/admin/files/chunk'", $js);
    assert_contains("fd.append('access_type', 'public')", $js);
    assert_contains('200 * 1024 * 1024', $js);
    assert_contains('loadLibrary(currentType, true)', $js);
    assert_contains('selectUrl(res.url)', $js);
});

test('После чанковой загрузки сервер возвращает URL нового публичного файла', function () {
    $controller = (string) file_get_contents(
        dirname(__DIR__, 2) . '/app/Controllers/Admin/ChunkedUploadController.php'
    );

    assert_contains('use App\\Models\\FileEntry;', $controller);
    assert_contains("'url' => \$accessType === 'public' ? FileEntry::publicUrl(\$file) : null", $controller);
    assert_contains("'mime_type' =>", $controller);
});

