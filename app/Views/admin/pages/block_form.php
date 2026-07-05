<?php

use App\Core\Csrf;

$pageTitle = 'Редактирование блока';
$activeNav = 'pages';
require __DIR__ . '/../layout/header.php';

/** @var array $block */
/** @var array $data */
/** @var array $forms */

$type = $block['type'];
?>
<a href="/admin/pages/<?= (int) $block['page_id'] ?>/edit" class="btn btn--small" style="margin-bottom:16px;">&larr; Назад к странице</a>

<div class="form-card">
    <form method="post" action="/admin/blocks/<?= (int) $block['id'] ?>/edit" class="form-grid">
        <?= Csrf::field() ?>

        <div class="form-field">
            <label for="title">Внутреннее название блока</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($block['title'] ?? '', ENT_QUOTES) ?>">
        </div>

        <?php if (in_array($type, ['text', 'cta', 'advantages', 'gallery'], true)): ?>
            <div class="form-field">
                <label for="title_field">Заголовок, показываемый на сайте</label>
                <input type="text" id="title_field" name="title_field" value="<?= htmlspecialchars($data['title'] ?? '', ENT_QUOTES) ?>">
            </div>
        <?php endif; ?>

        <?php if ($type === 'text'): ?>
            <div class="form-field">
                <label for="content">Текст (допускается HTML)</label>
                <textarea id="content" name="content" style="min-height:200px;"><?= htmlspecialchars($data['content'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
        <?php endif; ?>

        <?php if ($type === 'html'): ?>
            <div class="form-field">
                <label for="html">HTML-код блока</label>
                <textarea id="html" name="html" style="min-height:200px; font-family: monospace;"><?= htmlspecialchars($data['html'] ?? '', ENT_QUOTES) ?></textarea>
                <span class="form-hint">Выполняется как есть — используйте только доверенный код (виджеты карт, встраиваемые видео и т.п.).</span>
            </div>
        <?php endif; ?>

        <?php if ($type === 'cta'): ?>
            <div class="form-field">
                <label for="text">Текст</label>
                <textarea id="text" name="text"><?= htmlspecialchars($data['text'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <div class="form-field">
                <label for="button_text">Текст кнопки</label>
                <input type="text" id="button_text" name="button_text" value="<?= htmlspecialchars($data['button_text'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="form-field">
                <label for="button_url">Ссылка кнопки</label>
                <input type="text" id="button_url" name="button_url" value="<?= htmlspecialchars($data['button_url'] ?? '', ENT_QUOTES) ?>">
            </div>
        <?php endif; ?>

        <?php if ($type === 'advantages'): ?>
            <div>
                <label>Пункты преимуществ</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field">
                                <label>Иконка (эмодзи или короткий текст)</label>
                                <input type="text" name="items[<?= $i ?>][icon]" value="<?= htmlspecialchars($item['icon'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <div class="form-field">
                                <label>Заголовок</label>
                                <input type="text" name="items[<?= $i ?>][title]" value="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <div class="form-field">
                                <label>Текст</label>
                                <textarea name="items[<?= $i ?>][text]"><?= htmlspecialchars($item['text'] ?? '', ENT_QUOTES) ?></textarea>
                            </div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить пункт</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field">
                        <label>Иконка (эмодзи или короткий текст)</label>
                        <input type="text" name="items[__INDEX__][icon]">
                    </div>
                    <div class="form-field">
                        <label>Заголовок</label>
                        <input type="text" name="items[__INDEX__][title]">
                    </div>
                    <div class="form-field">
                        <label>Текст</label>
                        <textarea name="items[__INDEX__][text]"></textarea>
                    </div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить пункт</button>
                </template>
                <div class="repeater-actions">
                    <button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить пункт</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'slider'): ?>
            <div>
                <label>Слайды</label>
                <div data-repeater="slides">
                    <?php foreach (($data['slides'] ?? []) as $i => $slide): ?>
                        <div class="repeater-row">
                            <div class="form-field">
                                <label>Ссылка на изображение</label>
                                <input type="text" name="slides[<?= $i ?>][image]" value="<?= htmlspecialchars($slide['image'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/....jpg">
                            </div>
                            <div class="form-field">
                                <label>Alt-текст</label>
                                <input type="text" name="slides[<?= $i ?>][alt]" value="<?= htmlspecialchars($slide['alt'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <div class="form-field">
                                <label>Подпись</label>
                                <input type="text" name="slides[<?= $i ?>][caption]" value="<?= htmlspecialchars($slide['caption'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить слайд</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="slides">
                    <div class="form-field">
                        <label>Ссылка на изображение</label>
                        <input type="text" name="slides[__INDEX__][image]" placeholder="/uploads/public/....jpg">
                    </div>
                    <div class="form-field">
                        <label>Alt-текст</label>
                        <input type="text" name="slides[__INDEX__][alt]">
                    </div>
                    <div class="form-field">
                        <label>Подпись</label>
                        <input type="text" name="slides[__INDEX__][caption]">
                    </div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить слайд</button>
                </template>
                <div class="repeater-actions">
                    <button type="button" class="btn btn--small" data-repeater-add="slides">+ Добавить слайд</button>
                </div>
                <span class="form-hint">Изображения загружаются заранее в разделе «Файлы» (публичный доступ), ссылка копируется оттуда.</span>
            </div>
        <?php endif; ?>

        <?php if ($type === 'gallery'): ?>
            <div>
                <label>Изображения галереи</label>
                <div data-repeater="images">
                    <?php foreach (($data['images'] ?? []) as $i => $image): ?>
                        <div class="repeater-row">
                            <div class="form-field">
                                <label>Ссылка на изображение</label>
                                <input type="text" name="images[<?= $i ?>][url]" value="<?= htmlspecialchars($image['url'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/....jpg">
                            </div>
                            <div class="form-field">
                                <label>Подпись</label>
                                <input type="text" name="images[<?= $i ?>][caption]" value="<?= htmlspecialchars($image['caption'] ?? '', ENT_QUOTES) ?>">
                            </div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="images">
                    <div class="form-field">
                        <label>Ссылка на изображение</label>
                        <input type="text" name="images[__INDEX__][url]" placeholder="/uploads/public/....jpg">
                    </div>
                    <div class="form-field">
                        <label>Подпись</label>
                        <input type="text" name="images[__INDEX__][caption]">
                    </div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions">
                    <button type="button" class="btn btn--small" data-repeater-add="images">+ Добавить изображение</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'form'): ?>
            <div class="form-field">
                <label for="form_id">Форма обратной связи</label>
                <select id="form_id" name="form_id">
                    <option value="">— выберите форму —</option>
                    <?php foreach ($forms as $form): ?>
                        <option value="<?= (int) $form['id'] ?>" <?= (int) ($data['form_id'] ?? 0) === (int) $form['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($form['name'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($forms)): ?>
                    <span class="form-hint">Сначала создайте форму в разделе «Формы».</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="form-field">
            <label for="custom_css">Собственный CSS блока</label>
            <textarea id="custom_css" name="custom_css" style="min-height:140px; font-family: monospace;"><?= htmlspecialchars($block['custom_css'] ?? '', ENT_QUOTES) ?></textarea>
            <span class="form-hint">
                Стили автоматически изолируются: любой селектор при выводе на сайте получает префикс
                <code>#block-<?= (int) $block['id'] ?></code>, поэтому не может повлиять на остальную страницу.
                Пример: <code>h2 { color: red; }</code> → <code>#block-<?= (int) $block['id'] ?> h2 { color: red; }</code>.
            </span>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить блок</button>
            <a href="/admin/pages/<?= (int) $block['page_id'] ?>/edit" class="btn">Отмена</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
