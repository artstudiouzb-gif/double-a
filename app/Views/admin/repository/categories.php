<?php

use App\Core\Csrf;

$pageTitle = 'Хранилище: категории';
$activeNav = 'repository';
require __DIR__ . '/../layout/header.php';

/** @var list<array> $tree */

// Строка таблицы: имя (с отступом у подкатегории), число файлов, действия.
$row = static function (array $cat, bool $isChild): void {
    ?>
    <tr>
        <td>
            <?= $isChild ? '<span style="color:#9aa4b2;">└</span> ' : '' ?>
            <?= $isChild ? '' : '<strong>' ?><?= htmlspecialchars((string) $cat['name'], ENT_QUOTES) ?><?= $isChild ? '' : '</strong>' ?>
        </td>
        <td><?= (int) $cat['files_count'] ?></td>
        <td class="data-table__actions">
            <details style="position:relative;display:inline-block;">
                <summary class="btn btn--small" style="list-style:none;cursor:pointer;">Переименовать</summary>
                <form method="post" action="/admin/repository/categories/<?= (int) $cat['id'] ?>/rename" class="form-card" style="position:absolute;right:0;z-index:10;width:260px;padding:12px;text-align:left;box-shadow:0 8px 24px rgba(16,24,40,.18);">
                    <?= Csrf::field() ?>
                    <div class="form-field">
                        <label>Новое название</label>
                        <input type="text" name="name" required maxlength="120" value="<?= htmlspecialchars((string) $cat['name'], ENT_QUOTES) ?>">
                    </div>
                    <button type="submit" class="btn btn--small btn--primary">Сохранить</button>
                </form>
            </details>
            <form method="post" action="/admin/repository/categories/<?= (int) $cat['id'] ?>/delete" data-confirm="Удалить категорию «<?= htmlspecialchars((string) $cat['name'], ENT_QUOTES) ?>»?<?= !$isChild ? ' Её подкатегории тоже удалятся.' : '' ?> Файлы останутся без категории.">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn--small btn--danger">Удалить</button>
            </form>
        </td>
    </tr>
    <?php
};
?>
<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="/admin/repository" class="btn btn--small">Файлы</a>
    <a href="/admin/repository/categories" class="btn btn--small btn--primary">Категории</a>
    <a href="/admin/repository/users" class="btn btn--small">Пользователи портала</a>
</div>
<p class="form-hint">Категории файлового хранилища. Один уровень вложенности: категория → подкатегории. На портале фильтр по корневой категории показывает и файлы её подкатегорий.</p>

<div class="form-card" style="margin-bottom:24px;max-width:520px;">
    <h2 style="margin-top:0;">Добавить категорию</h2>
    <form method="post" action="/admin/repository/categories/create" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="name">Название</label>
            <input type="text" id="name" name="name" required maxlength="120" placeholder="напр. Приказы">
        </div>
        <div class="form-field">
            <label for="parent_id">Родительская категория</label>
            <select id="parent_id" name="parent_id">
                <option value="0">— Нет (корневая категория) —</option>
                <?php foreach ($tree as $root): ?>
                    <option value="<?= (int) $root['id'] ?>"><?= htmlspecialchars((string) $root['name'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn--primary">Добавить</button></div>
    </form>
</div>

<table class="data-table">
    <thead>
        <tr><th>Категория</th><th>Файлов</th><th></th></tr>
    </thead>
    <tbody>
        <?php if (empty($tree)): ?>
            <tr><td colspan="3" style="text-align:center;color:#888;padding:24px;">Категорий пока нет.</td></tr>
        <?php else: ?>
            <?php foreach ($tree as $root): ?>
                <?php $row($root, false); ?>
                <?php foreach ($root['children'] as $child): ?>
                    <?php $row($child, true); ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../layout/footer.php'; ?>
