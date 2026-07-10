<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Журнал действий администраторов: кто, что (метод + путь), когда и с какого
 * IP. Пишется центрально (public/index.php) для всех изменяющих запросов
 * панели; тело запроса не сохраняется (там могут быть пароли/токены).
 */
final class AuditLog
{
    /**
     * Записывает действие текущего запроса. Вызывается до диспетчеризации;
     * любая ошибка журнала не должна ломать сайт — глотаем с error_log.
     */
    public static function record(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        if (!str_starts_with($path, '/admin') || str_starts_with($path, '/admin/login')) {
            return; // не панель или ещё не аутентифицирован (вход журналируется security-логом)
        }
        if (empty($_SESSION['user_id'])) {
            return;
        }

        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO audit_log (user_id, username, method, path, ip, created_at)
                 VALUES (:uid, :username, :method, :path, :ip, NOW())'
            );
            $stmt->execute([
                ':uid' => (int) $_SESSION['user_id'],
                ':username' => mb_substr((string) ($_SESSION['username'] ?? ''), 0, 100),
                ':method' => mb_substr($method, 0, 8),
                ':path' => mb_substr($path, 0, 255),
                ':ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Событие аутентификации (roadmap v2, раздел «Безопасность»): вход,
     * неверный пароль, 2FA, выход. Пишется отдельно от record(), потому что
     * маршруты /admin/login центральным журналом сознательно пропускаются.
     * Метод в журнале — 'AUTH', путь — 'auth/<событие>'.
     */
    public static function auth(string $event, ?int $userId, string $username): void
    {
        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO audit_log (user_id, username, method, path, ip, created_at)
                 VALUES (:uid, :username, :method, :path, :ip, NOW())'
            );
            $stmt->execute([
                ':uid' => $userId,
                ':username' => mb_substr($username, 0, 100),
                ':method' => 'AUTH',
                ':path' => mb_substr('auth/' . $event, 0, 255),
                ':ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit auth log failed: ' . $e->getMessage());
        }
    }

    /**
     * Поиск с фильтрами и пагинацией.
     *
     * @param array{user_id?: int, q?: string, from?: string, to?: string} $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public static function search(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :uid';
            $params[':uid'] = (int) $filters['user_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = 'path LIKE :q';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        // Даты в формате Y-m-d (валидируются, мусор игнорируется).
        foreach (['from' => 'created_at >= :from', 'to' => 'created_at <= :to'] as $key => $cond) {
            $val = (string) ($filters[$key] ?? '');
            if ($val !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) === 1) {
                $where[] = $cond;
                $params[':' . $key] = $val . ($key === 'from' ? ' 00:00:00' : ' 23:59:59');
            }
        }

        $suffix = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
        $pdo = Database::pdo();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_log' . $suffix);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            'SELECT * FROM audit_log' . $suffix . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Список администраторов, встречающихся в журнале (для фильтра).
     *
     * @return array<int, array{user_id: int, username: string}>
     */
    public static function actors(): array
    {
        return Database::pdo()->query(
            'SELECT user_id, MAX(username) AS username FROM audit_log
             WHERE user_id IS NOT NULL GROUP BY user_id ORDER BY username'
        )->fetchAll();
    }

    /** Удаляет записи старше N дней; возвращает число удалённых. */
    public static function purgeOlderThan(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }
        $stmt = Database::pdo()->prepare('DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL :d DAY)');
        $stmt->bindValue(':d', $days, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
