<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\ImageField;
use App\Core\Slug;
use App\Core\TextProcessor;
use App\Core\View;
use App\Models\Language;
use App\Models\News;
use App\Models\NewsTranslation;

final class NewsController
{
    public function index(): void
    {
        Auth::requireLogin();
        $status = (string) ($_GET['status'] ?? '');
        $lang = (string) ($_GET['lang'] ?? '');
        View::render('admin/news/index', [
            'items' => News::filter($status ?: null, $lang ?: null),
            'filterStatus' => $status,
            'filterLang' => $lang,
        ]);
    }

    public function duplicate(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();
        $newId = News::duplicate((int) $params['id']);
        if ($newId === null) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        Flash::success('Новость дублирована как черновик.');
        header('Location: /admin/news/' . $newId . '/edit');
        exit;
    }

    public function create(): void
    {
        Auth::requireLogin();
        View::render('admin/news/form', ['news' => null, 'translations' => [], 'error' => null]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        [$data, $error] = $this->collectInput(null);

        if ($error !== null) {
            View::render('admin/news/form', ['news' => $data, 'translations' => [], 'error' => $error]);
            return;
        }

        $id = News::create($data);
        $this->saveTranslations($id);
        $this->handleGallery($id);

        // Авто-публикация в соцсети при создании сразу опубликованной новости.
        if ($data['status'] === 'published') {
            \App\Core\SocialSettings::enqueueForNews($id);
        }

        Flash::success('Новость создана.');
        header('Location: /admin/news/' . $id . '/edit');
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
        View::render('admin/news/form', [
            'news' => $news,
            'translations' => NewsTranslation::forNews((int) $news['id']),
            'gallery' => \App\Models\NewsImage::forNews((int) $news['id']),
            'error' => null,
        ]);
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
            View::render('admin/news/form', [
                'news' => array_merge($news, $data),
                'translations' => NewsTranslation::forNews($id),
                'error' => $error,
            ]);
            return;
        }

        $wasPublished = ($news['status'] ?? '') === 'published';
        News::update($id, $data);
        $this->saveTranslations($id);
        $this->handleGallery($id);

        // Авто-публикация при переходе черновик -> опубликовано (без повторов).
        if ($data['status'] === 'published' && !$wasPublished) {
            \App\Core\SocialSettings::enqueueForNews($id);
        }

        Flash::success('Новость обновлена.');
        header('Location: /admin/news');
        exit;
    }

    /** Ручная постановка новости в очередь публикации во все готовые сети. */
    public function pushSocial(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $news = News::findById((int) $params['id']);
        if (!$news) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $count = \App\Core\SocialSettings::enqueueForNews((int) $news['id']);
        if ($count > 0) {
            Flash::success("Поставлено в очередь публикации: {$count}. Отправка — по расписанию воркера.");
        } else {
            Flash::error('Нет настроенных соцсетей. Включите их в разделе «Соцсети».');
        }
        header('Location: /admin/news/' . (int) $news['id'] . '/edit');
        exit;
    }

