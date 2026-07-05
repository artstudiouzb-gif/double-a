<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;

final class TrashController
{
    private const TYPES = ['pages', 'news', 'projects'];

    public function index(): void
    {
        Auth::requireLogin();
        View::render('admin/trash/index', [
            'pages' => Page::trashed(),
            'news' => News::trashed(),
            'projects' => Project::trashed(),
        ]);
    }

    public function restore(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $type = (string) ($params['type'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        if (!in_array($type, self::TYPES, true)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        match ($type) {
            'pages' => Page::restore($id),
            'news' => News::restore($id),
            'projects' => Project::restore($id),
        };

        Flash::success('Элемент восстановлен из корзины.');
        header('Location: /admin/trash');
        exit;
    }

    public function forceDelete(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $type = (string) ($params['type'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        if (!in_array($type, self::TYPES, true)) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // Собираем привязанные медиа ДО удаления, удаляем сущность, затем
        // чистим файлы-сироты (не используемые больше нигде).
        $media = [];
        if ($type === 'pages') {
            $media = \App\Core\MediaCleaner::collectForPage($id);
            Page::forceDelete($id);
        } elseif ($type === 'news') {
            $news = News::findById($id);
            $media = $news ? \App\Core\MediaCleaner::collectForNews($news) : [];
            News::forceDelete($id);
        } else {
            Project::forceDelete($id);
        }

        \App\Core\MediaCleaner::purgeUnreferenced($media);

        Flash::success('Элемент удалён навсегда.');
        header('Location: /admin/trash');
        exit;
    }
}
