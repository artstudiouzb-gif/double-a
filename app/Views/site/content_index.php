<?php

use App\Core\ContentFields;
use App\Core\Locale;

/** @var array $type */
/** @var array $fields */
/** @var array $entries */
/** @var string $q */
/** @var string $sort */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
/** @var bool $hasDeadline */

$metaTitle = (string) $type['name'];
$metaDescription = (string) ($type['description'] ?? '');
require __DIR__ . '/_header.php';

$shortFields = array_values(array_filter($fields, static fn ($f) => in_array($f['field_type'], ['text', 'number', 'date'], true)));
$longFields = array_values(array_filter($fields, static fn ($f) => $f['field_type'] === 'textarea'));
$fileFields = array_values(array_filter($fields, static fn ($f) => in_array($f['field_type'], ['file', 'image'], true)));

$baseUrl = Locale::url('catalog/' . $type['slug']);
$qs = static function (array $overrides) use ($q, $sort): string {
    $params = array_filter(array_merge(['q' => $q, 'sort' => $sort], $overrides), static fn ($v) => $v !== '' && $v !== null);
    return $params === [] ? '' : '?' . http_build_query($params);
};
?>
<div class="content-list">
    <nav class="content-crumbs" aria-label="Хлебные крошки">
        <a href="<?= htmlspecialchars(Locale::url('/'), ENT_QUOTES) ?>">Главная</a>
        <span>/</span>
        <span><?= htmlspecialchars((string) $type['name'], ENT_QUOTES) ?></span>
    </nav>

    <header class="content-list__head">
        <h1><?= htmlspecialchars((string) $type['name'], ENT_QUOTES) ?></h1>
        <?php if (!empty($type['description'])): ?>
            <p class="content-list__lead"><?= htmlspecialchars((string) $type['description'], ENT_QUOTES) ?></p>
        <?php endif; ?>
    </header>

    <form class="content-toolbar" method="get" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>" role="search">
        <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" placeholder="Поиск в разделе">
        <select name="sort" onchange="this.form.submit()" aria-label="Сортировка">
            <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Сначала новые</option>
            <option value="old" <?= $sort === 'old' ? 'selected' : '' ?>>Сначала старые</option>
            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>По алфавиту</option>
        </select>
        <button type="submit">Найти</button>
        <?php if ($q !== ''): ?><a class="content-toolbar__reset" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>">Сброс</a><?php endif; ?>
    </form>

    <?php if (empty($entries)): ?>
        <p class="content-list__empty">
            <?= $q !== '' ? 'По вашему запросу ничего не найдено.' : 'В этом разделе пока нет опубликованных записей.' ?>
        </p>
    <?php else: ?>
        <p class="content-list__count">Найдено: <?= (int) $total ?></p>
        <div class="content-cards">
            <?php foreach ($entries as $entry): ?>
                <?php $url = Locale::url('catalog/' . $type['slug'] . '/' . $entry['slug']); ?>
                <article class="content-card<?= !empty($entry['is_archived']) ? ' content-card--archived' : '' ?>">
                    <h2 class="content-card__title">
                        <a href="<?= htmlspecialchars($url, ENT_QUOTES) ?>"><?= htmlspecialchars((string) $entry['title'], ENT_QUOTES) ?></a>
                        <?php if ($hasDeadline): ?>
                            <span class="content-badge content-badge--<?= !empty($entry['is_archived']) ? 'archived' : 'active' ?>"><?= !empty($entry['is_archived']) ? 'Архив' : 'Активна' ?></span>
                        <?php endif; ?>
                    </h2>
                    <?php
                    $meta = [];
                    foreach ($shortFields as $f) {
                        $val = ContentFields::displayValue($f, $entry['data'][$f['name']] ?? null);
                        if ($val !== '') {
                            $meta[] = '<span class="content-card__meta-item"><b>' . htmlspecialchars((string) $f['label'], ENT_QUOTES) . ':</b> ' . $val . '</span>';
                        }
                    }
                    ?>
                    <?php if ($meta !== []): ?><div class="content-card__meta"><?= implode('', $meta) ?></div><?php endif; ?>
                    <?php foreach ($longFields as $f): ?>
                        <?php $val = ContentFields::displayValue($f, $entry['data'][$f['name']] ?? null); ?>
                        <?php if ($val !== ''): ?><p class="content-card__excerpt"><?= $val ?></p><?php break; endif; ?>
                    <?php endforeach; ?>
                    <div class="content-card__foot">
                        <?php foreach ($fileFields as $f): ?>
                            <?php $val = ContentFields::displayValue($f, $entry['data'][$f['name']] ?? null); ?>
                            <?php if ($val !== '' && $f['field_type'] === 'file'): ?><span class="content-card__file">📎 <?= $val ?></span><?php endif; ?>
                        <?php endforeach; ?>
                        <a class="content-card__more" href="<?= htmlspecialchars($url, ENT_QUOTES) ?>">Подробнее →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="content-pager" aria-label="Постраничная навигация">
                <?php if ($page > 1): ?><a class="content-pager__link" href="<?= htmlspecialchars($baseUrl . $qs(['page' => $page - 1]), ENT_QUOTES) ?>">← Назад</a><?php endif; ?>
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <?php if ($p === $page): ?>
                        <span class="content-pager__link is-current"><?= $p ?></span>
                    <?php else: ?>
                        <a class="content-pager__link" href="<?= htmlspecialchars($baseUrl . $qs(['page' => $p]), ENT_QUOTES) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $pages): ?><a class="content-pager__link" href="<?= htmlspecialchars($baseUrl . $qs(['page' => $page + 1]), ENT_QUOTES) ?>">Вперёд →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
