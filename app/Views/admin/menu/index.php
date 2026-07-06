<?php

use App\Core\Csrf;

$pageTitle = 'Меню';
$activeNav = 'menu';
require __DIR__ . '/../layout/header.php';

/** @var array $tree */
/** @var array $items */
/** @var array $pages */
/** @var array $languages */

$urlTypeLabels = ['page' => 'Страница', 'news_index' => 'Раздел новостей', 'custom' => 'Произвольный URL'];

/**
 * Рендер одной строки пункта меню (переиспользуется для верхнего уровня и детей).
 */
$renderNode = function (array $item) use ($urlTypeLabels): string {
    $title = htmlspecialchars($item['title'], ENT_QUOTES);
    $type = htmlspecialchars($urlTypeLabels[$item['url_type']] ?? $item['url_type'], ENT_QUOTES);
    $dest = htmlspecialchars((string) ($item['url_value'] ?? '—'), ENT_QUOTES);
    $lang = $item['lang'] === '' ? 'все' : htmlspecialchars($item['lang'], ENT_QUOTES);
    $active = $item['is_active'] ? '✓' : '—';
    $hasChildren = !empty($item['children']);
    $confirm = $hasChildren
        ? 'Удалить пункт вместе с вложенными? Дочерние пункты будут удалены безвозвратно.'
        : 'Удалить пункт меню?';

    $html = '<div class="menu-node__row">';
    $html .= '<span class="menu-node__handle" title="Перетащите для сортировки/вложенности" aria-hidden="true">⠿</span>';
    $html .= '<span class="menu-node__title">' . $title . '</span>';
    $html .= '<span class="menu-node__meta">' . $type . ' · ' . $dest . ' · ' . $lang . ' · ' . $active . '</span>';
    $html .= '<span class="menu-node__actions">';
    $html .= '<form method="post" action="/admin/menu/' . (int) $item['id'] . '/delete" data-confirm="'
        . htmlspecialchars($confirm, ENT_QUOTES) . '">' . Csrf::field()
        . '<button class="btn btn--small btn--danger">Удалить</button></form>';
    $html .= '</span></div>';

    return $html;
};
?>
<p class="admin-hint">
    Перетаскивайте пункты для сортировки. Чтобы создать выпадающее подменю,
    перетащите пункт «внутрь» другого (в его область с отступом). Глубина
    ограничена одним уровнем.
</p>

<ul class="menu-tree" data-menu-sortable data-csrf="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>" style="margin-bottom:30px;">
    <?php if (empty($tree)): ?>
        <li class="menu-tree__empty">Пунктов меню пока нет.</li>
    <?php endif; ?>
    <?php foreach ($tree as $node): ?>
        <li class="menu-node" data-menu-id="<?= (int) $node['id'] ?>" draggable="true">
            <?= $renderNode($node) ?>
            <ul class="menu-node__children" data-menu-children>
                <?php foreach ($node['children'] ?? [] as $child): ?>
                    <li class="menu-node menu-node--child" data-menu-id="<?= (int) $child['id'] ?>" draggable="true">
                        <?= $renderNode($child) ?>
                        <ul class="menu-node__children" data-menu-children></ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        </li>
    <?php endforeach; ?>
</ul>

<div class="form-card">
    <h2 style="margin-top:0;">Добавить пункт меню</h2>
    <form method="post" action="/admin/menu/create" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="title">Название</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-field">
            <label for="url_type">Тип ссылки</label>
            <select id="url_type" name="url_type">
                <option value="page">Страница сайта</option>
                <option value="news_index">Раздел новостей</option>
                <option value="custom">Произвольный URL</option>
            </select>
        </div>
        <div class="form-field">
            <label for="url_value">Страница (для типа «Страница») или URL (для «Произвольный»)</label>
            <input type="text" id="url_value" name="url_value" list="page-slugs" placeholder="slug страницы или https://...">
            <datalist id="page-slugs">
                <?php foreach ($pages as $p): ?>
                    <option value="<?= htmlspecialchars($p['slug'], ENT_QUOTES) ?>"><?= htmlspecialchars($p['title'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </datalist>
            <span class="form-hint">Для «Страница» укажите её slug. Для «Раздел новостей» поле можно оставить пустым.</span>
        </div>
        <div class="form-field">
            <label for="lang">Язык</label>
            <select id="lang" name="lang">
                <option value="">Все языки</option>
                <?php foreach ($languages as $lang): ?>
                    <option value="<?= htmlspecialchars($lang['code'], ENT_QUOTES) ?>"><?= htmlspecialchars($lang['name'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="parent_id">Родительский пункт (для подменю)</label>
            <select id="parent_id" name="parent_id">
                <option value="">— верхний уровень —</option>
                <?php foreach ($tree as $node): // родителями могут быть только пункты верхнего уровня ?>
                    <option value="<?= (int) $node['id'] ?>" data-lang="<?= htmlspecialchars((string) $node['lang'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars($node['title'], ENT_QUOTES) ?><?= $node['lang'] !== '' ? ' (' . htmlspecialchars($node['lang'], ENT_QUOTES) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Вложенность — один уровень. Родитель и пункт должны быть на одном языке.</span>
        </div>
        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="is_active" name="is_active" value="1" checked>
            <label for="is_active">Активен</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Добавить</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
