<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Нативный SMTP-клиент на чистом PHP (stream_socket_client), без сторонних
 * библиотек. Поддерживает STARTTLS (порт 587), implicit SSL (порт 465),
 * авторизацию AUTH LOGIN и AUTH PLAIN. Подходит для Яндекс, Google и др.
 */
final class Mailer
{
    /** @var resource|null */
    private $socket = null;
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) Config::get('mail', []);
    }

    public static function isConfigured(): bool
    {
        return trim((string) Config::get('mail.host', '')) !== '';
    }

    /**
     * Отправляет письмо. Возвращает true при успехе. Не бросает исключения
     * наружу — ошибки логируются, чтобы отправка не ломала пользовательский сценарий.
     */
    public function send(string $toEmail, string $subject, string $body, ?string $toName = null): bool
    {
        if (trim((string) ($this->config['host'] ?? '')) === '') {
            return false;
        }

        try {
            $this->connect();
            $this->ehlo();

            if (($this->config['encryption'] ?? '') === 'tls') {
                $this->startTls();
                $this->ehlo();
            }

            if (!empty($this->config['username'])) {
                $this->authenticate();
            }

            $fromEmail = $this->config['from_email'] ?: $this->config['username'];
            $fromName = $this->config['from_name'] ?? 'ArtStudio CMS';

            $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command('DATA', [354]);

            $message = $this->buildMessage($fromEmail, $fromName, $toEmail, $toName, $subject, $body);
            $this->write($message . "\r\n.");
            $this->expect([250]);

            $this->command('QUIT', [221]);

            return true;
        } catch (\Throwable $e) {
            Logger::error('SMTP send failed: ' . $e->getMessage());
            return false;
        } finally {
            $this->close();
        }
    }

    private function connect(): void
    {
        $host = $this->config['host'];
        $port = (int) ($this->config['port'] ?? 587);
        $timeout = (int) ($this->config['timeout'] ?? 15);

        $transport = ($this->config['encryption'] ?? '') === 'ssl' ? 'ssl://' : 'tcp://';

        // Проверка TLS-сертификата сервера включена ЖЁСТКО и не отключается из
        // конфига: verify_peer/verify_peer_name = true, запрет самоподписанных.
        // Это защищает учётные данные SMTP от MITM. Релеи с самоподписанным
        // сертификатом не поддерживаются намеренно (используйте валидный TLS).
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new \RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, $timeout);
        $this->expect([220]);
    }

    private function ehlo(): void
    {
        $host = gethostname() ?: 'localhost';
        $this->write('EHLO ' . $host);
        $this->readResponse(); // 250 многострочный — читаем целиком
    }

    private function startTls(): void
    {
        $this->command('STARTTLS', [220]);

        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        if (!stream_socket_enable_crypto($this->socket, true, $crypto)) {
            throw new \RuntimeException('STARTTLS negotiation failed.');
        }
    }

    private function authenticate(): void
    {
        $username = (string) $this->config['username'];
        $password = (string) $this->config['password'];

        // AUTH LOGIN (наиболее совместимый вариант).
        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($username), [334]);
        $this->command(base64_encode($password), [235]);
    }

    private function buildMessage(string $fromEmail, string $fromName, string $toEmail, ?string $toName, string $subject, string $body): string
    {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHeader = $this->formatAddress($fromEmail, $fromName);
        $toHeader = $this->formatAddress($toEmail, $toName);

        // Тело переводим в base64, чтобы корректно передать UTF-8 и обойти
        // ограничения на длину строк / точку в начале строки.
        $encodedBody = chunk_split(base64_encode($body));

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: ' . $toHeader,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (gethostname() ?: 'artstudio') . '>',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $encodedBody;
    }

    private function formatAddress(string $email, ?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '<' . $email . '>';
        }

        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }

    /**
     * @param array<int, int> $expectedCodes
     */
    private function command(string $command, array $expectedCodes): void
    {
        $this->write($command);
        $this->expect($expectedCodes);
    }

    private function write(string $data): void
    {
        if ($this->socket === null) {
            throw new \RuntimeException('SMTP socket is not open.');
        }
        fwrite($this->socket, $data . "\r\n");
    }

    /**
     * @param array<int, int> $expectedCodes
     */
    private function expect(array $expectedCodes): string
    {
        $response = $this->readResponse();
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('Unexpected SMTP response: ' . trim($response));
        }

        return $response;
    }

    private function readResponse(): string
    {
        if ($this->socket === null) {
            throw new \RuntimeException('SMTP socket is not open.');
        }

        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            // Многострочный ответ: "250-..." продолжается, "250 ..." завершает.
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new \RuntimeException('Empty SMTP response (connection closed?).');
        }

        return $response;
    }

    private function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
