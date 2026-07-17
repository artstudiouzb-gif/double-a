<?php

use App\Core\Csrf;
use App\Models\Language;

$pageTitle = 'Новости';
$activeNav = 'news';
$pageActions = '<a href="/admin/news/create" class="btn btn--primary">' . \App\Core\AdminUi::icon('plus') . 'Добавить новость</a>';
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
    <div class="list-filters__actions"><button type="submit" class="btn btn--primary"><?= \App\Core\AdminUi::icon('filter') ?>Применить</button><a href="/admin/news" class="btn"><?= \App\Core\AdminUi::icon('reset') ?>Сбросить</a></div>
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
            <th>Соцсети</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="7" class="data-table__empty">Новостей не найдено.</td></tr>
        <?php endif; ?>
        <?php
        // Языки контента для всех строк одним запросом (без N+1) и список
        // активных языков сайта — чтобы показать и недостающие переводы.
        $itemIds = array_map(static fn ($i): int => (int) $i['id'], $items);
        $langMap = \App\Models\News::availableLangsForIds($itemIds);
        $siteLangs = array_map(static fn (array $l): string => (string) $l['code'], $langs);
        $readyNets = \App\Core\SocialSettings::readyNetworks();
        // Статус публикации в соцсети по всем строкам одним запросом (без N+1).
        $socialStatus = \App\Models\SocialPost::statusForNewsIds($itemIds);
        $socialNetNames = ['telegram' => 'Telegram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram'];
        ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>" form="bulkform" data-bulk-item></td>
                <td class="data-table__flex"><a class="data-table__primary" href="/admin/news/<?= (int) $item['id'] ?>/edit"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></a></td>
                <td style="white-space:nowrap;"><?= \App\Core\View::renderPartial('admin/layout/lang_badges', ['siteLangs' => $siteLangs, 'has' => $langMap[(int) $item['id']] ?? []]) ?></td>
                <td>
                    <span class="badge badge--<?= $item['status'] ?>">
                        <?= $item['status'] === 'published' ? 'Опубликовано' : 'Черновик' ?>
                    </span>
                </td>
                <td><?= $item['published_at'] ? htmlspecialchars($item['published_at'], ENT_QUOTES) : '—' ?></td>
                <?php
                // Колонка «Соцсети»: статус прошлой публикации + кнопка отправки.
                $ss = $socialStatus[(int) $item['id']] ?? null;
                $sentNets = $ss ? array_values(array_unique(array_map(static fn (string $n): string => $socialNetNames[$n] ?? $n, $ss['networks']))) : [];
                $alreadySent = $ss !== null && $ss['sent'] > 0;
                $lastSent = $alreadySent && $ss['last_sent'] ? substr((string) $ss['last_sent'], 0, 16) : '';
                ?>
                <td class="news-social">
                    <?php if ($item['status'] !== 'published'): ?>
                        <span class="news-social__meta">—</span>
                    <?php else: ?>
                        <div class="news-social__state">
                            <?php if ($alreadySent): ?>
                                <span class="badge badge--success" title="<?= htmlspecialchars(implode(', ', $sentNets), ENT_QUOTES) ?>">✓ Опубликовано</span>
                                <span class="news-social__meta"><?= htmlspecialchars(implode(', ', $sentNets), ENT_QUOTES) ?><?= $lastSent !== '' ? ' · ' . htmlspecialchars($lastSent, ENT_QUOTES) : '' ?></span>
                            <?php elseif ($ss !== null && $ss['pending'] > 0): ?>
                                <span class="badge badge--draft">В очереди</span>
                            <?php elseif ($ss !== null && $ss['failed'] > 0): ?>
                                <span class="badge badge--danger">Ошибка</span>
                            <?php else: ?>
                                <span class="news-social__meta">не публиковалось</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($readyNets)): ?>
                            <div class="news-social__btns">
                                <?php foreach ($readyNets as $net): ?>
                                    <?php
                                    $netLabel = $socialNetNames[$net] ?? ucfirst($net);
                                    $netSent = $ss !== null && in_array($net, $ss['networks'], true);
                                    $netConfirm = $netSent
                                        ? 'Новость «' . $item['title'] . '» уже публиковалась в ' . $netLabel . '. Отправить повторно?'
                                        : 'Опубликовать «' . $item['title'] . '» в ' . $netLabel . '?';
                                    ?>
                                    <form method="post" action="/admin/news/<?= (int) $item['id'] ?>/social" data-confirm="<?= htmlspecialchars($netConfirm, ENT_QUOTES) ?>">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="from" value="list">
                                        <input type="hidden" name="network" value="<?= htmlspecialchars($net, ENT_QUOTES) ?>">
                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars(http_build_query($filterParams), ENT_QUOTES) ?>">
                                        <button type="submit" class="btn btn--small btn--social btn--social-<?= htmlspecialchars($net, ENT_QUOTES) ?>" title="<?= $netSent ? 'Опубликовать снова' : 'Опубликовать' ?>">
                                            <?= \App\Core\AdminUi::icon($net) ?><?= htmlspecialchars($netLabel, ENT_QUOTES) ?><?= $netSent ? ' ✓' : '' ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="data-table__actions">
                    <a class="btn btn--small" href="/admin/news/<?= (int) $item['id'] ?>/edit"><?= \App\Core\AdminUi::icon('edit') ?>Редактировать</a>
                    <form method="post" action="/admin/news/<?= (int) $item['id'] ?>/duplicate">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small"><?= \App\Core\AdminUi::icon('copy') ?>Дублировать</button>
                    </form>
                    <form method="post" action="/admin/news/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить новость «<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>»?">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="return_query" value="<?= htmlspecialchars(http_build_query($filterParams), ENT_QUOTES) ?>">
                        <button type="submit" class="btn btn--small btn--danger"><?= \App\Core\AdminUi::icon('trash') ?>Удалить</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?= \App\Core\View::renderPartial('admin/layout/pagination', ['paginationPath' => '/admin/news', 'filterParams' => $filterParams, 'page' => $filters['page'], 'pages' => $pages, 'total' => $total]) ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
