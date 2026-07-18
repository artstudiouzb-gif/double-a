<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\AdminListQuery;
use App\Core\AppUrl;
use App\Core\ConcurrencyException;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\ImageField;
use App\Core\Slug;
use App\Core\TextProcessor;
use App\Core\View;
use App\Models\Language;
use App\Models\News;
use App\Models\NewsTranslation;
use App\Models\ContentRevision;

final class NewsController
{
    public function index(): void
    {
        Auth::requireLogin();
        $filters = AdminListQuery::normalize(
            $_GET,
            ['newest', 'oldest', 'title_asc', 'title_desc', 'published_desc'],
            'newest',
            true
        );
        $total = News::adminCount($filters);
        [$filters, $pages] = AdminListQuery::fitPage($filters, $total);
        View::render('admin/news/index', [
            'items' => News::adminList($filters),
            'filters' => $filters,
            'filterParams' => AdminListQuery::urlParams($filters),
            'total' => $total,
            'pages' => $pages,
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

        $extras = $this->collectExtras();
        [$id, $purgeAfterCommit] = Database::transaction(function (\PDO $_pdo) use ($data, $extras): array {
            $id = News::create($data);
            News::updateExtras($id, $extras);
            $this->saveTranslations($id);
            $purge = $this->handleGallery($id);

            return [$id, $purge];
        });
        if ($purgeAfterCommit !== []) {
            \App\Core\MediaCleaner::purgeUnreferenced($purgeAfterCommit);
        }

        // Авто-публикация в соцсети + вебхук при создании опубликованной новости.
        if ($data['status'] === 'published') {
            \App\Core\SocialSettings::enqueueForNews($id);
            if (\App\Core\WebPush::isEnabled()) {
                \App\Models\WebPushSubscription::enqueueNews($id);
            }
            $this->dispatchNewsPublished($id, $data);
        }

        Flash::success('Новость создана.');
        header('Location: /admin/news/' . $id . '/edit?draft_saved=news%3Anew');
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

    /**
     * Предпросмотр новости до публикации (группа 5.2): рендер как на сайте,
     * но только для авторизованных, с noindex и вне кэша/sitemap.
     */
    public function preview(array $params): void
    {
        Auth::requireLogin();
        $news = News::findById((int) $params['id']);
        if (!$news) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $lang = (string) ($_GET['lang'] ?? Language::defaultCode());
        if (!Language::isActive($lang)) {
            $lang = Language::defaultCode();
        }
        $news = News::localize($news, $lang);

        View::render('site/news_show', [
            'news' => $news,
            'gallery' => \App\Models\NewsImage::forNews((int) $news['id']),
            'robotsNoindex' => true,
            'previewNotice' => true,
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

        if (!ContentRevision::isFresh('news', $id, (string) ($_POST['expected_updated_at'] ?? ''))) {
            View::render('admin/news/form', [
                'news' => $news,
                'translations' => NewsTranslation::forNews($id),
                'gallery' => \App\Models\NewsImage::forNews($id),
                'error' => 'Новость уже была изменена в другой вкладке или другим пользователем. Текущие данные перезагружены; восстановите локальный черновик и проверьте изменения.',
            ]);
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
        $extras = $this->collectExtras();
        $expectedVersion = (int) ($_POST['expected_lock_version'] ?? 0);
        try {
            $purgeAfterCommit = Database::transaction(function (\PDO $_pdo) use ($id, $data, $extras, $expectedVersion): array {
                ContentRevision::capture('news', $id, Auth::id());
                News::update($id, $data, $expectedVersion);
                News::updateExtras($id, $extras);
                $this->saveTranslations($id);

                return $this->handleGallery($id);
            });
        } catch (ConcurrencyException) {
            $news = News::findById($id) ?? $news;
            View::render('admin/news/form', [
                'news' => $news,
                'translations' => NewsTranslation::forNews($id),
                'gallery' => \App\Models\NewsImage::forNews($id),
                'error' => 'Новость уже была изменена в другой вкладке или другим пользователем. Текущие данные перезагружены; восстановите локальный черновик и проверьте изменения.',
            ]);
            return;
        }
        if ($purgeAfterCommit !== []) {
            \App\Core\MediaCleaner::purgeUnreferenced($purgeAfterCommit);
        }

        // Авто-публикация + вебхук при переходе черновик -> опубликовано.
        if ($data['status'] === 'published' && !$wasPublished) {
            \App\Core\SocialSettings::enqueueForNews($id);
            if (\App\Core\WebPush::isEnabled()) {
                \App\Models\WebPushSubscription::enqueueNews($id);
            }
            $this->dispatchNewsPublished($id, $data);
        }

        Flash::success('Новость обновлена.');
        header('Location: /admin/news/' . $id . '/edit?draft_saved=news%3A' . $id);
        exit;
    }

    /** Отправляет событие news.published в исходящие вебхуки (задача 136). */
    private function dispatchNewsPublished(int $id, array $data): void
    {
        $base = AppUrl::base();
        \App\Core\WebhookDispatcher::dispatch('news.published', [
            'id' => $id,
            'title' => (string) ($data['title'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
            'url' => $base . '/news/' . rawurlencode((string) ($data['slug'] ?? '')),
        ]);
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

        // Кнопка конкретной сети передаёт network; пусто — во все готовые.
        $only = trim((string) ($_POST['network'] ?? ''));
        $only = in_array($only, \App\Core\SocialPublisher::NETWORKS, true) ? $only : null;

        // Кнопка публикации в админке — осознанное действие человека, поэтому
        // публикуем и повторно. Автопубликация при сохранении новости (выше по
        // коду) остаётся идемпотентной, иначе правки плодили бы посты.
        $count = \App\Core\SocialSettings::enqueueForNews((int) $news['id'], $only, true);
        if ($count <= 0) {
            Flash::error($only !== null
                ? 'Сеть не настроена. Проверьте её в разделе «Соцсети».'
                : 'Нет настроенных соцсетей. Включите их в разделе «Соцсети».');
        } else {
            // Пытаемся отправить сразу. Что не ушло — остаётся в очереди,
            // и воркер дошлёт по расписанию.
            $res = \App\Core\SocialSettings::dispatchPendingForNews((int) $news['id'], $only);
            if ($res['sent'] > 0 && $res['failed'] === 0) {
                Flash::success("Опубликовано в соцсети: {$res['sent']}.");
            } elseif ($res['sent'] > 0) {
                Flash::success("Опубликовано: {$res['sent']}, не удалось: {$res['failed']} — оставлено в очереди. " . implode('; ', $res['errors']));
            } elseif ($res['failed'] > 0) {
                Flash::error('Не удалось опубликовать: ' . implode('; ', $res['errors']) . '. Оставлено в очереди — воркер повторит.');
            } else {
                Flash::success("Поставлено в очередь публикации: {$count}. Отправка — по расписанию воркера.");
            }
        }
        // Из списка возвращаемся в список (с теми же фильтрами), из формы — в форму.
        if (($_POST['from'] ?? '') === 'list') {
            $query = trim((string) ($_POST['return_query'] ?? ''));
            header('Location: /admin/news' . ($query !== '' ? '?' . $query : ''));
            exit;
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
        header('Location: ' . AdminListQuery::returnPath('/admin/news', $_POST['return_query'] ?? ''));
        exit;
    }

    /**
     * @return array{0: array, 1: string|null}
     */
    /** Поля детальной страницы (эскиз): бейдж, тезисы, мероприятие, документы. */
    private function collectExtras(): array
    {
        $safeUrl = static function (string $u): string {
            return ($u !== '' && \App\Core\UrlGuard::isSafeLink($u)) ? $u : '';
        };
        $docs = [];
        foreach ((array) ($_POST['docs'] ?? []) as $doc) {
            $title = trim((string) ($doc['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $docs[] = [
                'title' => $title,
                'meta' => trim((string) ($doc['meta'] ?? '')),
                'url' => $safeUrl(trim((string) ($doc['url'] ?? ''))),
            ];
        }

        return [
            'badge' => trim((string) ($_POST['badge'] ?? '')),
            'press_release_url' => $safeUrl(trim((string) ($_POST['press_release_url'] ?? ''))),
            'key_points' => trim((string) ($_POST['key_points'] ?? '')),
            'event_meta' => trim((string) ($_POST['event_meta'] ?? '')),
            'docs' => $docs,
            'source_note' => trim((string) ($_POST['source_note'] ?? '')),
        ];
    }

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
    /** @return list<string> пути, которые можно удалить только после COMMIT */
    private function handleGallery(int $newsId): array
    {
        $purgeAfterCommit = [];
        // 1. Правки/удаления существующих.
        foreach ((array) ($_POST['gallery'] ?? []) as $imgId => $meta) {
            $imgId = (int) $imgId;
            if ($imgId <= 0) {
                continue;
            }
            if (!empty($meta['delete'])) {
                $path = \App\Models\NewsImage::delete($imgId, $newsId);
                if ($path !== null) {
                    $purgeAfterCommit[] = $path;
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
            return $purgeAfterCommit;
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

        return $purgeAfterCommit;
    }
}
