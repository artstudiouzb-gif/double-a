<?php

use App\Core\Csrf;

$pageTitle = 'Меню';
$activeNav = 'menu';
$pageActions = '<a href="#menu-add" class="btn btn--primary">' . \App\Core\AdminUi::icon('plus') . 'Добавить пункт</a>';
require __DIR__ . '/../layout/header.php';

/** @var array $tree */
/** @var array $items */
/** @var array $pages */
/** @var array $languages */

$urlTypeLabels = ['page' => 'Страница', 'news_index' => 'Раздел новостей', 'custom' => 'Произвольный URL'];
$parentCandidates = array_values(array_filter(
    $items,
    static fn (array $row): bool => $row['parent_id'] === null && empty($row['is_divider'])
));

/** Общие поля формы создания и редактирования пункта. */
$renderFields = static function (?array $item) use ($languages, $pages, $parentCandidates): string {
    $item ??= [];
    $id = isset($item['id']) ? (int) $item['id'] : 0;
    $prefix = $id > 0 ? 'menu_' . $id : 'menu_new';
    $urlType = (string) ($item['url_type'] ?? 'page');
    $urlValue = (string) ($item['url_value'] ?? '');
    $langCode = (string) ($item['lang'] ?? '');
    $parentId = isset($item['parent_id']) ? (int) $item['parent_id'] : 0;
    $isDivider = !empty($item['is_divider']);
    ob_start();
    ?>
    <div class="menu-form-fields" data-menu-link-form>
        <div class="form-field">
            <label for="<?= $prefix ?>_title">Название</label>
            <input type="text" id="<?= $prefix ?>_title" name="title"
                   value="<?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES) ?>"
                   placeholder="Например: О компании">
        </div>

        <div class="form-field">
            <label for="<?= $prefix ?>_lang">Язык</label>
            <select id="<?= $prefix ?>_lang" name="lang" data-menu-lang-select>
                <option value=""<?= $langCode === '' ? ' selected' : '' ?>>Все языки</option>
                <?php foreach ($languages as $lang): ?>
                    <option value="<?= htmlspecialchars((string) $lang['code'], ENT_QUOTES) ?>"<?= $langCode === (string) $lang['code'] ? ' selected' : '' ?>>
                        <?= htmlspecialchars((string) $lang['name'], ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field" data-menu-link-only>
            <label for="<?= $prefix ?>_type">Тип ссылки</label>
            <select id="<?= $prefix ?>_type" name="url_type" data-menu-url-type>
                <option value="page"<?= $urlType === 'page' ? ' selected' : '' ?>>Страница сайта</option>
                <option value="news_index"<?= $urlType === 'news_index' ? ' selected' : '' ?>>Раздел новостей</option>
                <option value="custom"<?= $urlType === 'custom' ? ' selected' : '' ?>>Произвольный URL</option>
            </select>
        </div>

        <div class="form-field" data-menu-url-field="page">
            <label for="<?= $prefix ?>_page">Страница</label>
            <select id="<?= $prefix ?>_page" name="page_slug" data-menu-page-select>
                <option value="">— выберите страницу —</option>
                <?php foreach ($pages as $page): ?>
                    <option value="<?= htmlspecialchars((string) $page['slug'], ENT_QUOTES) ?>" data-title="<?= htmlspecialchars((string) $page['title'], ENT_QUOTES) ?>"<?= $urlType === 'page' && $urlValue === (string) $page['slug'] ? ' selected' : '' ?>>
                        <?= htmlspecialchars((string) $page['title'], ENT_QUOTES) ?>
                        (/<?= htmlspecialchars((string) $page['slug'], ENT_QUOTES) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">В списке отображаются только опубликованные страницы.</span>
        </div>

        <div class="form-field" data-menu-url-field="custom">
            <label for="<?= $prefix ?>_url">URL</label>
            <input type="text" id="<?= $prefix ?>_url" name="custom_url"
                   value="<?= $urlType === 'custom' ? htmlspecialchars($urlValue, ENT_QUOTES) : '' ?>"
                   placeholder="/contacts или https://example.com">
        </div>

        <div class="form-field" data-menu-parent-field>
            <label for="<?= $prefix ?>_parent">Родительский пункт</label>
            <select id="<?= $prefix ?>_parent" name="parent_id" data-menu-parent-select>
                <option value="">— верхний уровень —</option>
                <?php foreach ($parentCandidates as $candidate): ?>
                    <?php if ($id > 0 && (int) $candidate['id'] === $id) { continue; } ?>
                    <option value="<?= (int) $candidate['id'] ?>"
                            data-lang="<?= htmlspecialchars((string) $candidate['lang'], ENT_QUOTES) ?>"
                            <?= $parentId === (int) $candidate['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $candidate['title'], ENT_QUOTES) ?>
                        <?= $candidate['lang'] !== '' ? '(' . htmlspecialchars((string) $candidate['lang'], ENT_QUOTES) . ')' : '(все языки)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Вложенность ограничена одним уровнем; язык родителя должен совпадать.</span>
        </div>

        <div class="form-field">
            <label for="<?= $prefix ?>_icon">SVG-иконка <span class="form-hint">(необязательно)</span></label>
            <textarea id="<?= $prefix ?>_icon" name="icon_svg" rows="3" placeholder="<svg viewBox=&quot;0 0 24 24&quot;>…</svg>"><?= htmlspecialchars((string) ($item['icon_svg'] ?? ''), ENT_QUOTES) ?></textarea>
            <span class="form-hint">Скрипты, обработчики событий и внешние ссылки удаляются автоматически.</span>
        </div>

        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="<?= $prefix ?>_divider" name="is_divider" value="1" data-menu-divider<?= $isDivider ? ' checked' : '' ?>>
            <label for="<?= $prefix ?>_divider">Разделитель без ссылки</label>
        </div>
        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="<?= $prefix ?>_active" name="is_active" value="1"<?= !isset($item['is_active']) || !empty($item['is_active']) ? ' checked' : '' ?>>
            <label for="<?= $prefix ?>_active">Показывать на сайте</label>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};

/** Строка структуры меню с полноширинной панелью редактирования. */
$renderNode = static function (array $item) use ($urlTypeLabels, $renderFields): string {
    $id = (int) $item['id'];
    $isDivider = !empty($item['is_divider']);
    $title = $isDivider ? 'Разделитель' : (string) $item['title'];
    $destination = $isDivider
        ? 'Без ссылки'
        : ($urlTypeLabels[$item['url_type']] ?? (string) $item['url_type']) . ': ' . ((string) ($item['url_value'] ?? '') ?: '/news');
    $editorId = 'menu-editor-' . $id;
    ob_start();
    ?>
    <div class="menu-node__row">
        <span class="menu-node__handle" draggable="true" title="Перетащите для сортировки" aria-hidden="true">⠿</span>
        <?php if (trim((string) ($item['icon_svg'] ?? '')) !== ''): ?>
            <span class="menu-node__icon" aria-hidden="true"><?= $item['icon_svg'] ?></span>
        <?php endif; ?>
        <span class="menu-node__content">
            <strong class="menu-node__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></strong>
            <span class="menu-node__meta"><?= htmlspecialchars($destination, ENT_QUOTES) ?></span>
        </span>
        <?php if (empty($item['is_active'])): ?><span class="badge badge--draft">Скрыт</span><?php endif; ?>
        <div class="menu-node__actions">
            <form method="post" action="/admin/menu/<?= $id ?>/move">
                <?= Csrf::field() ?><input type="hidden" name="direction" value="up">
                <button type="submit" class="btn btn--small menu-node__move" aria-label="Переместить вверх" title="Переместить вверх">↑</button>
            </form>
            <form method="post" action="/admin/menu/<?= $id ?>/move">
                <?= Csrf::field() ?><input type="hidden" name="direction" value="down">
                <button type="submit" class="btn btn--small menu-node__move" aria-label="Переместить вниз" title="Переместить вниз">↓</button>
            </form>
            <button type="button" class="btn btn--small" data-menu-edit-toggle aria-controls="<?= $editorId ?>" aria-expanded="false">Изменить</button>
            <form method="post" action="/admin/menu/<?= $id ?>/delete" data-confirm="Удалить пункт «<?= htmlspecialchars($title, ENT_QUOTES) ?>»<?= !empty($item['children']) ? ' вместе с вложенными пунктами' : '' ?>?">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn--small btn--danger" aria-label="Удалить <?= htmlspecialchars($title, ENT_QUOTES) ?>"><?= \App\Core\AdminUi::icon('trash') ?>Удалить</button>
            </form>
        </div>
    </div>
    <div class="menu-node__edit" id="<?= $editorId ?>" data-menu-edit-panel hidden>
        <form method="post" action="/admin/menu/<?= $id ?>/edit" class="form-grid">
            <?= Csrf::field() ?>
            <?= $renderFields($item) ?>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary"><?= \App\Core\AdminUi::icon('save') ?>Сохранить изменения</button>
                <button type="button" class="btn" data-menu-edit-close>Отмена</button>
            </div>
        </form>
    </div>
    <?php
    return (string) ob_get_clean();
};

$groups = [['code' => '', 'name' => 'Все языки']];
foreach ($languages as $language) {
    $groups[] = ['code' => (string) $language['code'], 'name' => (string) $language['name']];
}
?>

<p class="admin-hint">
    Структура разделена по языкам. Перетаскивайте пункт только за маркер ⠿ и нажмите
    «Сохранить» в появившейся панели. На телефоне используйте стрелки ↑/↓, а родителя выбирайте в редактировании.
</p>

<div class="menu-workspace">
    <aside class="form-card menu-add-panel" id="menu-add">
        <h2>Добавить пункт</h2>
        <form method="post" action="/admin/menu/create" class="form-grid">
            <?= Csrf::field() ?>
            <?= $renderFields(null) ?>
            <div class="form-actions"><button type="submit" class="btn btn--primary">Добавить в меню</button></div>
        </form>
    </aside>

    <section class="menu-structure" aria-labelledby="menu-structure-title">
        <div class="menu-structure__head">
            <div>
                <h2 id="menu-structure-title">Структура меню</h2>
                <p class="form-hint">Вложенность поддерживает один уровень.</p>
            </div>
            <span class="badge"><?= count($items) ?> пунктов</span>
        </div>

        <div class="menu-lang-tabs" role="tablist" aria-label="Язык меню">
            <?php foreach ($groups as $index => $group): ?>
                <button type="button" role="tab" class="menu-lang-tab<?= $index === 0 ? ' is-active' : '' ?>"
                        data-menu-lang-tab="<?= htmlspecialchars($group['code'], ENT_QUOTES) ?>"
                        aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                    <?= htmlspecialchars($group['name'], ENT_QUOTES) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($groups as $index => $group): ?>
            <?php $groupNodes = array_values(array_filter($tree, static fn (array $node): bool => (string) $node['lang'] === $group['code'])); ?>
            <div class="menu-lang-panel" data-menu-lang-panel="<?= htmlspecialchars($group['code'], ENT_QUOTES) ?>"<?= $index === 0 ? '' : ' hidden' ?>>
                <ul class="menu-tree" data-menu-sortable data-menu-lang="<?= htmlspecialchars($group['code'], ENT_QUOTES) ?>" data-csrf="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
                    <?php if ($groupNodes === []): ?>
                        <li class="menu-tree__empty">В этом языковом разделе пунктов пока нет.</li>
                    <?php endif; ?>
                    <?php foreach ($groupNodes as $node): ?>
                        <li class="menu-node<?= !empty($node['is_divider']) ? ' menu-node--divider' : '' ?>" data-menu-id="<?= (int) $node['id'] ?>" data-menu-lang="<?= htmlspecialchars((string) $node['lang'], ENT_QUOTES) ?>">
                            <?= $renderNode($node) ?>
                            <?php if (empty($node['is_divider'])): ?>
                                <ul class="menu-node__children" data-menu-children aria-label="Вложенные пункты <?= htmlspecialchars((string) $node['title'], ENT_QUOTES) ?>">
                                    <?php foreach ($node['children'] ?? [] as $child): ?>
                                        <li class="menu-node menu-node--child<?= (string) $child['lang'] !== (string) $node['lang'] ? ' menu-node--language-error' : '' ?>"
                                            data-menu-id="<?= (int) $child['id'] ?>" data-menu-lang="<?= htmlspecialchars((string) $child['lang'], ENT_QUOTES) ?>">
                                            <?= $renderNode($child) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
