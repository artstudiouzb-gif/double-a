<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\View;
use App\Models\Project;

/** Публичный раздел «Проекты»: список и детальная страница. */
final class ProjectController
{
    public function index(): void
    {
        View::render('site/projects_index', ['items' => Project::published()]);
    }

    public function show(array $params): void
    {
        $project = Project::findPublishedBySlug((string) ($params['slug'] ?? ''));
        if (!$project) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('site/project_show', ['project' => $project]);
    }
}
