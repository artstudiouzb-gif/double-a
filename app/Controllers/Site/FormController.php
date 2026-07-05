<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\RateLimiter;
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

        $successMessage = $form['success_message'] ?: 'Спасибо! Ваша заявка отправлена.';

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            $this->fail('Сессия устарела, обновите страницу и попробуйте снова.', [], 419);
        }

        // Анти-флуд: не более 10 отправок форм с одного IP за 10 минут.
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::throttle('form', $ip, 10, 10)) {
            $this->fail('Слишком много отправок. Пожалуйста, попробуйте позже.', [], 429);
        }

        // Honeypot: боты заполняют скрытое поле или отправляют форму мгновенно.
        // Тихо показываем «успех», чтобы не подсказывать спамеру о срабатывании.
        if (Csrf::isSpam()) {
            $this->success($successMessage);
        }

        $data = [];
        $errors = [];

        foreach ($form['fields'] as $field) {
            $name = $field['name'];
            $value = trim((string) ($_POST[$name] ?? ''));

            if (!empty($field['required']) && $value === '') {
                $errors[$name] = 'Поле «' . $field['label'] . '» обязательно.';
                continue;
            }
            if ($field['type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Некорректный email.';
                continue;
            }

            $data[$name] = $value;
        }

        if ($errors !== []) {
            $this->fail('Проверьте правильность заполнения формы.', $errors);
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

        $this->success($successMessage);
    }

    /** AJAX-запрос ли это (fetch с X-Requested-With). */
    private function wantsJson(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /**
     * @param array<string, string> $errors
     */
    private function fail(string $message, array $errors = [], int $code = 200): never
    {
        if ($this->wantsJson()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => $message, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($code !== 200) {
            http_response_code($code);
        }
        Flash::error($message);
        $this->redirectBack();
    }

    private function success(string $message): never
    {
        if ($this->wantsJson()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }
        Flash::success($message);
        $this->redirectBack();
    }

    private function notify(array $form, array $data): void
    {
        if (!Mailer::isConfigured()) {
            return; // SMTP не настроен — уведомление просто пропускается
        }

        $subject = 'Новая заявка: ' . $form['name'];
        $lines = [];
        foreach ($data as $key => $value) {
            $lines[] = $key . ': ' . $value;
        }
        $body = implode("\n", $lines);

        // Ставим письмо в очередь — фронтенд отрабатывает мгновенно, отправку
        // выполняет CLI-воркер app/Console/mail_worker.php по Cron.
        \App\Models\MailQueue::enqueue((string) $form['notify_email'], $subject, $body);
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
