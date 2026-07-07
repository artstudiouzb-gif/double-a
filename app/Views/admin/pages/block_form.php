<?php

use App\Core\Csrf;

$pageTitle = 'Редактирование блока';
$activeNav = 'pages';
require __DIR__ . '/../layout/header.php';

/** @var array $block */
/** @var array $data */
/** @var array $forms */

$type = $block['type'];
$backUrl = '/admin/pages/' . (int) $block['page_id'] . '/edit?block_lang=' . urlencode((string) ($block['lang'] ?? ''));
?>
<a href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>" class="btn btn--small" style="margin-bottom:16px;">&larr; Назад к странице</a>
<a href="/admin/blocks/<?= (int) $block['id'] ?>/revisions" class="btn btn--small" style="margin-bottom:16px;">История изменений</a>

<div class="form-card">
    <form method="post" action="/admin/blocks/<?= (int) $block['id'] ?>/edit" class="form-grid">
        <?= Csrf::field() ?>

        <div class="form-field">
            <label for="title">Внутреннее название блока</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($block['title'] ?? '', ENT_QUOTES) ?>">
        </div>

        <?php if (in_array($type, ['text', 'cta', 'advantages', 'gallery', 'testimonials', 'counters', 'team_list', 'projects_list', 'news_latest', 'faq'], true)): ?>
            <div class="form-field">
                <label for="title_field">Заголовок, показываемый на сайте</label>
                <input type="text" id="title_field" name="title_field" value="<?= htmlspecialchars($data['title'] ?? '', ENT_QUOTES) ?>">
            </div>
        <?php endif; ?>

        <?php if ($type === 'text'): ?>
            <div class="form-field">
                <label for="content">Текст</label>
                <textarea id="content" name="content" data-wysiwyg style="min-height:200px;"><?= htmlspecialchars($data['content'] ?? '', ENT_QUOTES) ?></textarea>
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
                                <label>SVG-иконка кодом (необязательно; имеет приоритет)</label>
                                <textarea name="items[<?= $i ?>][icon_svg]" placeholder="<svg ...>...</svg>"><?= htmlspecialchars($item['icon_svg'] ?? '', ENT_QUOTES) ?></textarea>
                                <span class="form-hint">Опасное содержимое (&lt;script&gt;, on*-обработчики) вырезается при сохранении.</span>
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
                        <label>SVG-иконка кодом (необязательно; имеет приоритет)</label>
                        <textarea name="items[__INDEX__][icon_svg]" placeholder="<svg ...>...</svg>"></textarea>
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

        <?php if ($type === 'columns'): ?>
            <div class="form-field">
                <label for="columns">Количество колонок</label>
                <select id="columns" name="columns">
                    <?php foreach ([2, 3, 4] as $n): ?>
                        <option value="<?= $n ?>" <?= (int) ($data['columns'] ?? 2) === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="gap">Промежуток между колонками</label>
                <select id="gap" name="gap">
                    <?php foreach (['small' => 'Малый', 'medium' => 'Средний', 'large' => 'Большой'] as $gv => $gl): ?>
                        <option value="<?= $gv ?>" <?= (string) ($data['gap'] ?? 'medium') === $gv ? 'selected' : '' ?>><?= $gl ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">Наполнение колонок настраивается на странице: кнопка «+ блок» в каждой колонке.</span>
            </div>
        <?php endif; ?>

        <?php if ($type === 'testimonials'): ?>
            <div>
                <label>Отзывы (карусель)</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Цитата</label><textarea name="items[<?= $i ?>][quote]"><?= htmlspecialchars($item['quote'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <div class="form-field"><label>Имя</label><input type="text" name="items[<?= $i ?>][name]" value="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Компания</label><input type="text" name="items[<?= $i ?>][company]" value="<?= htmlspecialchars($item['company'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Фото (URL)</label><input type="text" name="items[<?= $i ?>][photo]" value="<?= htmlspecialchars($item['photo'] ?? '', ENT_QUOTES) ?>" data-media-target="items[<?= $i ?>][photo]"><button type="button" class="btn btn--small" data-media-pick data-media-target="[name='items[<?= $i ?>][photo]']">Из медиатеки</button></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить отзыв</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>Цитата</label><textarea name="items[__INDEX__][quote]"></textarea></div>
                    <div class="form-field"><label>Имя</label><input type="text" name="items[__INDEX__][name]"></div>
                    <div class="form-field"><label>Компания</label><input type="text" name="items[__INDEX__][company]"></div>
                    <div class="form-field"><label>Фото (URL)</label><input type="text" name="items[__INDEX__][photo]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить отзыв</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить отзыв</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'counters'): ?>
            <div>
                <label>Счётчики</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Число</label><input type="number" name="items[<?= $i ?>][value]" value="<?= (int) ($item['value'] ?? 0) ?>"></div>
                            <div class="form-field"><label>Суффикс (напр. + или %)</label><input type="text" name="items[<?= $i ?>][suffix]" value="<?= htmlspecialchars($item['suffix'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Подпись</label><input type="text" name="items[<?= $i ?>][label]" value="<?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?>"></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>Число</label><input type="number" name="items[__INDEX__][value]" value="0"></div>
                    <div class="form-field"><label>Суффикс (напр. + или %)</label><input type="text" name="items[__INDEX__][suffix]"></div>
                    <div class="form-field"><label>Подпись</label><input type="text" name="items[__INDEX__][label]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить счётчик</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'team_list' || $type === 'projects_list' || $type === 'news_latest'): ?>
            <div class="form-field">
                <label for="limit">Сколько записей показывать<?= $type === 'news_latest' ? ' (0 — 3 по умолчанию)' : ' (0 — все)' ?></label>
                <input type="number" id="limit" name="limit" min="0" value="<?= (int) ($data['limit'] ?? 0) ?>">
                <span class="form-hint">
                    <?php if ($type === 'news_latest'): ?>
                        Блок выводит последние опубликованные новости (лента для главной страницы).
                    <?php else: ?>
                        Блок выводит опубликованные записи раздела «<?= $type === 'team_list' ? 'Команда' : 'Проекты' ?>» по порядку сортировки.
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($type === 'faq'): ?>
            <div>
                <label>Вопросы и ответы (аккордеон)</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Вопрос</label><input type="text" name="items[<?= $i ?>][question]" value="<?= htmlspecialchars($item['question'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Ответ</label><textarea name="items[<?= $i ?>][answer]" data-wysiwyg><?= htmlspecialchars($item['answer'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить вопрос</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>Вопрос</label><input type="text" name="items[__INDEX__][question]"></div>
                    <div class="form-field"><label>Ответ</label><textarea name="items[__INDEX__][answer]"></textarea></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить вопрос</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить вопрос</button></div>
            </div>
        <?php endif; ?>

        <?php $spacing = $data['_spacing'] ?? 'premium'; ?>
        <div class="form-field">
            <label for="spacing">Вертикальные отступы («воздух»)</label>
            <select id="spacing" name="spacing">
                <option value="none" <?= $spacing === 'none' ? 'selected' : '' ?>>Нет</option>
                <option value="small" <?= $spacing === 'small' ? 'selected' : '' ?>>Малый</option>
                <option value="premium" <?= $spacing === 'premium' ? 'selected' : '' ?>>Премиум</option>
                <option value="max" <?= $spacing === 'max' ? 'selected' : '' ?>>Максимальный</option>
            </select>
            <span class="form-hint">Адаптивные отступы через CSS clamp() — масштабируются под ширину экрана.</span>
        </div>
        <?php
        // Тип анимации появления (группа 4.2). Обратная совместимость: старое
        // булево _reveal=true трактуем как {enabled:true, type:'fade'}.
        $revealRaw = $data['_reveal'] ?? null;
        if (is_array($revealRaw)) {
            $revealEnabled = !empty($revealRaw['enabled']);
            $revealType = (string) ($revealRaw['type'] ?? 'fade');
        } else {
            $revealEnabled = !empty($revealRaw);
            $revealType = 'fade';
        }
        $revealCurrent = $revealEnabled ? $revealType : '';
        $revealOptions = [
            '' => 'Без анимации',
            'fade' => 'Плавное появление',
            'slide-up' => 'Выезд снизу',
            'slide-left' => 'Выезд слева',
            'slide-right' => 'Выезд справа',
            'zoom-in' => 'Увеличение',
        ];
        ?>
        <div class="form-field">
            <label for="reveal_type">Анимация появления при прокрутке</label>
            <select id="reveal_type" name="reveal_type">
                <?php foreach ($revealOptions as $rv => $rl): ?>
                    <option value="<?= htmlspecialchars($rv, ENT_QUOTES) ?>" <?= $revealCurrent === $rv ? 'selected' : '' ?>><?= htmlspecialchars($rl, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (\App\Core\Auth::isSuperAdmin()): ?>
        <div class="form-field">
            <label for="custom_css">Собственный CSS блока</label>
            <textarea id="custom_css" name="custom_css" style="min-height:140px; font-family: monospace;"><?= htmlspecialchars($block['custom_css'] ?? '', ENT_QUOTES) ?></textarea>
            <span class="form-hint">
                Стили автоматически изолируются: любой селектор при выводе на сайте получает префикс
                <code>#block-<?= (int) $block['id'] ?></code>, поэтому не может повлиять на остальную страницу.
                Пример: <code>h2 { color: red; }</code> → <code>#block-<?= (int) $block['id'] ?> h2 { color: red; }</code>.
            </span>
        </div>
        <?php else: ?>
            <?php /* Редактор не может менять кастомный CSS — сохраняем прежнее значение. */ ?>
            <input type="hidden" name="custom_css" value="<?= htmlspecialchars($block['custom_css'] ?? '', ENT_QUOTES) ?>">
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить блок</button>
            <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>" class="btn">Отмена</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