    /**
     * Сохраняет переводы для всех НЕ-дефолтных активных языков из полей
     * translations[<lang>][...].
     */
    private function saveTranslations(int $newsId): void
    {
        $defaultCode = Language::defaultCode();
        $input = (array) ($_POST['translations'] ?? []);

        foreach (Language::active() as $lang) {
            $code = (string) $lang['code'];
            if ($code === $defaultCode) {
                continue;
            }
            $t = (array) ($input[$code] ?? []);
            NewsTranslation::upsert($newsId, $code, [
                'title' => trim((string) ($t['title'] ?? '')),
                'excerpt' => trim((string) ($t['excerpt'] ?? '')),
                'content' => TextProcessor::process((string) ($t['content'] ?? ''), $code),
                'meta_title' => trim((string) ($t['meta_title'] ?? '')),
                'meta_description' => trim((string) ($t['meta_description'] ?? '')),
            ]);
        }
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
        // WYSIWYG-контент прогоняем через типограф/санитайзер (задача 75).
        $content = TextProcessor::process((string) ($_POST['content'] ?? ''), Language::defaultCode());
        $metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
        $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $publishedAtInput = trim((string) ($_POST['published_at'] ?? ''));

        $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
        $layoutType = in_array($_POST['layout_type'] ?? 'standard', News::LAYOUTS, true) ? (string) $_POST['layout_type'] : 'standard';
        $focalX = self::clampPercent($_POST['focal_x'] ?? null);
        $focalY = self::clampPercent($_POST['focal_y'] ?? null);

        if ($title === '') {
            return [['title' => $title, 'slug' => $slugInput, 'excerpt' => $excerpt, 'content' => $content, 'status' => $status], 'Укажите заголовок новости.'];
        }

        if ($videoUrl !== '' && !\App\Core\Video::isYoutube($videoUrl)) {
            return [
                ['title' => $title, 'slug' => $slugInput, 'excerpt' => $excerpt, 'content' => $content, 'status' => $status, 'video_url' => $videoUrl, 'layout_type' => $layoutType],
                'Ссылка на видео должна быть YouTube-адресом (youtube.com/watch, youtu.be и т.п.).',
            ];
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
            'video_url' => $videoUrl !== '' ? $videoUrl : null,
            'layout_type' => $layoutType,
            'focal_x' => $focalX,
            'focal_y' => $focalY,
            'meta_title' => $metaTitle !== '' ? $metaTitle : null,
            'meta_description' => $metaDescription !== '' ? $metaDescription : null,
            'status' => $status,
            'published_at' => $publishedAt,
            'author_id' => Auth::id(),
        ];

        return [$data, null];
    }

    private static function clampPercent(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int) $value;
        return max(0, min(100, $n));
    }

    /**
     * Обрабатывает галерею новости: удаление отмеченных фото, правку alt/порядка/
     * фокальной точки существующих и загрузку новых файлов (news_gallery[]).
     */
    private function handleGallery(int $newsId): void
    {
        // 1. Правки/удаления существующих.
        foreach ((array) ($_POST['gallery'] ?? []) as $imgId => $meta) {
            $imgId = (int) $imgId;
            if ($imgId <= 0) {
                continue;
            }
            if (!empty($meta['delete'])) {
                $path = \App\Models\NewsImage::delete($imgId, $newsId);
                if ($path !== null) {
                    \App\Core\MediaCleaner::purgeUnreferenced([$path]);
                }
                continue;
            }
            \App\Models\NewsImage::updateMeta(
                $imgId,
                $newsId,
                trim((string) ($meta['alt'] ?? '')) ?: null,
                (int) ($meta['sort'] ?? 0),
                self::clampPercent($meta['focal_x'] ?? null),
                self::clampPercent($meta['focal_y'] ?? null)
            );
        }

        // 2. Новые загрузки (множественный input news_gallery[]).
        if (empty($_FILES['news_gallery']) || !is_array($_FILES['news_gallery']['name'] ?? null)) {
            return;
        }

        $existing = \App\Models\NewsImage::forNews($newsId);
        $sort = count($existing);

        $names = $_FILES['news_gallery']['name'];
        foreach ($names as $i => $name) {
            if (($_FILES['news_gallery']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $single = [
                'name' => $_FILES['news_gallery']['name'][$i],
                'type' => $_FILES['news_gallery']['type'][$i],
                'tmp_name' => $_FILES['news_gallery']['tmp_name'][$i],
                'error' => $_FILES['news_gallery']['error'][$i],
                'size' => $_FILES['news_gallery']['size'][$i],
            ];
            try {
                $file = \App\Core\Uploader::store($single, 'public', Auth::id());
                \App\Models\NewsImage::create($newsId, \App\Models\FileEntry::publicUrl($file), null, $sort++);
            } catch (\Throwable $e) {
                Flash::error('Не удалось загрузить фото галереи: ' . $e->getMessage());
            }
        }
    }
}
