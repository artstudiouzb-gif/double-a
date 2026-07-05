<?php

use App\Core\Csrf;

$isEdit = !empty($page['id']);
$pageTitle = $isEdit ? 'Редактирование страницы' : 'Новая страница';
$activeNav = 'pages';
require __DIR__ . '/../layout/header.php';

/** @var array|null $page */
/** @var string|null $error */
/** @var array $blocks */
$blocks = $blocks ?? [];

$action = $isEdit ? '/admin/pages/' . (int) $page['id'] . '/edit' : '/admin/pages/create';

$blockTypeLabels = [
    'text' => 'Текст',
    'html' => 'Произвольный HTML',
    'cta' => 'Призыв к действию (CTA)',
    'advantages' => 'Преимущества',
    'slider' => 'Слайдер',
    'gallery' => 'Галерея',
    'form' => 'Форма',
];
?>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="<?= $action ?>" class="form-grid">
        <?= Csrf::field() ?>

        <div class="form-field">
            <label for="title">Заголовок страницы</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($page['title'] ?? '', ENT_QUOTES) ?>" required>
        </div>

        <div class="form-field">
            <label for="slug">ЧПУ (slug)</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($page['slug'] ?? '', ENT_QUOTES) ?>" placeholder="оставьте пустым для автогенерации">
            <span class="form-hint">Итоговый адрес: /&lt;slug&gt;</span>
        </div>

        <div class="form-field">
            <label for="meta_title">SEO: meta title</label>
            <input type="text" id="meta_title" name="meta_title" value="<?= htmlspecialchars($page['meta_title'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="meta_description">SEO: meta description</label>
            <textarea id="meta_description" name="meta_description"><?= htmlspecialchars($page['meta_description'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <div class="form-field">
            <label for="status">Статус</label>
            <select id="status" name="status">
                <option value="draft" <?= ($page['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                <option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>Опубликовано</option>
            </select>
        </div>

        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="is_home" name="is_home" value="1" <?= !empty($page['is_home']) ? 'checked' : '' ?>>
            <label for="is_home">Сделать главной страницей сайта</label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
            <a href="/admin/pages" class="btn">Отмена</a>
        </div>
    </form>
</div>

<?php if ($isEdit): ?>
    <h2 style="margin-top:40px;">Блоки страницы</h2>

    <?php if (empty($blocks)): ?>
        <p class="form-hint">На странице пока нет блоков.</p>
    <?php endif; ?>

    <?php foreach ($blocks as $index => $block): ?>
        <div class="block-list-item">
            <div class="block-list-item__meta">
                <strong><?= htmlspecialchars($block['title'] ?: ('Блок #' . $block['id']), ENT_QUOTES) ?></strong>
                <span class="block-list-item__type"><?= htmlspecialchars($blockTypeLabels[$block['type']] ?? $block['type'], ENT_QUOTES) ?></span>
            </div>
            <div class="block-list-item__actions">
                <form method="post" action="/admin/blocks/<?= (int) $block['id'] ?>/move">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="direction" value="up">
                    <button type="submit" class="btn btn--small" <?= $index === 0 ? 'disabled' : '' ?>>&uarr;</button>
                </form>
                <form method="post" action="/admin/blocks/<?= (int) $block['id'] ?>/move">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="direction" value="down">
                    <button type="submit" class="btn btn--small" <?= $index === count($blocks) - 1 ? 'disabled' : '' ?>>&darr;</button>
                </form>
                <a class="btn btn--small" href="/admin/blocks/<?= (int) $block['id'] ?>/edit">Редактировать</a>
                <form method="post" action="/admin/blocks/<?= (int) $block['id'] ?>/delete" data-confirm="Удалить блок?">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="form-card" style="margin-top:20px;">
        <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/blocks/add" class="form-grid">
            <?= Csrf::field() ?>
            <div class="form-field">
                <label for="type">Добавить блок</label>
                <select id="type" name="type">
                    <?php foreach ($blockTypeLabels as $type => $label): ?>
                        <option value="<?= $type ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="block_title">Внутреннее название блока (необязательно)</label>
                <input type="text" id="block_title" name="title" placeholder="например: Слайдер на главной">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Добавить блок</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
