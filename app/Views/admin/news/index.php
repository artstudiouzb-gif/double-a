<?php

use App\Core\Csrf;
use App\Models\Language;

$pageTitle = 'Новости';
$activeNav = 'news';
$pageActions = '<a href="/admin/news/create" class="btn btn--primary">+ Добавить новость</a>';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
/** @var array $filters */
/** @var array $filterParams */
/** @var int $total */
/** @var int $pages */
$langs = Language::active();
?>
<form method="get" action="/admin/news" class="list-filters list-filters--panel">
    <div class="list-filter list-filter--search"><label for="news_q">Поиск</label><input type="search" id="news_q" name="q" value="<?= htmlspecialchars($filters['q'], ENT_QUOTES) ?>" placeholder="Заголовок или slug"></div>
    <div class="list-filter"><label for="news_status">Статус</label><select id="news_status" name="status">
        <option value="">Все статусы</option><option value="published" <?= $filters['status'] === 'published' ? 'selected' : '' ?>>Опубликованные</option><option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Черновики</option>
    </select></div>
    <div class="list-filter"><label for="news_lang">Язык</label><select id="news_lang" name="lang">
        <option value="">Все языки</option>
        <?php foreach ($langs as $l): ?><option value="<?= htmlspecialchars($l['code'], ENT_QUOTES) ?>" <?= $filters['lang'] === $l['code'] ? 'selected' : '' ?>><?= $l['code'] === Language::defaultCode() ? 'Основной: ' : 'Есть перевод: ' ?><?= htmlspecialchars($l['name'], ENT_QUOTES) ?></option><?php endforeach; ?>
    </select></div>
    <div class="list-filter"><label for="news_from">Дата от</label><input type="date" id="news_from" name="from" value="<?= htmlspecialchars($filters['from'], ENT_QUOTES) ?>"></div>
    <div class="list-filter"><label for="news_to">Дата до</label><input type="date" id="news_to" name="to" value="<?= htmlspecialchars($filters['to'], ENT_QUOTES) ?>"></div>
    <div class="list-filter"><label for="news_sort">Сортировка</label><select id="news_sort" name="sort">
        <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Сначала новые</option><option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Сначала старые</option><option value="published_desc" <?= $filters['sort'] === 'published_desc' ? 'selected' : '' ?>>По дате публикации</option><option value="title_asc" <?= $filters['sort'] === 'title_asc' ? 'selected' : '' ?>>Название А–Я</option><option value="title_desc" <?= $filters['sort'] === 'title_desc' ? 'selected' : '' ?>>Название Я–А</option>
    </select></div>
    <div class="list-filter list-filter--compact"><label for="news_per_page">На странице</label><select id="news_per_page" name="per_page"><?php foreach ([20, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $filters['per_page'] === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?></select></div>
    <div class="list-filters__actions"><button type="submit" class="btn btn--primary">Применить</button><a href="/admin/news" class="btn">Сбросить</a></div>
</form>

<p class="list-results">Найдено: <strong><?= (int) $total ?></strong></p>

<form id="bulkform" method="post" action="/admin/bulk/news" class="bulk-bar" data-bulk-form>
    <?= Csrf::field() ?>
    <input type="hidden" name="return_query" value="<?= htmlspecialchars(http_build_query($filterParams), ENT_QUOTES) ?>">
    <select name="bulk_action" required>
        <option value="">С выбранными…</option>
        <option value="publish">Опубликовать</option>
        <option value="unpublish">Снять с публикации</option>
        <option value="duplicate">Дублировать</option>
        <option value="trash">В корзину</option>
    </select>
    <button type="submit" class="btn btn--small">Применить</button>
    <span class="bulk-bar__count" data-bulk-count>0 выбрано</span>
</form>

<table class="data-table">
    <thead>
        <tr>
            <th style="width:32px;"><input type="checkbox" data-select-all aria-label="Выбрать все"></th>
            <th>Заголовок</th>
            <th>Языки</th>
            <th>Статус</th>
            <th>Дата публикации</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="6" class="data-table__empty">Новостей не найдено.</td></tr>
        <?php endif; ?>
        <?php
        // Языки контента для всех строк одним запросом (без N+1) и список
        // активных языков сайта — чтобы показать и недостающие переводы.
        $langMap = \App\Models\News::availableLangsForIds(array_map(static fn ($i): int => (int) $i['id'], $items));
        $siteLangs = array_map(static fn (array $l): string => (string) $l['code'], $langs);
        $socialReady = \App\Core\SocialSettings::readyNetworks() !== [];
        ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>" form="bulkform" data-bulk-item></td>
                <td><a class="data-table__primary" href="/admin/news/<?= (int) $item['id'] ?>/edit"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></a></td>
                <td style="white-space:nowrap;">
                    <?php
                    $has = $langMap[(int) $item['id']] ?? [];
                    foreach ($siteLangs as $code):
                        $on = in_array($code, $has, true);
                        ?>
                        <span title="<?= $on ? 'Контент на этом языке есть' : 'Перевода нет' ?>"
                              style="display:inline-block;margin-right:4px;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:700;text-transform:uppercase;<?= $on
                                  ? 'background:#e6f4ea;color:#1e7e34;'
                                  : 'background:#f1f2f4;color:#9aa0a6;' ?>"><?= htmlspecialchars($code, ENT_QUOTES) ?></span>
                    <?php endforeach; ?>
                </td>
                <td>
                    <span class="badge badge--<?= $item['status'] ?>">
                        <?= $item['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
                    </span>
                </td>
                <td><?= $item['published_at'] ? htmlspecialchars($item['published_at'], ENT_QUOTES) : '—' ?></td>
                <td class="data-table__actions">
                    <a class="btn btn--small" href="/admin/news/<?= (int) $item['id'] ?>/edit">Редактировать</a>
                    <?php // В соцсети — только для опубликованных и когда сети настроены. ?>
                    <?php if ($socialReady && $item['status'] === 'published'): ?>
                        <form method="post" action="/admin/news/<?= (int) $item['id'] ?>/social"
                              data-confirm="Опубликовать «<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>» в соцсети?">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="from" value="list">
                            <input type="hidden" name="return_query" value="<?= htmlspecialchars(http_build_query($filterParams), ENT_QUOTES) ?>">
                            <button type="submit" class="btn btn--small">В соцсети</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/news/<?= (int) $item['id'] ?>/duplicate">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small">Дублировать</button>
                    </form>
                    <form method="post" action="/admin/news/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить новость «<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>»?">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="return_query" value="<?= htmlspecialchars(http_build_query($filterParams), ENT_QUOTES) ?>">
                        <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= \App\Core\View::renderPartial('admin/layout/pagination', ['paginationPath' => '/admin/news', 'filterParams' => $filterParams, 'page' => $filters['page'], 'pages' => $pages, 'total' => $total]) ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
