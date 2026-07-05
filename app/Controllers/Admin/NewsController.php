<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\ImageField;
use App\Core\Slug;
use App\Core\View;
use App\Models\News;

final class NewsController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('admin/news/index', ['items' => News::all()]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        View::render('admin/news/form', ['news' => null, 'error' => null]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        [$data, $error] = $this->collectInput(null);

        if ($error !== null) {
            View::render('admin/news/form', ['news' => $data, 'error' => $error]);
            return;
        }

        News::create($data);
        Flash::success('Новость создана.');
        header('Location: /admin/news');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();
        $news = News::findById((int) $params['id']);
        if (!$news) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        View::render('admin/news/form', ['news' => $news, 'error' => null]);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $id = (int) $params['id'];
        $news = News::findById($id);
        if (!$news) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        [$data, $error] = $this->collectInput($id, $news);

        if ($error !== null) {
            View::render('admin/news/form', ['news' => array_merge($news, $data), 'error' => $error]);
            return;
        }

        News::update($id, $data);
        Flash::success('Новость обновлена.');
        header('Location: /admin/news');
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        News::delete((int) $params['id']);
        Flash::success('Новость удалена.');
        header('Location: /admin/news');
        exit;
    }

    /**
     * @return array{0: array, 1: string|null}
     */
    private function collectInput(?int $id, ?array $existing = null): array
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
        $content = (string) ($_POST['content'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $publishedAtInput = trim((string) ($_POST['published_at'] ?? ''));

        if ($title === '') {
            return [['title' => $title, 'slug' => $slugInput, 'excerpt' => $excerpt, 'content' => $content, 'status' => $status], 'Укажите заголовок новости.'];
        }

        $slug = $slugInput !== '' ? Slug::make($slugInput) : Slug::make($title);
        if (News::slugExists($slug, $id)) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        $publishedAt = $publishedAtInput !== '' ? str_replace('T', ' ', $publishedAtInput) . ':00' : date('Y-m-d H:i:s');

        $image = ImageField::resolve('image_file', 'image_url', $existing['image'] ?? null, Auth::id());

        $data = [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'content' => $content,
            'image' => $image,
            'status' => $status,
            'published_at' => $publishedAt,
            'author_id' => Auth::id(),
        ];

        return [$data, null];
    }
}
