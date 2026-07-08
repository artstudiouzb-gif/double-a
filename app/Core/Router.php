<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use App\Models\Language;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable|array}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        // Менеджер редиректов: старые URL (переезд с прежнего сайта) уводим на
        // новые адреса до сопоставления маршрутов — работает и для «занятых»
        // путей. Только GET/HEAD, панель не редиректится.
        if (in_array(strtoupper($method), ['GET', 'HEAD'], true)
            && !str_starts_with($path, '/admin')
            && Database::isConnected()) {
            try {
                $redirect = \App\Models\Redirect::findByPath($path);
                if ($redirect !== null) {
                    \App\Models\Redirect::recordHit((int) $redirect['id']);
                    $target = \App\Models\Redirect::buildTarget(
                        (string) $redirect['to_url'],
                        (string) ($_SERVER['QUERY_STRING'] ?? '')
                    );
                    http_response_code((int) $redirect['code'] === 302 ? 302 : 301);
                    header('Location: ' . $target);
                    return;
                }
            } catch (\Throwable) {
                // Таблицы может не быть до применения миграции — не мешаем сайту.
            }
        }

        $path = $this->resolveLocale($path);

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $regex = $this->compile($route['pattern']);
            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, static fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
                $this->invoke($route['handler'], $params);
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404');
    }

    /**
     * Определяет язык из первого сегмента URL. Если это код активного
     * не-дефолтного языка (site.com/uz/...), язык устанавливается и префикс
     * отрезается. Админка (/admin) под языковой префикс не попадает.
     */
    private function resolveLocale(string $path): string
    {
        // В режиме установки (или при недоступной БД) языков ещё нет.
        if (!Database::isConnected()) {
            Locale::set('ru');
            Locale::setPath($path);

            return $path;
        }

        if (str_starts_with($path, '/admin')) {
            Locale::set(Language::defaultCode());

            return $path;
        }

        if (preg_match('#^/([a-zA-Z]{2,8})(/.*|)$#', $path, $m)) {
            $code = strtolower($m[1]);
            if ($code !== Language::defaultCode() && Language::isActive($code)) {
                Locale::set($code);
                $rest = $m[2] === '' ? '/' : $m[2];
                Locale::setPath($rest);

                return $rest;
            }
        }

        Locale::set(Language::defaultCode());
        Locale::setPath($path);

        return $path;
    }

    private function compile(string $pattern): string
    {
        $pattern = rtrim($pattern, '/');
        if ($pattern === '') {
            $pattern = '/';
        }

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);

        return '#^' . $regex . '$#u';
    }

    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method($params);
            return;
        }

        $handler($params);
    }
}
