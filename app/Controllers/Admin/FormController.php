<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Slug;
use App\Core\View;
use App\Models\FormDef;
use App\Models\FormSubmission;

final class FormController
{
    public function index(): void
    {
        Auth::requireLogin();
        $items = FormDef::all();
        foreach ($items as &$item) {
            $item['unread'] = FormSubmission::countUnread((int) $item['id']);
        }
        View::render('admin/forms/index', ['items' => $items]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        View::render('admin/forms/form', ['form' => null, 'error' => null]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        [$data, $error] = $this->collectInput(null);

        if ($error !== null) {
            View::render('admin/forms/form', ['form' => $data, 'error' => $error]);
            return;
        }

        FormDef::create($data);
        Flash::success('Форма создана.');
        header('Location: /admin/forms');
        exit;
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();

        $form = FormDef::findById((int) $params['id']);
        if (!$form) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        View::render('admin/forms/form', ['form' => $form, 'error' => null]);
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        $id = (int) $params['id'];
        $form = FormDef::findById($id);
        if (!$form) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        [$data, $error] = $this->collectInput($id, $form);

        if ($error !== null) {
            View::render('admin/forms/form', ['form' => array_merge($form, $data), 'error' => $error]);
            return;
        }

        FormDef::update($id, $data);
        Flash::success('Форма обновлена.');
        header('Location: /admin/forms');
        exit;
    }

    public function destroy(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        FormDef::delete((int) $params['id']);
        Flash::success('Форма удалена.');
        header('Location: /admin/forms');
        exit;
    }

    public function submissions(array $params): void
    {
        Auth::requireLogin();

        $form = FormDef::findById((int) $params['id']);
        if (!$form) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        $submissions = FormSubmission::forForm((int) $form['id']);
        foreach ($submissions as $submission) {
            FormSubmission::markRead((int) $submission['id']);
        }

        View::render('admin/forms/submissions', ['form' => $form, 'submissions' => $submissions]);
    }

    public function deleteSubmission(array $params): void
    {
        Auth::requireLogin();
        Csrf::verifyRequest();

        FormSubmission::delete((int) $params['id']);
        Flash::success('Заявка удалена.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/forms'));
        exit;
    }

    /**
     * @return array{0: array, 1: string|null}
     */
    private function collectInput(?int $id, ?array $existing = null): array
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slugInput = trim((string) ($_POST['slug'] ?? ''));
        $notifyEmail = trim((string) ($_POST['notify_email'] ?? ''));
        $successMessage = trim((string) ($_POST['success_message'] ?? ''));

        if ($name === '') {
            return [['name' => $name], 'Укажите название формы.'];
        }

        $fields = [];
        foreach ((array) ($_POST['fields'] ?? []) as $field) {
            $fieldName = trim((string) ($field['name'] ?? ''));
            $fieldLabel = trim((string) ($field['label'] ?? ''));
            if ($fieldName === '' || $fieldLabel === '') {
                continue;
            }
            $fieldName = preg_replace('/[^a-z0-9_]/i', '', $fieldName) ?? '';
            $type = (string) ($field['type'] ?? 'text');
            $type = in_array($type, ['text', 'email', 'tel', 'textarea', 'file', 'select', 'radio', 'checkbox_group', 'checkbox', 'date'], true) ? $type : 'text';
            $entry = [
                'name' => $fieldName,
                'label' => $fieldLabel,
                'type' => $type,
                'required' => !empty($field['required']),
            ];
            if (in_array($type, ['select', 'radio', 'checkbox_group'], true)) {
                $entry['options'] = trim((string) ($field['options'] ?? ''));
            }
            // Условная логика показа поля (задача 135): показывать только если
            // другое поле равно заданному значению.
            $condField = preg_replace('/[^a-z0-9_]/i', '', trim((string) ($field['condition_field'] ?? ''))) ?? '';
            if ($condField !== '') {
                $entry['condition'] = [
                    'field' => $condField,
                    'value' => trim((string) ($field['condition_value'] ?? '')),
                ];
            }
            $fields[] = $entry;
        }

        if (empty($fields)) {
            return [['name' => $name], 'Добавьте хотя бы одно поле формы.'];
        }

        $slug = $slugInput !== '' ? Slug::make($slugInput) : Slug::make($name);
        if (FormDef::slugExists($slug, $id)) {
            $slug .= '-' . bin2hex(random_bytes(2));
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'fields' => $fields,
            'notify_email' => $notifyEmail !== '' ? $notifyEmail : null,
            'success_message' => $successMessage !== '' ? $successMessage : 'Спасибо! Ваша заявка отправлена.',
        ];

        return [$data, null];
    }
}
