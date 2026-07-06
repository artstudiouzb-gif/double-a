<?php

use App\Core\Csrf;
use App\Models\Language;

$isEdit = !empty($page['id']);
$pageTitle = $isEdit ? 'Редактирование страницы' : 'Новая страница';
$activeNav = 'pages';
require __DIR__ . '/../layout/header.php';

/** @var array|null $page */
/** @var array $translations */
/** @var string|null $error */
/** @var array $blocks */
$blocks = $blocks ?? [];
$blockLang = $blockLang ?? Language::defaultCode();

$action = $isEdit ? '/admin/pages/' . (int) $page['id'] . '/edit' : '/admin/pages/create';
$defaultCode = Language::defaultCode();
$languages = Language::active();

$blockTypeLabels = [
    'text' => 'Текст',
    'html' => 'Произвольный HTML',
    'cta' => 'Призыв к действию (CTA)',
    'advantages' => 'Преимущества',
    'slider' => 'Слайдер',
    'gallery' => 'Галерея',
    'form' => 'Форма',
    'columns' => 'Колонки',
];

// Дочерние блоки колонок (группа 4.1): подгружаем детей каждого columns-блока.
$columnsChildren = [];
foreach ($blocks as $b) {
    if ($b['type'] === 'columns') {
        $columnsChildren[(int) $b['id']] = \App\Models\Block::childrenOf((int) $b['id']);
    }
}
?>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="<?= $action ?>" class="form-grid">
        <?= Csrf::field() ?>

        <div data-lang-tabs>
            <div class="lang-tabs">
                <?php foreach ($languages as $i => $lang): ?>
                    <button type="button" class="lang-tab-btn <?= $i === 0 ? 'is-active' : '' ?>" data-lang-target="<?= htmlspecialchars($lang['code'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>
                        <?php if ($lang['code'] === $defaultCode): ?><span class="lang-tab-btn__badge">(основной)</span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($languages as $i => $lang): ?>
                <?php $code = (string) $lang['code']; $isDefault = $code === $defaultCode; ?>
                <div class="lang-tab-panel <?= $i === 0 ? 'is-active' : '' ?>" data-lang-panel="<?= htmlspecialchars($code, ENT_QUOTES) ?>">
                    <?php if ($isDefault): ?>
                        <div class="form-field">
                            <label>Заголовок страницы</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($page['title'] ?? '', ENT_QUOTES) ?>" required>
                        </div>
                        <div class="form-field">
                            <label>SEO: meta title</label>
                            <input type="text" name="meta_title" value="<?= htmlspecialchars($page['meta_title'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>SEO: meta description</label>
                            <textarea name="meta_description"><?= htmlspecialchars($page['meta_description'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                    <?php else: ?>
                        <?php $t = $translations[$code] ?? []; ?>
                        <p class="form-hint">Перевод для языка «<?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>». Пустой заголовок на сайте заменяется версией основного языка.</p>
                        <div class="form-field">
                            <label>Заголовок страницы</label>
                            <input type="text" name="translations[<?= $code ?>][title]" value="<?= htmlspecialchars($t['title'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>SEO: meta title</label>
                            <input type="text" name="translations[<?= $code ?>][meta_title]" value="<?= htmlspecialchars($t['meta_title'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>SEO: meta description</label>
                            <textarea name="translations[<?= $code ?>][meta_description]"><?= htmlspecialchars($t['meta_description'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <hr style="border:none;border-top:1px solid var(--admin-border);margin:6px 0;">

        <div class="form-field">
            <label for="slug">ЧПУ (slug) — общий для всех языков</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($page['slug'] ?? '', ENT_QUOTES) ?>" placeholder="оставьте пустым для автогенерации">
            <span class="form-hint">Адрес: /&lt;slug&gt; (основной язык), /<?= htmlspecialchars($languages[1]['code'] ?? 'uz', ENT_QUOTES) ?>/&lt;slug&gt; (другой язык)</span>
        </div>

        <div class="form-field">
            <label for="layout_type">Макет страницы</label>
            <select id="layout_type" name="layout_type">
                <option value="no_sidebar" <?= ($page['layout_type'] ?? 'no_sidebar') === 'no_sidebar' ? 'selected' : '' ?>>Без сайдбара (на всю ширину)</option>
                <option value="left_sidebar" <?= ($page['layout_type'] ?? '') === 'left_sidebar' ? 'selected' : '' ?>>Левый сайдбар</option>
                <option value="right_sidebar" <?= ($page['layout_type'] ?? '') === 'right_sidebar' ? 'selected' : '' ?>>Правый сайдбар</option>
            </select>
            <span class="form-hint">Виджеты сайдбара настраиваются в разделе «Виджеты».</span>
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
            <?php if ($isEdit): ?>
                <a href="/admin/pages/<?= (int) $page['id'] ?>/preview?block_lang=<?= urlencode($blockLang) ?>"
                   class="btn" target="_blank" rel="noopener">Предпросмотр ↗</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($isEdit): ?>
    <h2 style="margin-top:40px;">Блоки страницы</h2>

    <div class="lang-tabs" style="margin-bottom:16px;">
        <?php foreach ($languages as $lang): ?>
            <a class="lang-tab-btn <?= (string) $lang['code'] === $blockLang ? 'is-active' : '' ?>"
               href="/admin/pages/<?= (int) $page['id'] ?>/edit?block_lang=<?= urlencode((string) $lang['code']) ?>">
                Блоки: <?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="form-hint">У каждого языка свой независимый стек блоков. Если стек языка пуст, на сайте показывается стек основного языка.</p>

    <?php if (empty($blocks)): ?>
        <p class="form-hint">На этом языке блоков пока нет.</p>
    <?php endif; ?>

    <?php if (!empty($blocks)): ?>
        <p class="form-hint">Перетаскивайте блоки за значок ⠿ для изменения порядка (сохраняется автоматически).</p>
    <?php endif; ?>
    <div class="block-list" data-block-sortable
         data-page-id="<?= (int) $page['id'] ?>"
         data-block-lang="<?= htmlspecialchars($blockLang, ENT_QUOTES) ?>"
         data-csrf="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">
    <?php foreach ($blocks as $index => $block): ?>
        <div class="block-list-item" draggable="true" data-block-id="<?= (int) $block['id'] ?>">
            <span class="block-list-item__handle" title="Перетащить" aria-hidden="true">⠿</span>
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
        <?php if ($block['type'] === 'columns'):
            $cdata = json_decode((string) $block['data'], true) ?: [];
            $colCount = (int) ($cdata['columns'] ?? 2);
            if ($colCount < 2 || $colCount > 4) { $colCount = 2; }
            $kids = $columnsChildren[(int) $block['id']] ?? [];
        ?>
        <div class="columns-editor" style="margin:4px 0 16px 32px;">
            <div class="columns-editor__grid columns-editor__grid--<?= $colCount ?>">
                <?php for ($ci = 0; $ci < $colCount; $ci++): ?>
                    <div class="columns-editor__col">
                        <div class="columns-editor__col-title">Колонка <?= $ci + 1 ?></div>
                        <?php foreach ($kids as $kid): if ((int) $kid['column_index'] !== $ci) { continue; } ?>
                            <div class="columns-editor__child">
                                <span><?= htmlspecialchars($kid['title'] ?: ($blockTypeLabels[$kid['type']] ?? $kid['type']), ENT_QUOTES) ?></span>
                                <span class="columns-editor__child-actions">
                                    <a class="btn btn--small" href="/admin/blocks/<?= (int) $kid['id'] ?>/edit">✎</a>
                                    <form method="post" action="/admin/blocks/<?= (int) $kid['id'] ?>/delete" data-confirm="Удалить вложенный блок?">
                                        <?= Csrf::field() ?><button class="btn btn--small btn--danger">×</button>
                                    </form>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/blocks/add" class="columns-editor__add">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="block_lang" value="<?= htmlspecialchars($blockLang, ENT_QUOTES) ?>">
                            <input type="hidden" name="parent_block_id" value="<?= (int) $block['id'] ?>">
                            <input type="hidden" name="column_index" value="<?= $ci ?>">
                            <select name="type" aria-label="Тип вложенного блока">
                                <?php foreach ($blockTypeLabels as $t => $lbl):
                                    if ($t === 'columns') { continue; } // без columns-в-columns
                                    if ($t === 'html' && !\App\Core\Auth::isSuperAdmin()) { continue; }
                                ?>
                                    <option value="<?= htmlspecialchars($t, ENT_QUOTES) ?>"><?= htmlspecialchars($lbl, ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn--small">+ блок</button>
                        </form>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>

    <?php $snippets = \App\Models\BlockSnippet::all(); ?>
    <div class="form-card" style="margin-top:16px;">
        <h3 style="margin-top:0;">Шаблоны блоков</h3>
        <div class="snippet-tools">
            <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/snippets/save" class="snippet-tools__row">
                <?= Csrf::field() ?>
                <input type="hidden" name="block_lang" value="<?= htmlspecialchars($blockLang, ENT_QUOTES) ?>">
                <input type="text" name="snippet_name" placeholder="Название шаблона" required>
                <button type="submit" class="btn btn--small">Сохранить блоки как шаблон</button>
            </form>
            <?php if (!empty($snippets)): ?>
                <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/snippets/insert" class="snippet-tools__row">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="block_lang" value="<?= htmlspecialchars($blockLang, ENT_QUOTES) ?>">
                    <select name="snippet_id" required>
                        <option value="">— выберите шаблон —</option>
                        <?php foreach ($snippets as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars((string) $s['name'], ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn--small">Вставить шаблон</button>
                </form>
            <?php else: ?>
                <p class="form-hint">Пока нет сохранённых шаблонов.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-card" style="margin-top:20px;">
        <form method="post" action="/admin/pages/<?= (int) $page['id'] ?>/blocks/add" class="form-grid">
            <?= Csrf::field() ?>
            <input type="hidden" name="block_lang" value="<?= htmlspecialchars($blockLang, ENT_QUOTES) ?>">
            <div class="form-field">
                <label for="type">Добавить блок (язык: <?= htmlspecialchars($blockLang, ENT_QUOTES) ?>)</label>
                <select id="type" name="type">
                    <?php foreach ($blockTypeLabels as $type => $label): ?>
                        <?php // Блок сырого HTML доступен только супер-администратору. ?>
                        <?php if ($type === 'html' && !\App\Core\Auth::isSuperAdmin()) { continue; } ?>
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
