<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Models\FormDef;
use App\Models\FormSubmission;

final class FormController
{
    public function submit(array $params): void
    {
        $slug = $params['slug'] ?? '';
        $form = FormDef::findBySlug($slug);

        if (!$form) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Flash::error('Сессия устарела, попробуйте отправить форму ещё раз.');
            $this->redirectBack();
        }

        $data = [];
        $missing = [];

        foreach ($form['fields'] as $field) {
            $name = $field['name'];
            $value = trim((string) ($_POST[$name] ?? ''));

            if (!empty($field['required']) && $value === '') {
                $missing[] = $field['label'];
                continue;
            }

            if ($field['type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $missing[] = $field['label'] . ' (некорректный email)';
                continue;
            }

            $data[$name] = $value;
        }

        if (!empty($missing)) {
            Flash::error('Заполните обязательные поля: ' . implode(', ', $missing));
            $this->redirectBack();
        }

        FormSubmission::create(
            (int) $form['id'],
            $data,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        if (!empty($form['notify_email'])) {
            $this->notify($form, $data);
        }

        Flash::success($form['success_message'] ?: 'Спасибо! Ваша заявка отправлена.');
        $this->redirectBack();
    }

    private function notify(array $form, array $data): void
    {
        $subject = 'Новая заявка: ' . $form['name'];
        $lines = [];
        foreach ($data as $key => $value) {
            $lines[] = $key . ': ' . $value;
        }
        $body = implode("\n", $lines);

        // Best-effort уведомление: на большинстве shared-хостингов mail() настроен
        // из коробки, но ошибка отправки не должна ломать сохранение заявки.
        try {
            @mail($form['notify_email'], $subject, $body);
        } catch (\Throwable $e) {
            error_log('Form notify mail failed: ' . $e->getMessage());
        }
    }

    private function redirectBack(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        // Разрешаем редирект только на относительные пути этого же сайта.
        if (!str_starts_with($referer, '/')) {
            $referer = '/';
        }
        header('Location: ' . $referer);
        exit;
    }
}
