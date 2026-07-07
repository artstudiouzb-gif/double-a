<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Locale;
use App\Core\View;
use App\Models\ContentEntry;
use App\Models\ContentType;

/**
 * Публичный фронтенд пользовательских типов контента (Документы, Вакансии,
 * Тендеры и любые типы, созданные в админке с флагом «публичный»). Общий
 * список и карточка записи; значения кастомных полей рендерятся по типу.
 */
final class ContentController
{
    public function index(array $params): void
    {
        $type = ContentType::findBySlug((string) ($params['type'] ?? ''));
        if ($type === null || (int) ($type['is_public'] ?? 0) !== 1) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $lang = Locale::current();
        $fields = ContentType::fields((int) $type['id']);

        $perPage = 12;
        $q = trim((string) ($_GET['q'] ?? ''));
        $sort = in_array($_GET['sort'] ?? '', ['new', 'old', 'title'], true) ? (string) $_GET['sort'] : 'new';
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $total = ContentEntry::countTypePublic((int) $type['id'], $q);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        // Тип с дедлайном (вакансии/тендеры) — помечаем просроченные как «архив».
        $deadlineField = null;
        foreach ($fields as $f) {
            if ($f['field_type'] === 'date' && in_array($f['name'], ['deadline', 'end_date'], true)) {
                $deadlineField = $f['name'];
                break;
            }
        }

        $entries = [];
        foreach (ContentEntry::forTypePublic((int) $type['id'], $q, $sort, $perPage, $offset) as $entry) {
            $entry['data'] = json_decode((string) $entry['data'], true) ?: [];
            $entry = $this->localize($entry, $type, $lang);
            $entry['is_archived'] = false;
            if ($deadlineField !== null && !empty($entry['data'][$deadlineField])) {
                $ts = strtotime((string) $entry['data'][$deadlineField]);
                $entry['is_archived'] = $ts !== false && $ts < strtotime('today');
            }
            $entries[] = $entry;
        }

        View::render('site/content_index', [
            'type' => $type,
            'fields' => $fields,
            'entries' => $entries,
            'q' => $q,
            'sort' => $sort,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'hasDeadline' => $deadlineField !== null,
        ]);
    }

    public function show(array $params): void
    {
        $type = ContentType::findBySlug((string) ($params['type'] ?? ''));
        if ($type === null || (int) ($type['is_public'] ?? 0) !== 1) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $entry = ContentEntry::findPublishedBySlug((int) $type['id'], (string) ($params['slug'] ?? ''));
        if ($entry === null) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $lang = Locale::current();
        $entry = $this->localize($entry, $type, $lang);

        View::render('site/content_show', [
            'type' => $type,
            'fields' => ContentType::fields((int) $type['id']),
            'entry' => $entry,
        ]);
    }

    /**
     * Накладывает перевод текущего языка на запись (для мультиязычных типов).
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $type
     */
    private function localize(array $entry, array $type, string $lang): array
    {
        if ((int) ($type['has_translations'] ?? 0) !== 1) {
            return $entry;
        }
        $translations = ContentEntry::translations((int) $entry['id']);
        if (!isset($translations[$lang])) {
            return $entry;
        }
        $tr = $translations[$lang];
        if (!empty($tr['title'])) {
            $entry['title'] = $tr['title'];
        }
        if (!empty($tr['data']) && is_array($tr['data'])) {
            $entry['data'] = array_merge((array) $entry['data'], $tr['data']);
        }

        return $entry;
    }
}
