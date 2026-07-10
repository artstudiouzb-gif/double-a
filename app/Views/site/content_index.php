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

$crumbs = [
    ['label' => 'Главная', 'url' => Locale::url('/')],
    ['label' => (string) $type['name']],
];
require __DIR__ . '/_crumbs.php';

$shortFields = array_values(array_filter($fields, static fn ($f) => in_array($f['field_type'], ['text', 'number', 'date'], true)));
$longFields = array_values(array_filter($fields, static fn ($f) => $f['field_type'] === 'textarea'));
$fileFields = array_values(array_filter($fields, static fn ($f) => $f['field_type'] === 'file'));
// Типы с датой проведения (мероприятия) получают карточку с датой-плиткой.
$isEvents = array_filter($fields, static fn ($f) => $f['name'] === 'event_date' && $f['field_type'] === 'date') !== [];
$months = ['ЯНВ', 'ФЕВ', 'МАР', 'АПР', 'МАЙ', 'ИЮН', 'ИЮЛ', 'АВГ', 'СЕН', 'ОКТ', 'НОЯ', 'ДЕК'];

$baseUrl = Locale::url('catalog/' . $type['slug']);
$qs = static function (array $overrides) use ($q, $sort): string {
    $params = array_filter(array_merge(['q' => $q, 'sort' => $sort === 'new' ? '' : $sort], $overrides), static fn ($v) => $v !== '' && $v !== null);
    return $params === [] ? '' : '?' . http_build_query($params);
};
?>
<div class="listing">
    <div class="listing__head">
        <h1 class="listing__title"><?= htmlspecialchars((string) $type['name'], ENT_QUOTES) ?></h1>
        <?php if (!empty($type['description'])): ?>
            <p class="listing__lead"><?= htmlspecialchars((string) $type['description'], ENT_QUOTES) ?></p>
        <?php endif; ?>
    </div>

    <form class="catlist-toolbar" method="get" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>" role="search">
        <div class="catlist-toolbar__search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES) ?>" placeholder="Поиск в разделе" aria-label="Поиск в разделе">
        </div>
        <select class="catlist-toolbar__sort" name="sort" data-auto-submit aria-label="Сортировка">
            <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Сначала новые</option>
            <option value="old" <?= $sort === 'old' ? 'selected' : '' ?>>Сначала старые</option>
            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>По алфавиту</option>
        </select>
        <button class="catlist-toolbar__btn" type="submit">Найти</button>
        <?php if ($q !== ''): ?><a class="catlist-toolbar__reset" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES) ?>">Сбросить ↺</a><?php endif; ?>
    </form>

    <?php if (empty($entries)): ?>
        <p class="listing__empty">
            <?= $q !== '' ? 'По вашему запросу ничего не найдено.' : 'В этом разделе пока нет опубликованных записей.' ?>
        </p>
    <?php else: ?>
        <p class="catlist-count">Найдено: <b><?= (int) $total ?></b></p>
        <div class="catlist<?= $isEvents ? ' catlist--events' : '' ?>">
            <?php foreach ($entries as $entry): ?>
                <?php $url = Locale::url('catalog/' . $type['slug'] . '/' . $entry['slug']); ?>
                <article class="catcard<?= !empty($entry['is_archived']) ? ' catcard--archived' : '' ?>">
                    <?php if ($isEvents && !empty($entry['data']['event_date'])): ?>
                        <?php $ts = (int) strtotime((string) $entry['data']['event_date']); ?>
                        <span class="catcard__datebox" aria-hidden="true">
                            <b><?= date('d', $ts) ?></b>
                            <i><?= $months[(int) date('n', $ts) - 1] ?></i>
                            <em><?= date('Y', $ts) ?></em>
                        </span>
                    <?php endif; ?>
                    <div class="catcard__main">
                        <div class="catcard__top">
                            <?php if ($hasDeadline): ?>
                                <span class="catcard__status<?= !empty($entry['is_archived']) ? ' catcard__status--off' : '' ?>"><?= !empty($entry['is_archived']) ? 'Архив' : 'Приём открыт' ?></span>
                            <?php endif; ?>
                            <time class="catcard__created"><?= htmlspecialchars(date('d.m.Y', strtotime((string) $entry['created_at'])), ENT_QUOTES) ?></time>
                        </div>
                        <h2 class="catcard__title"><a href="<?= htmlspecialchars($url, ENT_QUOTES) ?>"><?= htmlspecialchars((string) $entry['title'], ENT_QUOTES) ?></a></h2>
                        <?php
                        $meta = [];
                        foreach ($shortFields as $f) {
                            if ($isEvents && $f['name'] === 'event_date') {
                                continue; // уже в плитке даты
                            }
                            $val = ContentFields::displayValue($f, $entry['data'][$f['name']] ?? null);
                            if ($val !== '') {
                                $meta[] = '<span class="catcard__meta-item"><i>' . htmlspecialchars((string) $f['label'], ENT_QUOTES) . '</i>' . $val . '</span>';
                            }
                        }
                        ?>
                        <?php if ($meta !== []): ?><div class="catcard__meta"><?= implode('', $meta) ?></div><?php endif; ?>
                        <?php foreach ($longFields as $f): ?>
                            <?php $val = ContentFields::displayValue($f, $entry['data'][$f['name']] ?? null); ?>
                            <?php if ($val !== ''): ?><p class="catcard__excerpt"><?= htmlspecialchars(mb_substr(trim(strip_tags((string) $val)), 0, 160), ENT_QUOTES) ?></p><?php break; endif; ?>
                        <?php endforeach; ?>
                        <div class="catcard__foot">
                            <?php foreach ($fileFields as $f): ?>
                                <?php if (!empty($entry['data'][$f['name']])): ?>
                                    <span class="catcard__file">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="14" height="14" aria-hidden="true"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/></svg>
                                        <?= htmlspecialchars((string) $f['label'], ENT_QUOTES) ?>
                                    </span>
                                    <?php break; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <a class="catcard__more" href="<?= htmlspecialchars($url, ENT_QUOTES) ?>">Подробнее →</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="listing-pager" aria-label="Страницы">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <?php if ($p === $page): ?>
                        <span class="listing-pager__item is-active" aria-current="page"><?= $p ?></span>
                    <?php else: ?>
                        <a class="listing-pager__item" href="<?= htmlspecialchars($baseUrl . $qs(['page' => $p > 1 ? $p : null]), ENT_QUOTES) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
