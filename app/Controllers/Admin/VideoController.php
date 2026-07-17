<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\ImageField;
use App\Core\View;
use App\Models\Language;
use App\Models\Video;
use App\Models\VideoTranslation;

/**
 * Управление видео: список + создание, редактирование (обложка, ссылка,
 * длительность), публикация, флаг «показать на главном», удаление.
 */
final class VideoController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('admin/videos/index', ['items' => Video::all()]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $id = Video::create((string) ($_POST['title'] ?? ''));
        if ($id === null) {
            Flash::error('Укажите название видео.');
            header('Location: /admin/videos');
            exit;
        }
        Flash::success('Видео создано — добавьте обложку и ссылку.');
        header('Location: /admin/videos/' . $id . '/edit');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();

        $video = Video::findById((int) $params['id']);
        if (!$video) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        View::render('admin/videos/form', [
            'video' => $video,
            'translations' => VideoTranslation::forVideo((int) $video['id']),
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $id = (int) $params['id'];
        $existing = Video::findById($id);
        if ($existing === null) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $cover = ImageField::resolve('cover_file', 'cover_url', (string) ($existing['cover_url'] ?? ''), Auth::id());

        Video::update(
            $id,
            (string) ($_POST['title'] ?? ''),
            (string) ($_POST['description'] ?? ''),
            (string) ($cover ?? ''),
            (string) ($_POST['video_url'] ?? ''),
            (string) ($_POST['duration'] ?? ''),
            !empty($_POST['is_published']),
            !empty($_POST['is_featured']),
            (int) ($_POST['sort_order'] ?? 0)
        );
        $this->saveTranslations($id);
        Flash::success('Видео сохранено.');
        header('Location: /admin/videos/' . $id . '/edit');
        exit;
    }

    /**
     * Сохраняет переводы (title, description) для всех НЕ-основных активных
     * языков из полей translations[<lang>][...].
     */
    private function saveTranslations(int $videoId): void
    {
        $defaultCode = Language::defaultCode();
        $input = (array) ($_POST['translations'] ?? []);
        foreach (Language::active() as $lang) {
            $code = (string) $lang['code'];
            if ($code === $defaultCode) {
                continue;
            }
            $t = (array) ($input[$code] ?? []);
            VideoTranslation::upsert($videoId, $code, [
                'title' => trim((string) ($t['title'] ?? '')),
                'description' => trim((string) ($t['description'] ?? '')),
            ]);
        }
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        Video::delete((int) $params['id']);
        Flash::success('Видео удалено.');
        header('Location: /admin/videos');
        exit;
    }
}
