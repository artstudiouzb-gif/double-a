<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class MenuItem
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private static array $activeRequestCache = [];

    public static function all(): array
    {
        $stmt = Database::pdo()->query('SELECT * FROM menu_items ORDER BY sort_order ASC, id ASC');

        return $stmt->fetchAll();
    }

    /**
     * Активные пункты меню для языка: пункты этого языка + пункты, помеченные
     * как «для всех языков» (lang = ''). Если для языка нет ни одного
     * специфичного пункта, показываются пункты языка по умолчанию.
     */
    public static function activeForLang(string $lang): array
    {
        if (isset(self::$activeRequestCache[$lang])) {
            return self::$activeRequestCache[$lang];
        }
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM menu_items WHERE is_active = 1 AND (lang = :lang OR lang = '')
             ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([':lang' => $lang]);
        $rows = $stmt->fetchAll();

        $hasLangSpecific = false;
        foreach ($rows as $row) {
            if ((string) $row['lang'] === $lang && $lang !== '') {
                $hasLangSpecific = true;
                break;
            }
        }

        // Если для запрошенного языка нет собственных пунктов и это не язык
        // по умолчанию — откатываемся на пункты языка по умолчанию + общие.
        if (!$hasLangSpecific && $lang !== Language::defaultCode()) {
            $default = Language::defaultCode();
            $stmt = Database::pdo()->prepare(
                "SELECT * FROM menu_items WHERE is_active = 1 AND (lang = :lang OR lang = '')
                 ORDER BY sort_order ASC, id ASC"
            );
            $stmt->execute([':lang' => $default]);

            return self::$activeRequestCache[$lang] = self::buildTree($stmt->fetchAll());
        }

        return self::$activeRequestCache[$lang] = self::buildTree($rows);
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM menu_items WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null;

        // Порядок считаем в пределах одного родителя и языка.
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM menu_items
             WHERE lang = :lang AND parent_id <=> :parent'
        );
        $stmt->execute([':lang' => $data['lang'], ':parent' => $parentId]);
        $nextOrder = (int) $stmt->fetchColumn();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO menu_items (lang, title, icon_svg, is_divider, url_type, url_value, parent_id, mega_columns, sort_order, is_active, created_at)
             VALUES (:lang, :title, :icon_svg, :is_divider, :url_type, :url_value, :parent_id, :mega_columns, :sort_order, :is_active, NOW())'
        );
        $stmt->execute([
            ':lang' => $data['lang'],
            ':title' => $data['title'],
            ':icon_svg' => self::cleanIcon($data['icon_svg'] ?? null),
            ':is_divider' => !empty($data['is_divider']) ? 1 : 0,
            ':url_type' => $data['url_type'],
            ':url_value' => $data['url_value'],
            ':parent_id' => $parentId,
            ':mega_columns' => self::megaColumns($data['mega_columns'] ?? 0, $parentId),
            ':sort_order' => $nextOrder,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        $id = (int) Database::pdo()->lastInsertId();
        self::$activeRequestCache = [];
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null;
        $current = self::findById($id);
        $sortOrder = (int) ($current['sort_order'] ?? 0);
        $parentChanged = $current !== null
            && ((string) $current['lang'] !== (string) $data['lang']
                || (($current['parent_id'] === null) !== ($parentId === null))
                || ($parentId !== null && (int) $current['parent_id'] !== $parentId));
        if ($parentChanged) {
            $orderStmt = Database::pdo()->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM menu_items
                 WHERE lang = :lang AND parent_id <=> :parent AND id <> :id'
            );
            $orderStmt->execute([':lang' => $data['lang'], ':parent' => $parentId, ':id' => $id]);
            $sortOrder = (int) $orderStmt->fetchColumn();
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE menu_items SET lang = :lang, title = :title, icon_svg = :icon_svg,
             is_divider = :is_divider, url_type = :url_type, url_value = :url_value,
             parent_id = :parent_id, mega_columns = :mega_columns,
             sort_order = :sort_order, is_active = :is_active WHERE id = :id'
        );
        $stmt->execute([
            ':lang' => $data['lang'],
            ':title' => $data['title'],
            ':icon_svg' => self::cleanIcon($data['icon_svg'] ?? null),
            ':is_divider' => !empty($data['is_divider']) ? 1 : 0,
            ':url_type' => $data['url_type'],
            ':url_value' => $data['url_value'],
            ':parent_id' => $parentId,
            ':mega_columns' => self::megaColumns($data['mega_columns'] ?? 0, $parentId),
            ':sort_order' => $sortOrder,
            ':is_active' => !empty($data['is_active']) ? 1 : 0,
            ':id' => $id,
        ]);
        self::$activeRequestCache = [];
    }

    /**
     * Число колонок мега-меню: 0 (обычная выпадашка) либо 2..4. У вложенного
     * пункта мега-меню быть не может — раскладку задаёт только верхний уровень.
     */
    public static function megaColumns(mixed $value, ?int $parentId = null): int
    {
        if ($parentId !== null) {
            return 0;
        }
        $columns = (int) $value;

        return $columns >= 2 && $columns <= 4 ? $columns : 0;
    }

    /**
     * Очистка инлайновой SVG-иконки перед сохранением: пусто → null; иначе
     * прогоняем через строгий санитайзер (тот же, что для загружаемых SVG —
     * вырезает скрипты, обработчики событий, внешние ссылки и XXE). Слишком
     * крупные строки отбрасываем (иконка должна быть компактной).
     */
    private static function cleanIcon(mixed $svg): ?string
    {
        $svg = trim((string) $svg);
        if ($svg === '' || mb_stripos($svg, '<svg') === false) {
            return null;
        }
        if (mb_strlen($svg) > 20000) {
            return null;
        }

        return \App\Core\Uploader::sanitizeSvgString($svg);
    }

    /**
     * Проверяет допустимость назначения родителя (глубина максимум 1 уровень,
     * без циклов и «внуков»). Возвращает текст ошибки или null, если всё ок.
     *
     * @param int|null $selfId id редактируемого пункта (null при создании)
     */
    public static function validateParent(?int $parentId, ?int $selfId, string $lang): ?string
    {
        if ($selfId !== null && self::hasChildrenWithOtherLanguage($selfId, $lang)) {
            return 'Сначала приведите язык вложенных пунктов в соответствие с родительским.';
        }
        if ($parentId === null) {
            return null; // Пункт верхнего уровня — всегда допустимо.
        }
        if ($selfId !== null && $parentId === $selfId) {
            return 'Пункт не может быть родителем самому себе.';
        }
        $parent = self::findById($parentId);
        if (!$parent) {
            return 'Выбранный родительский пункт не найден.';
        }
        // Глубина ограничена одним уровнем: у родителя не должно быть своего родителя.
        if ($parent['parent_id'] !== null) {
            return 'Меню поддерживает только один уровень вложенности.';
        }
        // Родитель и потомок должны быть на одном языке (или оба «для всех»).
        if ((string) $parent['lang'] !== $lang) {
            return 'Родитель и вложенный пункт должны быть на одном языке.';
        }
        if (!empty($parent['is_divider'])) {
            return 'Разделитель не может быть родительским пунктом.';
        }
        // Нельзя вкладывать пункт, у которого уже есть свои дети (появились бы «внуки»).
        if ($selfId !== null && self::hasChildren($selfId)) {
            return 'У этого пункта есть вложенные — сначала перенесите или удалите их.';
        }

        return null;
    }

    private static function hasChildrenWithOtherLanguage(int $id, string $lang): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM menu_items WHERE parent_id = :id AND lang <> :lang LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':lang' => $lang]);

        return $stmt->fetchColumn() !== false;
    }

    public static function hasChildren(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM menu_items WHERE parent_id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Пункты, пригодные быть родителями для указанного языка: только верхнего
     * уровня (parent_id IS NULL), исключая сам редактируемый пункт.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function parentCandidates(string $lang, ?int $excludeId = null): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM menu_items WHERE parent_id IS NULL AND lang = :lang
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':lang' => $lang]);
        $rows = $stmt->fetchAll();

        return array_values(array_filter($rows, static fn ($r) => $excludeId === null || (int) $r['id'] !== $excludeId));
    }

    /**
     * Пакетное сохранение порядка и вложенности (AJAX drag-and-drop, задача 3).
     * Каждый элемент: ['id'=>int, 'parent_id'=>?int, 'sort_order'=>int].
     * Отклоняет попытки превысить глубину/создать цикл (валидируется на сервере).
     *
     * @param array<int,array{id:int,parent_id:?int,sort_order:int}> $rows
     */
    public static function reorder(array $rows): void
    {
        // Индекс всех пунктов для проверки глубины.
        $byId = [];
        foreach (self::all() as $r) {
            $byId[(int) $r['id']] = $r;
        }
        if ($rows === []) {
            throw new \DomainException('Список пунктов меню пуст. Обновите страницу и повторите попытку.');
        }

        // Полная карта будущих родителей: неизменяемые строки тоже участвуют
        // в проверке, поэтому частичный запрос не может создать скрытых «внуков».
        $futureParent = [];
        foreach ($byId as $id => $row) {
            $futureParent[$id] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }
        $seen = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (!isset($byId[$id])) {
                throw new \DomainException('Один из пунктов меню больше не существует. Обновите страницу.');
            }
            if (isset($seen[$id])) {
                throw new \DomainException('Получен повторяющийся пункт меню. Обновите страницу.');
            }
            $seen[$id] = true;
            $futureParent[$id] = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }

        // Проверяем всю будущую структуру до первого UPDATE: либо применятся
        // все изменения, либо ни одного. Это исключает частично сохранённое меню.
        foreach ($futureParent as $id => $parent) {
            if (!empty($byId[$id]['is_divider']) && $parent !== null) {
                throw new \DomainException('Разделитель нельзя сделать вложенным пунктом.');
            }
            if ($parent === null) {
                continue;
            }
            if ($parent === $id) {
                throw new \DomainException('Пункт меню не может быть родителем самому себе.');
            }
            if (!isset($byId[$parent])) {
                throw new \DomainException('Родительский пункт больше не существует. Обновите страницу.');
            }
            if (!empty($byId[$parent]['is_divider'])) {
                throw new \DomainException('Разделитель не может содержать вложенные пункты.');
            }
            if ((string) $byId[$parent]['lang'] !== (string) $byId[$id]['lang']) {
                throw new \DomainException('Нельзя объединять пункты меню разных языков.');
            }
            $parentOfParent = $futureParent[$parent];
            if ($parentOfParent !== null) {
                throw new \DomainException('Меню поддерживает только один уровень вложенности.');
            }
        }

        // Нормализуем порядок отдельно внутри каждого языка и родителя.
        $groupOrder = [];
        $normalizedOrder = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $parent = $futureParent[$id];
            $group = (string) $byId[$id]['lang'] . ':' . ($parent ?? 0);
            $groupOrder[$group] = ($groupOrder[$group] ?? 0) + 1;
            $normalizedOrder[$id] = $groupOrder[$group];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE menu_items SET parent_id = :parent, sort_order = :order WHERE id = :id');
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $parent = $futureParent[$id];
                $stmt->execute([
                    ':parent' => $parent,
                    ':order' => $normalizedOrder[$id],
                    ':id' => $id,
                ]);
            }
            $pdo->commit();
            self::$activeRequestCache = [];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Строит двухуровневое дерево из плоского списка строк: пункты верхнего
     * уровня получают ключ 'children' с отсортированными дочерними пунктами.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function buildTree(array $rows): array
    {
        $top = [];
        $children = [];
        foreach ($rows as $r) {
            if ($r['parent_id'] === null) {
                $r['children'] = [];
                $top[(int) $r['id']] = $r;
            } else {
                $children[(int) $r['parent_id']][] = $r;
            }
        }
        foreach ($children as $parentId => $kids) {
            if (isset($top[$parentId])) {
                $top[$parentId]['children'] = $kids;
            }
            // «Осиротевшие» дети (родитель неактивен/не в выборке) не показываем.
        }

        return array_values($top);
    }

    /** Все пункты в виде дерева (для админки). */
    public static function allTree(): array
    {
        return self::buildTree(self::all());
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM menu_items WHERE id = :id');
        $stmt->execute([':id' => $id]);
        self::$activeRequestCache = [];
    }

    public static function move(int $id, string $direction): void
    {
        $item = self::findById($id);
        if (!$item) {
            return;
        }
        $siblings = array_values(array_filter(
            self::all(),
            static fn (array $r) => (string) $r['lang'] === (string) $item['lang']
                && (($r['parent_id'] === null && $item['parent_id'] === null)
                    || (int) $r['parent_id'] === (int) $item['parent_id'])
        ));

        $index = null;
        foreach ($siblings as $i => $s) {
            if ((int) $s['id'] === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swap < 0 || $swap >= count($siblings)) {
            return;
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE menu_items SET sort_order = :order WHERE id = :id');
            $stmt->execute([':order' => $siblings[$swap]['sort_order'], ':id' => $siblings[$index]['id']]);
            $stmt->execute([':order' => $siblings[$index]['sort_order'], ':id' => $siblings[$swap]['id']]);
            $pdo->commit();
            self::$activeRequestCache = [];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Разрешает конечный URL пункта меню с учётом языкового префикса.
     */
    public static function resolveUrl(array $item, string $lang): string
    {
        $prefix = $lang === Language::defaultCode() ? '' : '/' . $lang;

        return match ($item['url_type']) {
            'news_index' => $prefix . '/news',
            'page' => self::pageUrl((string) $item['url_value'], $prefix),
            default => (string) $item['url_value'],
        };
    }

    /**
     * URL страницы: главная ведёт на корень («/» или «/{lang}»), остальные —
     * на «/{slug}». Иначе ссылка на главную выглядела бы как «/home».
     */
    private static function pageUrl(string $slug, string $prefix): string
    {
        $slug = ltrim($slug, '/');
        $home = Page::homeSlug();
        if ($home !== '' && $slug === $home) {
            return $prefix !== '' ? $prefix : '/';
        }

        return $prefix . '/' . $slug;
    }
}
