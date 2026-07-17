<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\AdminListQuery;
use App\Core\ConcurrencyException;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\ImageField;
use App\Core\Slug;
use App\Core\View;
use App\Models\Language;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\ProjectImage;
use App\Models\ProjectTranslation;
use App\Models\ContentRevision;

final class ProjectController
{
    public function index(): void
    {
        Auth::requireLogin();
        $filters = AdminListQuery::normalize(
            $_GET,
            ['manual', 'newest', 'oldest', 'title_asc', 'title_desc'],
            'manual'
        );
        $total = Project::adminCount($filters);
        [$filters, $pages] = AdminListQuery::fitPage($filters, $total);
        View::render('admin/projects/index', [
            'items' => Project::adminList($filters),
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
        $newId = Project::duplicate((int) $params['id']);
        if ($newId === null) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }
        Flash::success('Проект дублирован как черновик.');
        header('Location: /admin/projects/' . $newId . '/edit');
        exit;
    }

    public function create(): void
    {
        Auth::requireLogin();
        View::render('admin/projects/form', ['project' => null, 'images' => [], 'fields' => [], 'translations' => [], 'error' => null]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        [$data, $error] = $this->collectInput(null);
        $translations = $this->collectTranslations();

        if ($error !== null) {
            View::render('admin/projects/form', [
                'project' => $data,
                'images' => $this->collectImages(),
                'fields' => $this->collectFields(),
                'translations' => $translations,
                'error' => $error,
            ]);
            return;
        }

        $images = $this->collectImages();
        $fields = $this->collectFields();
        $id = Database::transaction(static function (\PDO $_pdo) use ($data, $images, $fields, $translations): int {
            $id = Project::create($data);
            ProjectImage::replaceAll($id, $images);
            ProjectField::replaceAll($id, $fields);
            self::saveTranslations($id, $translations);

            return $id;
        });

        Flash::success('Проект создан.');
        header('Location: /admin/projects/' . $id . '/edit?draft_saved=project%3Anew');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();

        $project = Project::findById((int) $params['id']);
        if (!$project) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('admin/projects/form', [
            'project' => $project,
            'images' => ProjectImage::forProject((int) $project['id']),
            'fields' => ProjectField::forProject((int) $project['id']),
            'translations' => ProjectTranslation::forProject((int) $project['id']),
            'error' => null,
        ]);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $id = (int) $params['id'];
        $project = Project::findById($id);
        if (!$project) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if (!ContentRevision::isFresh('project', $id, (string) ($_POST['expected_updated_at'] ?? ''))) {
            View::render('admin/projects/form', [
                'project' => $project,
                'images' => ProjectImage::forProject($id),
                'fields' => ProjectField::forProject($id),
                'translations' => ProjectTranslation::forProject($id),
                'error' => 'Проект уже был изменён в другой вкладке или другим пользователем. Текущие данные перезагружены; восстановите локальный черновик и проверьте изменения.',
            ]);
            return;
        }

        [$data, $error] = $this->collectInput($id, $project);
        $translations = $this->collectTranslations();

        if ($error !== null) {
            View::render('admin/projects/form', [
                'project' => array_merge($project, $data),
                'images' => $this->collectImages(),
                'fields' => $this->collectFields(),
                'translations' => $translations,
                'error' => $error,
            ]);
            return;
        }

        $images = $this->collectImages();
        $fields = $this->collectFields();
        $expectedVersion = (int) ($_POST['expected_lock_version'] ?? 0);
        try {
            Database::transaction(static function (\PDO $_pdo) use ($id, $data, $images, $fields, $translations, $expectedVersion): void {
                ContentRevision::capture('project', $id, Auth::id());
                Project::update($id, $data, $expectedVersion);
                ProjectImage::replaceAll($id, $images);
                ProjectField::replaceAll($id, $fields);
                self::saveTranslations($id, $translations);
            });
        } catch (ConcurrencyException) {
            $project = Project::findById($id) ?? $project;
            View::render('admin/projects/form', [
                'project' => $project,
                'images' => ProjectImage::forProject($id),
                'fields' => ProjectField::forProject($id),
                'translations' => ProjectTranslation::forProject($id),
                'error' => 'Проект уже был изменён в другой вкладке или другим пользователем. Текущие данные перезагружены; восстановите локальный черновик и проверьте изменения.',
            ]);
            return;
        }

        Flash::success('Проект обновлён.');
        header('Location: /admin/projects/' . $id . '/edit?draft_saved=project%3A' . $id);
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        Project::delete((int) $params['id']);
        Flash::success('Проект удалён.');
        header('Location: ' . AdminListQuery::returnPath('/admin/projects', $_POST['return_query'] ?? ''));
        exit;
    }

    /**
     * @return array{0: array, 1: string|null}
     */
    private function collectInput(?int $id, ?array $existing = null): array
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $description = (string) ($_POST['description'] ?? '');
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($title === '') {
            return [['title' => $title, 'slug' => $slugInput, 'description' => $description, 'status' => $status], 'Укажите название проекта.'];
        }

        $slug = $slugInput !== '' ? Slug::make($slugInput) : Slug::make($title);
        if (Project::slugExists($slug, $id)) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        $coverImage = ImageField::resolve('cover_image_file', 'cover_image_url', $existing['cover_image'] ?? null, Auth::id());

        $data = [
            'title' => $title,
            'slug' => $slug,
            'description' => $description !== '' ? $description : null,
            'cover_image' => $coverImage,
            'status' => $status,
            'is_featured' => !empty($_POST['is_featured']),
            'sort_order' => $sortOrder,
        ];

        return [$data, null];
    }

    private function collectImages(): array
    {
        $images = [];
        foreach ((array) ($_POST['gallery'] ?? []) as $image) {
            $path = trim((string) ($image['file_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $images[] = ['file_path' => $path, 'caption' => trim((string) ($image['caption'] ?? ''))];
        }

        return $images;
    }

    /**
     * Переводы из полей translations[<lang>][title|description] для всех
     * НЕ-основных активных языков. Ключ — код языка.
     *
     * @return array<string, array{title: string, description: string}>
     */
    private function collectTranslations(): array
    {
        $defaultCode = Language::defaultCode();
        $input = (array) ($_POST['translations'] ?? []);
        $out = [];
        foreach (Language::active() as $lang) {
            $code = (string) $lang['code'];
            if ($code === $defaultCode) {
                continue;
            }
            $t = (array) ($input[$code] ?? []);
            $out[$code] = [
                'title' => trim((string) ($t['title'] ?? '')),
                'description' => trim((string) ($t['description'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, array{title: string, description: string}> $translations
     */
    private static function saveTranslations(int $projectId, array $translations): void
    {
        foreach ($translations as $code => $t) {
            ProjectTranslation::upsert($projectId, (string) $code, [
                'title' => $t['title'] ?? '',
                'description' => $t['description'] ?? '',
            ]);
        }
    }

    private function collectFields(): array
    {
        $fields = [];
        foreach ((array) ($_POST['custom_fields'] ?? []) as $field) {
            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $fields[] = ['field_key' => $key, 'field_value' => trim((string) ($field['value'] ?? ''))];
        }

        return $fields;
    }
}
