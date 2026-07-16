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

        <?php if (in_array($type, ['text', 'cta', 'advantages', 'gallery', 'testimonials', 'counters', 'team_list', 'projects_list', 'news_latest', 'partners', 'banner', 'faq', 'subscribe', 'contact_cards', 'hero', 'categories_grid', 'media_materials', 'cards_grid', 'image_cards', 'media_gallery', 'news_feature', 'person_cards', 'timeline', 'cta_band', 'feature_band', 'stages', 'text_image', 'docs_list', 'map_point', 'org_structure'], true)): ?>
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
            <div class="colorfield-row">
                <?= \App\Core\AdminUi::colorField('bg_color', $data['bg_color'] ?? '', 'Цвет фона', '#eef2f7') ?>
                <?= \App\Core\AdminUi::colorField('text_color', $data['text_color'] ?? '', 'Цвет текста', '#173a63') ?>
                <?= \App\Core\AdminUi::colorField('button_color', $data['button_color'] ?? '', 'Цвет кнопки', '#17999b') ?>
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
            <div class="form-field">
                <label for="form_layout">Макет формы</label>
                <select id="form_layout" name="layout">
                    <option value="1col" <?= ($data['layout'] ?? '1col') === '1col' ? 'selected' : '' ?>>В одну колонку</option>
                    <option value="2col" <?= ($data['layout'] ?? '1col') === '2col' ? 'selected' : '' ?>>В две колонки (сетка)</option>
                </select>
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
            <div class="colorfield-row">
                <?= \App\Core\AdminUi::colorField('card_bg', $data['card_bg'] ?? '', 'Цвет карточки (фон)', '#ffffff', 'По умолчанию (белая)') ?>
                <?= \App\Core\AdminUi::colorField('text_color', $data['text_color'] ?? '', 'Цвет текста и цифр', '#173a63', 'По умолчанию (тёмно-синий)') ?>
            </div>
            <span class="form-hint" style="display:block;margin:-6px 0 14px;">Оставьте «по умолчанию», чтобы карточка была белой с тёмным текстом. Для тёмной карточки выберите тёмный фон и светлый текст.</span>
            <div>
                <label>Счётчики</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>SVG-иконка (необязательно)</label><textarea name="items[<?= $i ?>][icon_svg]"><?= htmlspecialchars($item['icon_svg'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <div class="form-field"><label>Число</label><input type="number" name="items[<?= $i ?>][value]" value="<?= (int) ($item['value'] ?? 0) ?>"></div>
                            <div class="form-field"><label>Суффикс (напр. + или %)</label><input type="text" name="items[<?= $i ?>][suffix]" value="<?= htmlspecialchars($item['suffix'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Подпись</label><input type="text" name="items[<?= $i ?>][label]" value="<?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?>"></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>SVG-иконка (необязательно)</label><textarea name="items[__INDEX__][icon_svg]"></textarea></div>
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

        <?php if ($type === 'banner'): ?>
            <div class="form-field">
                <label for="text">Текст баннера</label>
                <textarea id="text" name="text" rows="2"><?= htmlspecialchars($data['text'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <?= \App\Core\AdminUi::imageField('image', $data['image'] ?? '', ['label' => 'Фоновое изображение', 'hint' => 'Тёмная подложка накладывается автоматически для читаемости текста.']) ?>
            <div class="form-field">
                <label for="banner_style">Стиль баннера</label>
                <select id="banner_style" name="style">
                    <option value="dark" <?= ($data['style'] ?? 'dark') === 'dark' ? 'selected' : '' ?>>Тёмный (фото с подложкой)</option>
                    <option value="light" <?= ($data['style'] ?? 'dark') === 'light' ? 'selected' : '' ?>>Светлый сплит (текст слева, фото справа)</option>
                </select>
            </div>
            <div class="form-field">
                <label for="button_text">Текст кнопки</label>
                <input type="text" id="button_text" name="button_text" value="<?= htmlspecialchars($data['button_text'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="form-field">
                <label for="button_url">Ссылка кнопки</label>
                <input type="text" id="button_url" name="button_url" value="<?= htmlspecialchars($data['button_url'] ?? '', ENT_QUOTES) ?>" placeholder="/catalog/documenty">
            </div>
            <div class="colorfield-row">
                <?= \App\Core\AdminUi::colorField('bg_color', $data['bg_color'] ?? '', 'Цвет фона (без фото)', '#173a63') ?>
                <?= \App\Core\AdminUi::colorField('text_color', $data['text_color'] ?? '', 'Цвет текста', '#ffffff') ?>
                <?= \App\Core\AdminUi::colorField('button_color', $data['button_color'] ?? '', 'Цвет кнопки', '#17999b') ?>
            </div>
        <?php endif; ?>

        <?php if ($type === 'partners'): ?>
            <div>
                <label>Логотипы партнёров</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Ссылка на логотип</label><input type="text" name="items[<?= $i ?>][logo]" value="<?= htmlspecialchars($item['logo'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/logo.png"></div>
                            <div class="form-field"><label>Название</label><input type="text" name="items[<?= $i ?>][name]" value="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Ссылка (необязательно)</label><input type="text" name="items[<?= $i ?>][url]" value="<?= htmlspecialchars($item['url'] ?? '', ENT_QUOTES) ?>" placeholder="https://..."></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>Ссылка на логотип</label><input type="text" name="items[__INDEX__][logo]" placeholder="/uploads/public/logo.png"></div>
                    <div class="form-field"><label>Название</label><input type="text" name="items[__INDEX__][name]"></div>
                    <div class="form-field"><label>Ссылка (необязательно)</label><input type="text" name="items[__INDEX__][url]" placeholder="https://..."></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить логотип</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'subscribe'): ?>
            <div class="form-field">
                <label for="sub_text">Текст под заголовком</label>
                <input type="text" id="sub_text" name="text" value="<?= htmlspecialchars($data['text'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="form-field">
                <label for="sub_btn">Текст кнопки</label>
                <input type="text" id="sub_btn" name="button_text" value="<?= htmlspecialchars($data['button_text'] ?? '', ENT_QUOTES) ?>" placeholder="Подписаться">
            </div>
            <p class="form-hint">Адреса попадают в раздел «Подписчики»; рассылку раз в неделю отправляет digest_worker (cron).</p>
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

        <?php if ($type === 'contact_cards'): ?>
            <div>
                <label>Контактные карточки (адрес, телефон, e-mail, часы работы…)</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>SVG-иконка (необязательно)</label><textarea name="items[<?= $i ?>][icon_svg]" placeholder="<svg ...>...</svg>"><?= htmlspecialchars($item['icon_svg'] ?? '', ENT_QUOTES) ?></textarea><span class="form-hint">Опасное содержимое вырезается при сохранении.</span></div>
                            <div class="form-field"><label>Заголовок</label><input type="text" name="items[<?= $i ?>][title]" value="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES) ?>" placeholder="напр. Телефон"></div>
                            <div class="form-field"><label>Строки (по одной на строку)</label><textarea name="items[<?= $i ?>][lines]" placeholder="+998 71 000-00-00&#10;info@example.uz"><?= htmlspecialchars($item['lines'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <div class="form-field"><label>Ссылка (URL)</label><input type="text" name="items[<?= $i ?>][link_url]" value="<?= htmlspecialchars($item['link_url'] ?? '', ENT_QUOTES) ?>" placeholder="tel:+998710000000 / mailto: / https://"></div>
                            <div class="form-field"><label>Текст ссылки</label><input type="text" name="items[<?= $i ?>][link_text]" value="<?= htmlspecialchars($item['link_text'] ?? '', ENT_QUOTES) ?>" placeholder="напр. Позвонить"></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить карточку</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>SVG-иконка (необязательно)</label><textarea name="items[__INDEX__][icon_svg]" placeholder="<svg ...>...</svg>"></textarea></div>
                    <div class="form-field"><label>Заголовок</label><input type="text" name="items[__INDEX__][title]" placeholder="напр. Телефон"></div>
                    <div class="form-field"><label>Строки (по одной на строку)</label><textarea name="items[__INDEX__][lines]"></textarea></div>
                    <div class="form-field"><label>Ссылка (URL)</label><input type="text" name="items[__INDEX__][link_url]"></div>
                    <div class="form-field"><label>Текст ссылки</label><input type="text" name="items[__INDEX__][link_text]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить карточку</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить карточку</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'hero'): ?>
            <div class="form-field"><label for="hero_width">Ширина секции</label>
                <select id="hero_width" name="hero_width">
                    <option value="full" <?= ($data['width'] ?? 'full') === 'full' ? 'selected' : '' ?>>Во всю ширину экрана</option>
                    <option value="standard" <?= ($data['width'] ?? '') === 'standard' ? 'selected' : '' ?>>Стандартная (по контейнеру)</option>
                </select>
            </div>
            <?php
            $heroHeightMode = (string) ($data['height'] ?? 'regular');
            $heroHeightMode = in_array($heroHeightMode, ['regular', 'full', 'custom'], true) ? $heroHeightMode : 'regular';
            $heroCustomHeight = (string) ($data['custom_height'] ?? '720px');
            preg_match('/^(\d+(?:\.\d+)?)(px|vh|dvh|rem)$/', $heroCustomHeight, $heroHeightParts);
            $heroHeightValue = $heroHeightParts[1] ?? '720';
            $heroHeightUnit = $heroHeightParts[2] ?? 'px';
            ?>
            <div class="form-field"><label for="hero_height">Высота секции</label>
                <select id="hero_height" name="hero_height" data-hero-height>
                    <option value="regular" <?= ($data['height'] ?? 'regular') === 'regular' ? 'selected' : '' ?>>Обычная</option>
                    <option value="full" <?= ($data['height'] ?? '') === 'full' ? 'selected' : '' ?>>Полноэкранная (100vh)</option>
                    <option value="custom" <?= $heroHeightMode === 'custom' ? 'selected' : '' ?>>Своя высота</option>
                </select>
            </div>
            <div class="form-field" data-hero-custom-height<?= $heroHeightMode !== 'custom' ? ' hidden' : '' ?>>
                <label for="hero_height_value">Своя высота секции</label>
                <div style="display:grid;grid-template-columns:minmax(0,1fr) 110px;gap:10px;">
                    <input type="number" id="hero_height_value" name="hero_height_value" min="10" max="2000" step="0.1" value="<?= htmlspecialchars($heroHeightValue, ENT_QUOTES) ?>">
                    <select id="hero_height_unit" name="hero_height_unit" aria-label="Единица высоты">
                        <?php foreach (['px' => 'px', 'vh' => 'vh', 'dvh' => 'dvh', 'rem' => 'rem'] as $unit => $label): ?>
                            <option value="<?= $unit ?>" <?= $heroHeightUnit === $unit ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <span class="form-hint">Допустимо: 160–2000 px, 20–150 vh/dvh или 10–120 rem. Используется минимальная высота, поэтому содержимое не обрезается.</span>
            </div>
            <div class="form-field">
                <label for="eyebrow">Надзаголовок (мелкий текст над заголовком)</label>
                <input type="text" id="eyebrow" name="eyebrow" value="<?= htmlspecialchars($data['eyebrow'] ?? '', ENT_QUOTES) ?>" placeholder="СТРАТЕГИЯ. РЕФОРМЫ. РАЗВИТИЕ.">
            </div>
            <div class="form-field">
                <label for="subtitle">Подзаголовок</label>
                <textarea id="subtitle" name="subtitle" rows="2"><?= htmlspecialchars($data['subtitle'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <div class="form-field"><label for="bg_type">Фон секции</label>
                <select id="bg_type" name="bg_type" data-hero-bg>
                    <?php
                    // Старые блоки без bg_type: определяем тип по заполненным полям.
                    $bt = (string) ($data['bg_type'] ?? '');
                    if ($bt === '') {
                        $bt = \App\Core\Video::youtubeId($data['youtube_url'] ?? '') ? 'youtube'
                            : (trim((string) ($data['video_url'] ?? '')) !== '' ? 'video'
                            : (trim((string) ($data['image'] ?? '')) !== '' ? 'image' : 'none'));
                    }
                    ?>
                    <option value="none" <?= $bt === 'none' ? 'selected' : '' ?>>Без фона (светлая секция)</option>
                    <option value="image" <?= $bt === 'image' ? 'selected' : '' ?>>Фото</option>
                    <option value="video" <?= $bt === 'video' ? 'selected' : '' ?>>Видео из медиа (mp4)</option>
                    <option value="youtube" <?= $bt === 'youtube' ? 'selected' : '' ?>>Видео с YouTube</option>
                </select>
                <span class="form-hint">Выберите источник фона. Поля ниже подстраиваются под выбор.</span>
            </div>
            <?= \App\Core\AdminUi::imageField('image', $data['image'] ?? '', ['label' => 'Фото фона (и постер для видео)', 'hint' => 'Показывается как фон, а для видео — как заставка до загрузки.']) ?>
            <div class="form-field">
                <label for="video_url">Видео-фон из медиа (mp4)</label>
                <div class="image-field__controls">
                    <input type="text" id="video_url" name="video_url" value="<?= htmlspecialchars($data['video_url'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/hero.mp4">
                    <button type="button" class="btn btn--small" data-media-pick data-media-target="#video_url" data-media-type="video">Медиабиблиотека</button>
                </div>
                <span class="form-hint">Выберите mp4 из медиабиблиотеки или вставьте ссылку. Видео зациклено, без звука.</span>
            </div>
            <div class="form-field">
                <label for="youtube_url">Ссылка на YouTube</label>
                <input type="text" id="youtube_url" name="youtube_url" value="<?= htmlspecialchars($data['youtube_url'] ?? '', ENT_QUOTES) ?>" placeholder="https://www.youtube.com/watch?v=…">
                <span class="form-hint">Ролик проигрывается фоном без звука, зациклено. Загрузка идёт с серверов YouTube.</span>
            </div>
            <div class="form-field"><label for="overlay_color">Затемнение фона (overlay) — цвет</label>
                <input type="color" id="overlay_color" name="overlay_color" value="<?= htmlspecialchars($data['overlay_color'] ?? '#0b1a30', ENT_QUOTES) ?>">
            </div>
            <div class="form-field"><label for="overlay_opacity">Прозрачность overlay: <output data-range-output="overlay_opacity"><?= (int) ($data['overlay_opacity'] ?? 55) ?></output>%</label>
                <input type="range" min="0" max="100" id="overlay_opacity" name="overlay_opacity" value="<?= (int) ($data['overlay_opacity'] ?? 55) ?>" data-range-input="overlay_opacity">
                <span class="form-hint">0% — фон виден полностью, 100% — сплошная заливка. Помогает читаемости текста.</span>
            </div>
            <div class="colorfield-row">
                <?= \App\Core\AdminUi::colorField('bg_color', $data['bg_color'] ?? '', 'Цвет фона под текстом (градиент)', '#0b1a30', 'Нет (по теме)') ?>
            </div>
            <span class="form-hint" style="display:block;margin:-6px 0 14px;">Полупрозрачный градиент выбранного цвета под текстом — не зависит от светлой/тёмной темы. Полезно для героя без фото: иначе фон берётся из темы (светло-серый / тёмно-синий).</span>
            <div class="form-field"><label for="text_position">Положение текста</label>
                <select id="text_position" name="text_position">
                    <?php $tp = $data['text_position'] ?? 'left'; ?>
                    <option value="left" <?= $tp === 'left' ? 'selected' : '' ?>>Слева</option>
                    <option value="center" <?= $tp === 'center' ? 'selected' : '' ?>>По центру</option>
                    <option value="right" <?= $tp === 'right' ? 'selected' : '' ?>>Справа</option>
                </select>
            </div>
            <?php
            preg_match('/^(\d+(?:\.\d+)?)(px|%|vw)$/', (string) ($data['text_width'] ?? ''), $twParts);
            $twValue = $twParts[1] ?? '';
            $twUnit = $twParts[2] ?? 'px';
            ?>
            <div class="form-field">
                <label for="text_width_value">Ширина текстовой колонки</label>
                <div style="display:grid;grid-template-columns:minmax(0,1fr) 110px;gap:10px;">
                    <input type="number" id="text_width_value" name="text_width_value" min="10" max="2000" step="0.1" value="<?= htmlspecialchars($twValue, ENT_QUOTES) ?>" placeholder="по теме (620)">
                    <select id="text_width_unit" name="text_width_unit" aria-label="Единица ширины">
                        <?php foreach (['px' => 'px', '%' => '%', 'vw' => 'vw'] as $unit => $label): ?>
                            <option value="<?= $unit ?>" <?= $twUnit === $unit ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <span class="form-hint">Максимальная ширина блока с заголовком и текстом: 200–2000 px или 10–100 %/vw (например, 50 vw — половина экрана). Пусто — ширина темы. На телефонах ограничение не применяется.</span>
            </div>
            <div class="colorfield-row">
                <?= \App\Core\AdminUi::colorField('text_color', $data['text_color'] ?? '', 'Цвет текста', '#ffffff', 'Авто (белый на фото, тёмный без фона)') ?>
                <?= \App\Core\AdminUi::colorField('button_color', $data['button_color'] ?? '', 'Цвет кнопок', '#173a63', 'По умолчанию') ?>
            </div>
            <div class="form-field">
                <label class="hb-switch"><input type="checkbox" name="panel_enabled" value="1" <?= !empty($data['panel_enabled']) ? 'checked' : '' ?>><span class="hb-switch__track"></span> Подложка под текстом</label>
                <span class="form-hint">Цветная полупрозрачная плашка под заголовком — для читаемости на пёстром фоне. Если делаете светлую подложку — задайте тёмный цвет текста выше.</span>
            </div>
            <div class="form-field"><label for="panel_color">Цвет подложки</label>
                <input type="color" id="panel_color" name="panel_color" value="<?= htmlspecialchars($data['panel_color'] ?? '#0b1a30', ENT_QUOTES) ?>">
            </div>
            <div class="form-field"><label for="panel_opacity">Прозрачность подложки: <output data-range-output="panel_opacity"><?= (int) ($data['panel_opacity'] ?? 40) ?></output>%</label>
                <input type="range" min="0" max="100" id="panel_opacity" name="panel_opacity" value="<?= (int) ($data['panel_opacity'] ?? 40) ?>" data-range-input="panel_opacity">
            </div>
            <div class="form-field"><label for="button_text">Кнопка 1 — текст</label><input type="text" id="button_text" name="button_text" value="<?= htmlspecialchars($data['button_text'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="button_url">Кнопка 1 — ссылка</label><input type="text" id="button_url" name="button_url" value="<?= htmlspecialchars($data['button_url'] ?? '', ENT_QUOTES) ?>" placeholder="/o-nas"></div>
            <div class="form-field"><label for="button2_text">Кнопка 2 — текст (контурная)</label><input type="text" id="button2_text" name="button2_text" value="<?= htmlspecialchars($data['button2_text'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="button2_url">Кнопка 2 — ссылка</label><input type="text" id="button2_url" name="button2_url" value="<?= htmlspecialchars($data['button2_url'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="video_button_text">Кнопка «Смотреть видео» — текст</label><input type="text" id="video_button_text" name="video_button_text" value="<?= htmlspecialchars($data['video_button_text'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="video_button_url">Кнопка «Смотреть видео» — ссылка</label><input type="text" id="video_button_url" name="video_button_url" value="<?= htmlspecialchars($data['video_button_url'] ?? '', ENT_QUOTES) ?>"></div>
        <?php endif; ?>

        <?php if ($type === 'news_feature'): ?>
            <div class="form-field"><label for="nf_limit">Сколько новостей показывать</label><input type="number" id="nf_limit" name="limit" min="2" max="12" value="<?= (int) ($data['limit'] ?? 6) ?>"><span class="form-hint">1 крупная + список. Берутся опубликованные новости.</span></div>
            <div class="form-field"><label for="nf_all_text">Ссылка «Все …» — текст</label><input type="text" id="nf_all_text" name="all_text" value="<?= htmlspecialchars($data['all_text'] ?? '', ENT_QUOTES) ?>" placeholder="Все новости"></div>
            <div class="form-field"><label for="nf_all_url">Ссылка «Все …» — URL (пусто = /news)</label><input type="text" id="nf_all_url" name="all_url" value="<?= htmlspecialchars($data['all_url'] ?? '', ENT_QUOTES) ?>"></div>
        <?php endif; ?>

        <?php if (in_array($type, ['cards_grid', 'image_cards', 'media_gallery'], true)): ?>
            <div class="form-field"><label for="all_text">Ссылка «Все …» — текст</label><input type="text" id="all_text" name="all_text" value="<?= htmlspecialchars($data['all_text'] ?? '', ENT_QUOTES) ?>" placeholder="Все направления"></div>
            <div class="form-field"><label for="all_url">Ссылка «Все …» — URL</label><input type="text" id="all_url" name="all_url" value="<?= htmlspecialchars($data['all_url'] ?? '', ENT_QUOTES) ?>"></div>
            <?php if ($type === 'image_cards' || $type === 'media_gallery'): ?>
                <?php
                $srcVal = $data['source'] ?? 'manual';
                $srcOptions = $type === 'image_cards'
                    ? ['projects' => 'Из раздела «Проекты» (отмеченные «на главной»)']
                    : ['media' => 'Видео + фотоальбомы, с вкладками (отмеченные «на главной»)', 'albums' => 'Из фотоальбомов (отмеченные «на главной»)', 'videos' => 'Из раздела «Видео» (отмеченные «на главной»)'];
                ?>
                <div class="form-field">
                    <label for="source">Источник данных</label>
                    <select id="source" name="source">
                        <option value="manual" <?= !isset($srcOptions[$srcVal]) ? 'selected' : '' ?>>Ручной список (ниже)</option>
                        <?php foreach ($srcOptions as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $srcVal === $val ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">При выборе автоматического источника карточки собираются из отмеченных записей, а список ниже игнорируется. Отмечайте записи галочкой «Показать на главной» в соответствующем разделе.</span>
                </div>
                <div class="form-field"><label for="limit">Сколько карточек показывать</label><input type="number" id="limit" name="limit" min="2" max="24" value="<?= (int) ($data['limit'] ?? ($type === 'image_cards' ? 6 : 8)) ?>"></div>
            <?php endif; ?>
            <?php if ($type === 'cards_grid'): ?>
                <div class="form-field"><label for="columns">Колонок</label>
                    <select id="columns" name="columns">
                        <?php foreach ([2,3,4,5] as $n): ?><option value="<?= $n ?>" <?= (int)($data['columns'] ?? 5)===$n?'selected':'' ?>><?= $n ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="colorfield-row">
                    <?= \App\Core\AdminUi::colorField('card_bg', $data['card_bg'] ?? '', 'Цвет карточек (фон)', '#ffffff') ?>
                    <?= \App\Core\AdminUi::colorField('text_color', $data['text_color'] ?? '', 'Цвет текста и иконок', '#173a63') ?>
                </div>
            <?php endif; ?>
            <div>
                <label>Элементы</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <?php if ($type === 'cards_grid'): ?>
                                <div class="form-field"><label>SVG-иконка</label><textarea name="items[<?= $i ?>][icon_svg]"><?= htmlspecialchars($item['icon_svg'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <?php else: ?>
                                <div class="form-field"><label>Изображение (URL)</label><input type="text" name="items[<?= $i ?>][image]" value="<?= htmlspecialchars($item['image'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/..."></div>
                            <?php endif; ?>
                            <div class="form-field"><label>Заголовок</label><input type="text" name="items[<?= $i ?>][title]" value="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES) ?>"></div>
                            <?php if ($type === 'cards_grid'): ?>
                                <div class="form-field"><label>Текст</label><textarea name="items[<?= $i ?>][text]"><?= htmlspecialchars($item['text'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <?php elseif ($type === 'media_gallery'): ?>
                                <div class="form-field"><label>Тип</label><select name="items[<?= $i ?>][kind]"><option value="video" <?= ($item['kind'] ?? 'video')==='video'?'selected':'' ?>>Видео</option><option value="photo" <?= ($item['kind'] ?? '')==='photo'?'selected':'' ?>>Фото</option></select></div>
                                <div class="form-field"><label>Длительность (напр. 02:35)</label><input type="text" name="items[<?= $i ?>][meta]" value="<?= htmlspecialchars($item['meta'] ?? '', ENT_QUOTES) ?>"></div>
                                <div class="form-field"><label>Дата</label><input type="text" name="items[<?= $i ?>][text]" value="<?= htmlspecialchars($item['text'] ?? '', ENT_QUOTES) ?>"></div>
                            <?php endif; ?>
                            <div class="form-field"><label>Ссылка</label><input type="text" name="items[<?= $i ?>][url]" value="<?= htmlspecialchars($item['url'] ?? '', ENT_QUOTES) ?>"></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <?php if ($type === 'cards_grid'): ?>
                        <div class="form-field"><label>SVG-иконка</label><textarea name="items[__INDEX__][icon_svg]"></textarea></div>
                    <?php else: ?>
                        <div class="form-field"><label>Изображение (URL)</label><input type="text" name="items[__INDEX__][image]"></div>
                    <?php endif; ?>
                    <div class="form-field"><label>Заголовок</label><input type="text" name="items[__INDEX__][title]"></div>
                    <?php if ($type === 'cards_grid'): ?>
                        <div class="form-field"><label>Текст</label><textarea name="items[__INDEX__][text]"></textarea></div>
                    <?php elseif ($type === 'media_gallery'): ?>
                        <div class="form-field"><label>Тип</label><select name="items[__INDEX__][kind]"><option value="video">Видео</option><option value="photo">Фото</option></select></div>
                        <div class="form-field"><label>Длительность</label><input type="text" name="items[__INDEX__][meta]"></div>
                        <div class="form-field"><label>Дата</label><input type="text" name="items[__INDEX__][text]"></div>
                    <?php endif; ?>
                    <div class="form-field"><label>Ссылка</label><input type="text" name="items[__INDEX__][url]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'categories_grid' || $type === 'media_materials'): ?>
            <div>
                <label><?= $type === 'categories_grid' ? 'Категории' : 'Медиаматериалы' ?></label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>SVG-иконка</label><textarea name="items[<?= $i ?>][icon_svg]" placeholder="<svg ...>...</svg>"><?= htmlspecialchars($item['icon_svg'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <div class="form-field"><label>Название</label><input type="text" name="items[<?= $i ?>][label]" value="<?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?>"></div>
                            <?php if ($type === 'media_materials'): ?>
                                <div class="form-field"><label>Действие (напр. «Смотреть»)</label><input type="text" name="items[<?= $i ?>][action]" value="<?= htmlspecialchars($item['action'] ?? '', ENT_QUOTES) ?>"></div>
                            <?php endif; ?>
                            <div class="form-field"><label>Ссылка</label><input type="text" name="items[<?= $i ?>][url]" value="<?= htmlspecialchars($item['url'] ?? '', ENT_QUOTES) ?>" placeholder="/catalog/..."></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>SVG-иконка</label><textarea name="items[__INDEX__][icon_svg]" placeholder="<svg ...>...</svg>"></textarea></div>
                    <div class="form-field"><label>Название</label><input type="text" name="items[__INDEX__][label]"></div>
                    <?php if ($type === 'media_materials'): ?>
                        <div class="form-field"><label>Действие</label><input type="text" name="items[__INDEX__][action]"></div>
                    <?php endif; ?>
                    <div class="form-field"><label>Ссылка</label><input type="text" name="items[__INDEX__][url]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'person_cards'): ?>
            <div class="form-field"><label for="all_text">Ссылка «Все …» — текст</label><input type="text" id="all_text" name="all_text" value="<?= htmlspecialchars($data['all_text'] ?? '', ENT_QUOTES) ?>" placeholder="Все руководство"></div>
            <div class="form-field"><label for="all_url">Ссылка «Все …» — URL</label><input type="text" id="all_url" name="all_url" value="<?= htmlspecialchars($data['all_url'] ?? '', ENT_QUOTES) ?>"></div>
            <div>
                <label>Персоны (без фото и имени — карточка «Вакантно»)</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Фото (URL)</label><input type="text" name="items[<?= $i ?>][photo]" value="<?= htmlspecialchars($item['photo'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/..."></div>
                            <div class="form-field"><label>Имя</label><input type="text" name="items[<?= $i ?>][name]" value="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Должность</label><input type="text" name="items[<?= $i ?>][role]" value="<?= htmlspecialchars($item['role'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Ссылка «Подробнее»</label><input type="text" name="items[<?= $i ?>][url]" value="<?= htmlspecialchars($item['url'] ?? '', ENT_QUOTES) ?>"></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>Фото (URL)</label><input type="text" name="items[__INDEX__][photo]" placeholder="/uploads/public/..."></div>
                    <div class="form-field"><label>Имя</label><input type="text" name="items[__INDEX__][name]"></div>
                    <div class="form-field"><label>Должность</label><input type="text" name="items[__INDEX__][role]"></div>
                    <div class="form-field"><label>Ссылка «Подробнее»</label><input type="text" name="items[__INDEX__][url]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить персону</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'timeline'): ?>
            <div>
                <label>События (год + описание)</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Год</label><input type="text" name="items[<?= $i ?>][year]" value="<?= htmlspecialchars($item['year'] ?? '', ENT_QUOTES) ?>" placeholder="2023+"></div>
                            <div class="form-field"><label>Текст</label><textarea name="items[<?= $i ?>][text]"><?= htmlspecialchars($item['text'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>Год</label><input type="text" name="items[__INDEX__][year]"></div>
                    <div class="form-field"><label>Текст</label><textarea name="items[__INDEX__][text]"></textarea></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить событие</button></div>
            </div>
            <div class="form-field"><label for="button_text">Кнопка под таймлайном — текст</label><input type="text" id="button_text" name="button_text" value="<?= htmlspecialchars($data['button_text'] ?? '', ENT_QUOTES) ?>" placeholder="Вся история"></div>
            <div class="form-field"><label for="button_url">Кнопка под таймлайном — ссылка</label><input type="text" id="button_url" name="button_url" value="<?= htmlspecialchars($data['button_url'] ?? '', ENT_QUOTES) ?>"></div>
            <hr>
            <div class="form-field"><label for="cta_title">CTA-карточка справа — заголовок (пусто = без карточки)</label><input type="text" id="cta_title" name="cta_title" value="<?= htmlspecialchars($data['cta_title'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="cta_text">CTA — текст</label><textarea id="cta_text" name="cta_text" rows="2"><?= htmlspecialchars($data['cta_text'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="form-field"><label for="cta_button_text">CTA — текст кнопки</label><input type="text" id="cta_button_text" name="cta_button_text" value="<?= htmlspecialchars($data['cta_button_text'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="cta_button_url">CTA — ссылка кнопки</label><input type="text" id="cta_button_url" name="cta_button_url" value="<?= htmlspecialchars($data['cta_button_url'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="cta_image">CTA — фоновое фото (URL)</label><input type="text" id="cta_image" name="cta_image" value="<?= htmlspecialchars($data['cta_image'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/..."></div>
        <?php endif; ?>

        <?php if ($type === 'news_docs'): ?>
            <div class="form-field"><label for="news_title">Колонка новостей — заголовок</label><input type="text" id="news_title" name="news_title" value="<?= htmlspecialchars($data['news_title'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="limit">Сколько новостей (1–6)</label><input type="number" id="limit" name="limit" min="1" max="6" value="<?= (int) ($data['limit'] ?? 3) ?>"><span class="form-hint">Берутся последние опубликованные новости.</span></div>
            <div class="form-field"><label for="news_all_text">Новости: «Все …» — текст</label><input type="text" id="news_all_text" name="news_all_text" value="<?= htmlspecialchars($data['news_all_text'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="news_all_url">Новости: «Все …» — URL (пусто = /news)</label><input type="text" id="news_all_url" name="news_all_url" value="<?= htmlspecialchars($data['news_all_url'] ?? '', ENT_QUOTES) ?>"></div>
            <hr>
            <div class="form-field"><label for="docs_title">Колонка документов — заголовок</label><input type="text" id="docs_title" name="docs_title" value="<?= htmlspecialchars($data['docs_title'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="docs_all_text">Документы: «Все …» — текст</label><input type="text" id="docs_all_text" name="docs_all_text" value="<?= htmlspecialchars($data['docs_all_text'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="docs_all_url">Документы: «Все …» — URL</label><input type="text" id="docs_all_url" name="docs_all_url" value="<?= htmlspecialchars($data['docs_all_url'] ?? '', ENT_QUOTES) ?>"></div>
            <div>
                <label>Документы</label>
                <div data-repeater="docs">
                    <?php foreach (($data['docs'] ?? []) as $i => $doc): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Название</label><input type="text" name="docs[<?= $i ?>][title]" value="<?= htmlspecialchars($doc['title'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Мета (напр. PDF · 2.4 МБ)</label><input type="text" name="docs[<?= $i ?>][meta]" value="<?= htmlspecialchars($doc['meta'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field">
                                <label>Ссылка на файл</label>
                                <div style="display:flex;gap:8px;">
                                    <input type="text" name="docs[<?= $i ?>][url]" value="<?= htmlspecialchars($doc['url'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/....pdf" style="flex:1;">
                                    <button type="button" class="btn btn--secondary btn--small" data-media-pick data-media-target="[name='docs[<?= $i ?>][url]']" data-media-type="all_files">Выбрать</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="docs">
                    <div class="form-field"><label>Название</label><input type="text" name="docs[__INDEX__][title]"></div>
                    <div class="form-field"><label>Мета (напр. PDF · 2.4 МБ)</label><input type="text" name="docs[__INDEX__][meta]"></div>
                    <div class="form-field">
                        <label>Ссылка на файл</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" name="docs[__INDEX__][url]" style="flex:1;">
                            <button type="button" class="btn btn--secondary btn--small" data-media-pick data-media-target="[name='docs[__INDEX__][url]']" data-media-type="all_files">Выбрать</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="docs">+ Добавить документ</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'cta_band'): ?>
            <div class="form-field"><label for="text">Текст</label><textarea id="text" name="text" rows="2"><?= htmlspecialchars($data['text'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="form-field"><label for="icon_svg">SVG-иконка (необязательно)</label><textarea id="icon_svg" name="icon_svg" placeholder="<svg ...>...</svg>"><?= htmlspecialchars($data['icon_svg'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="form-field"><label for="button_text">Кнопка — текст</label><input type="text" id="button_text" name="button_text" value="<?= htmlspecialchars($data['button_text'] ?? '', ENT_QUOTES) ?>" placeholder="Связаться с нами"></div>
            <div class="form-field"><label for="button_url">Кнопка — ссылка</label><input type="text" id="button_url" name="button_url" value="<?= htmlspecialchars($data['button_url'] ?? '', ENT_QUOTES) ?>" placeholder="/kontakty"></div>
            <div class="colorfield-row">
                <?= \App\Core\AdminUi::colorField('bg_color', $data['bg_color'] ?? '', 'Цвет фона полосы', '#173a63') ?>
                <?= \App\Core\AdminUi::colorField('text_color', $data['text_color'] ?? '', 'Цвет текста', '#ffffff') ?>
                <?= \App\Core\AdminUi::colorField('button_color', $data['button_color'] ?? '', 'Цвет кнопки', '#ffffff') ?>
            </div>
        <?php endif; ?>

        <?php if ($type === 'person_profile'): ?>
            <div class="form-field"><label for="photo">Фото (URL)</label><input type="text" id="photo" name="photo" value="<?= htmlspecialchars($data['photo'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/..."></div>
            <div class="form-field"><label for="name">Имя</label><input type="text" id="name" name="name" value="<?= htmlspecialchars($data['name'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="position">Должность</label><input type="text" id="position" name="position" value="<?= htmlspecialchars($data['position'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="text">Описание</label><textarea id="text" name="text" rows="4"><?= htmlspecialchars($data['text'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="form-field"><label for="phone_label">Подпись телефона</label><input type="text" id="phone_label" name="phone_label" value="<?= htmlspecialchars($data['phone_label'] ?? 'Приёмная:', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="phone">Телефон</label><input type="text" id="phone" name="phone" value="<?= htmlspecialchars($data['phone'] ?? '', ENT_QUOTES) ?>" placeholder="+998 71 203 10 00"></div>
            <div class="form-field"><label for="email_label">Подпись e-mail</label><input type="text" id="email_label" name="email_label" value="<?= htmlspecialchars($data['email_label'] ?? 'E-mail:', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="email">E-mail</label><input type="text" id="email" name="email" value="<?= htmlspecialchars($data['email'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="button_text">Кнопка — текст</label><input type="text" id="button_text" name="button_text" value="<?= htmlspecialchars($data['button_text'] ?? '', ENT_QUOTES) ?>" placeholder="Обратиться к руководителю"></div>
            <div class="form-field"><label for="button_url">Кнопка — ссылка</label><input type="text" id="button_url" name="button_url" value="<?= htmlspecialchars($data['button_url'] ?? '', ENT_QUOTES) ?>"></div>
        <?php endif; ?>

        <?php if ($type === 'feature_band'): ?>
            <div>
                <label>Элементы полосы (иконка + название + текст)</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>SVG-иконка</label><textarea name="items[<?= $i ?>][icon_svg]" placeholder="<svg ...>...</svg>"><?= htmlspecialchars($item['icon_svg'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <div class="form-field"><label>Название</label><input type="text" name="items[<?= $i ?>][title]" value="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Текст</label><textarea name="items[<?= $i ?>][text]"><?= htmlspecialchars($item['text'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>SVG-иконка</label><textarea name="items[__INDEX__][icon_svg]" placeholder="<svg ...>...</svg>"></textarea></div>
                    <div class="form-field"><label>Название</label><input type="text" name="items[__INDEX__][title]"></div>
                    <div class="form-field"><label>Текст</label><textarea name="items[__INDEX__][text]"></textarea></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить элемент</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'bio_education'): ?>
            <div class="form-field"><label for="bio_title">Левая колонка — заголовок</label><input type="text" id="bio_title" name="bio_title" value="<?= htmlspecialchars($data['bio_title'] ?? 'Биография', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="bio_text">Вступительный текст</label><textarea id="bio_text" name="bio_text" rows="4"><?= htmlspecialchars($data['bio_text'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div>
                <label>Карьера (годы + позиция)</label>
                <div data-repeater="career">
                    <?php foreach (($data['career'] ?? []) as $i => $row): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Годы</label><input type="text" name="career[<?= $i ?>][years]" value="<?= htmlspecialchars($row['years'] ?? '', ENT_QUOTES) ?>" placeholder="2023 – н.в."></div>
                            <div class="form-field"><label>Позиция</label><textarea name="career[<?= $i ?>][text]"><?= htmlspecialchars($row['text'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="career">
                    <div class="form-field"><label>Годы</label><input type="text" name="career[__INDEX__][years]"></div>
                    <div class="form-field"><label>Позиция</label><textarea name="career[__INDEX__][text]"></textarea></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="career">+ Добавить период</button></div>
            </div>
            <hr>
            <div class="form-field"><label for="edu_title">Правая колонка — заголовок</label><input type="text" id="edu_title" name="edu_title" value="<?= htmlspecialchars($data['edu_title'] ?? 'Образование', ENT_QUOTES) ?>"></div>
            <div>
                <label>Образование (годы + степень + вуз)</label>
                <div data-repeater="edu_items">
                    <?php foreach (($data['edu_items'] ?? []) as $i => $row): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Годы</label><input type="text" name="edu_items[<?= $i ?>][years]" value="<?= htmlspecialchars($row['years'] ?? '', ENT_QUOTES) ?>" placeholder="2011 – 2013"></div>
                            <div class="form-field"><label>Степень</label><input type="text" name="edu_items[<?= $i ?>][title]" value="<?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Учебное заведение</label><input type="text" name="edu_items[<?= $i ?>][org]" value="<?= htmlspecialchars($row['org'] ?? '', ENT_QUOTES) ?>"></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="edu_items">
                    <div class="form-field"><label>Годы</label><input type="text" name="edu_items[__INDEX__][years]"></div>
                    <div class="form-field"><label>Степень</label><input type="text" name="edu_items[__INDEX__][title]"></div>
                    <div class="form-field"><label>Учебное заведение</label><input type="text" name="edu_items[__INDEX__][org]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="edu_items">+ Добавить</button></div>
            </div>
            <div class="form-field"><label for="extra_title">Доп. образование — заголовок</label><input type="text" id="extra_title" name="extra_title" value="<?= htmlspecialchars($data['extra_title'] ?? '', ENT_QUOTES) ?>" placeholder="Дополнительное образование"></div>
            <div class="form-field"><label for="extra_text">Доп. образование — пункты (по одному на строку)</label><textarea id="extra_text" name="extra_text" rows="3"><?= htmlspecialchars($data['extra_text'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="form-field"><label for="quote_text">Цитата</label><textarea id="quote_text" name="quote_text" rows="2"><?= htmlspecialchars($data['quote_text'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="form-field"><label for="quote_author">Автор цитаты</label><input type="text" id="quote_author" name="quote_author" value="<?= htmlspecialchars($data['quote_author'] ?? '', ENT_QUOTES) ?>"></div>
        <?php endif; ?>

        <?php if ($type === 'anchor_nav'): ?>
            <div>
                <label>Пункты навигации (якоря разделов или ссылки)</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Название</label><input type="text" name="items[<?= $i ?>][label]" value="<?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?>" placeholder="Обзор"></div>
                            <div class="form-field"><label>Ссылка (якорь #block-N или URL)</label><input type="text" name="items[<?= $i ?>][url]" value="<?= htmlspecialchars($item['url'] ?? '', ENT_QUOTES) ?>" placeholder="#block-12"></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>Название</label><input type="text" name="items[__INDEX__][label]"></div>
                    <div class="form-field"><label>Ссылка</label><input type="text" name="items[__INDEX__][url]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить пункт</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'stages'): ?>
            <div class="form-field"><label for="all_text">Ссылка «Все …» — текст</label><input type="text" id="all_text" name="all_text" value="<?= htmlspecialchars($data['all_text'] ?? '', ENT_QUOTES) ?>" placeholder="Все этапы"></div>
            <div class="form-field"><label for="all_url">Ссылка «Все …» — URL</label><input type="text" id="all_url" name="all_url" value="<?= htmlspecialchars($data['all_url'] ?? '', ENT_QUOTES) ?>"></div>
            <div>
                <label>Этапы</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Годы</label><input type="text" name="items[<?= $i ?>][year]" value="<?= htmlspecialchars($item['year'] ?? '', ENT_QUOTES) ?>" placeholder="2026–2027"></div>
                            <div class="form-field"><label>Подпись этапа</label><input type="text" name="items[<?= $i ?>][stage]" value="<?= htmlspecialchars($item['stage'] ?? '', ENT_QUOTES) ?>" placeholder="III этап"></div>
                            <div class="form-field"><label>Заголовок</label><input type="text" name="items[<?= $i ?>][title]" value="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Текст</label><textarea name="items[<?= $i ?>][text]"><?= htmlspecialchars($item['text'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <div class="form-field"><label>Статус</label><select name="items[<?= $i ?>][status]">
                                <?php foreach (['done' => 'Завершён', 'active' => 'В процессе', 'planned' => 'Запланирован'] as $sv => $sl): ?>
                                    <option value="<?= $sv ?>" <?= ($item['status'] ?? 'planned') === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                                <?php endforeach; ?>
                            </select></div>
                            <div class="form-field"><label>Свой текст статуса (необязательно)</label><input type="text" name="items[<?= $i ?>][status_text]" value="<?= htmlspecialchars($item['status_text'] ?? '', ENT_QUOTES) ?>"></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить этап</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>Годы</label><input type="text" name="items[__INDEX__][year]"></div>
                    <div class="form-field"><label>Подпись этапа</label><input type="text" name="items[__INDEX__][stage]"></div>
                    <div class="form-field"><label>Заголовок</label><input type="text" name="items[__INDEX__][title]"></div>
                    <div class="form-field"><label>Текст</label><textarea name="items[__INDEX__][text]"></textarea></div>
                    <div class="form-field"><label>Статус</label><select name="items[__INDEX__][status]"><option value="done">Завершён</option><option value="active">В процессе</option><option value="planned" selected>Запланирован</option></select></div>
                    <div class="form-field"><label>Свой текст статуса</label><input type="text" name="items[__INDEX__][status_text]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить этап</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить этап</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'text_image'): ?>
            <div class="form-field"><label for="text">Текст (абзацы через пустую строку)</label><textarea id="text" name="text" rows="5"><?= htmlspecialchars($data['text'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="form-field"><label for="image">Фото (URL)</label><input type="text" id="image" name="image" value="<?= htmlspecialchars($data['image'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/..."></div>
            <div>
                <label>Мини-фичи под текстом (иконка + подпись)</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>SVG-иконка</label><textarea name="items[<?= $i ?>][icon_svg]"><?= htmlspecialchars($item['icon_svg'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <div class="form-field"><label>Подпись</label><input type="text" name="items[<?= $i ?>][label]" value="<?= htmlspecialchars($item['label'] ?? '', ENT_QUOTES) ?>"></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>SVG-иконка</label><textarea name="items[__INDEX__][icon_svg]"></textarea></div>
                    <div class="form-field"><label>Подпись</label><input type="text" name="items[__INDEX__][label]"></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'docs_list'): ?>
            <div class="form-field"><label for="all_text">Ссылка «Все …» — текст</label><input type="text" id="all_text" name="all_text" value="<?= htmlspecialchars($data['all_text'] ?? '', ENT_QUOTES) ?>" placeholder="Все документы"></div>
            <div class="form-field"><label for="all_url">Ссылка «Все …» — URL</label><input type="text" id="all_url" name="all_url" value="<?= htmlspecialchars($data['all_url'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="columns">Колонок</label><select id="columns" name="columns"><?php foreach ([1,2,3,4] as $n): ?><option value="<?= $n ?>" <?= (int)($data['columns'] ?? 4)===$n?'selected':'' ?>><?= $n ?></option><?php endforeach; ?></select></div>
            <div>
                <label>Документы</label>
                <div data-repeater="items">
                    <?php foreach (($data['items'] ?? []) as $i => $item): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Название</label><input type="text" name="items[<?= $i ?>][title]" value="<?= htmlspecialchars($item['title'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Мета (PDF · 2.4 МБ)</label><input type="text" name="items[<?= $i ?>][meta]" value="<?= htmlspecialchars($item['meta'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field">
                                <label>Ссылка на файл</label>
                                <div style="display:flex;gap:8px;">
                                    <input type="text" name="items[<?= $i ?>][url]" value="<?= htmlspecialchars($item['url'] ?? '', ENT_QUOTES) ?>" style="flex:1;">
                                    <button type="button" class="btn btn--secondary btn--small" data-media-pick data-media-target="[name='items[<?= $i ?>][url]']" data-media-type="all_files">Выбрать</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="items">
                    <div class="form-field"><label>Название</label><input type="text" name="items[__INDEX__][title]"></div>
                    <div class="form-field"><label>Мета</label><input type="text" name="items[__INDEX__][meta]"></div>
                    <div class="form-field">
                        <label>Ссылка на файл</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" name="items[__INDEX__][url]" style="flex:1;">
                            <button type="button" class="btn btn--secondary btn--small" data-media-pick data-media-target="[name='items[__INDEX__][url]']" data-media-type="all_files">Выбрать</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="items">+ Добавить документ</button></div>
            </div>
        <?php endif; ?>

        <?php if ($type === 'map_point'): ?>
            <div class="form-field"><label for="embed_url">Iframe-карта (https-URL встраивания, приоритет)</label><input type="text" id="embed_url" name="embed_url" value="<?= htmlspecialchars($data['embed_url'] ?? '', ENT_QUOTES) ?>" placeholder="https://www.google.com/maps/embed?..."></div>
            <div class="form-field"><label for="image">Или картинка-карта (URL)</label><input type="text" id="image" name="image" value="<?= htmlspecialchars($data['image'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/map.jpg"></div>
            <div class="form-field"><label for="card_title">Карточка на карте — заголовок</label><input type="text" id="card_title" name="card_title" value="<?= htmlspecialchars($data['card_title'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="address">Карточка — адрес (можно в 2 строки)</label><textarea id="address" name="address" rows="2"><?= htmlspecialchars($data['address'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="form-field"><label for="button_text">Кнопка (напр. «Построить маршрут») — текст</label><input type="text" id="button_text" name="button_text" value="<?= htmlspecialchars($data['button_text'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="button_url">Кнопка — ссылка</label><input type="text" id="button_url" name="button_url" value="<?= htmlspecialchars($data['button_url'] ?? '', ENT_QUOTES) ?>" placeholder="https://maps.google.com/?daddr=..."></div>
        <?php endif; ?>

        <?php if ($type === 'org_structure'): ?>
            <div class="form-field"><label for="head_title">Руководитель — должность</label><input type="text" id="head_title" name="head_title" value="<?= htmlspecialchars($data['head_title'] ?? 'Директор', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="head_name">Руководитель — Ф.И.О. (необязательно)</label><input type="text" id="head_name" name="head_name" value="<?= htmlspecialchars($data['head_name'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="form-field"><label for="head_url">Руководитель — ссылка (напр. на страницу директора)</label><input type="text" id="head_url" name="head_url" value="<?= htmlspecialchars($data['head_url'] ?? '', ENT_QUOTES) ?>" placeholder="/direktor"></div>
            <div class="form-field">
                <label for="side_items">Органы при руководителе (по одному на строку)</label>
                <textarea id="side_items" name="side_items" rows="3" placeholder="Координационный совет&#10;Советник"><?= htmlspecialchars($data['side_items'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <div>
                <label>Ветки (заместители / блоки подразделений)</label>
                <div data-repeater="branches">
                    <?php foreach (($data['branches'] ?? []) as $i => $branch): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Должность</label><input type="text" name="branches[<?= $i ?>][title]" value="<?= htmlspecialchars($branch['title'] ?? '', ENT_QUOTES) ?>" placeholder="Первый заместитель директора"></div>
                            <div class="form-field"><label>Ф.И.О. (необязательно)</label><input type="text" name="branches[<?= $i ?>][name]" value="<?= htmlspecialchars($branch['name'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Подразделения (по одному на строку)</label><textarea name="branches[<?= $i ?>][units]" rows="5" placeholder="Отдел стратегического планирования&#10;Отдел анализа и мониторинга"><?= htmlspecialchars($branch['units'] ?? '', ENT_QUOTES) ?></textarea></div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить ветку</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="branches">
                    <div class="form-field"><label>Должность</label><input type="text" name="branches[__INDEX__][title]" placeholder="Заместитель директора"></div>
                    <div class="form-field"><label>Ф.И.О. (необязательно)</label><input type="text" name="branches[__INDEX__][name]"></div>
                    <div class="form-field"><label>Подразделения (по одному на строку)</label><textarea name="branches[__INDEX__][units]" rows="5"></textarea></div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить ветку</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="branches">+ Добавить ветку</button></div>
            </div>
            <div class="form-field"><label for="footnote">Примечание под схемой (необязательно)</label><input type="text" id="footnote" name="footnote" value="<?= htmlspecialchars($data['footnote'] ?? '', ENT_QUOTES) ?>" placeholder="Структура утверждена постановлением…"></div>
        <?php endif; ?>

        <?php // Общие поля оформления свёрнуты: контент-поля — основная задача,
              // а отступы/фон/анимация нужны эпизодически (значения внутри
              // закрытого details всё равно отправляются с формой). ?>
        <details class="form-section">
            <summary>Оформление секции <span class="form-section__hint">отступы, фон, анимация появления</span></summary>
            <div class="form-section__body">
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
        // Фон секции + полноширинная подложка + независимые отступы сверху/снизу.
        $bg = $data['_bg'] ?? 'none';
        $fullwidth = !empty($data['_fullwidth']);
        $padTop = $data['_pad_top'] ?? 'default';
        $padBottom = $data['_pad_bottom'] ?? 'default';
        $bgOpts = ['none' => 'Нет', 'light' => 'Светлый', 'tint' => 'Лёгкий акцент', 'navy' => 'Тёмный (navy)'];
        $padOpts = ['default' => 'По умолчанию', 'none' => 'Нет', 'small' => 'Малый', 'medium' => 'Средний', 'large' => 'Большой'];
        ?>
        <div class="form-field">
            <label for="bg">Фон секции</label>
            <select id="bg" name="bg">
                <?php foreach ($bgOpts as $v => $l): ?><option value="<?= $v ?>" <?= $bg === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="fullwidth" name="fullwidth" value="1" <?= $fullwidth ? 'checked' : '' ?>>
            <label for="fullwidth">Фон во всю ширину экрана (контент остаётся по центру)</label>
        </div>
        <div class="form-field">
            <label for="pad_top">Отступ сверху</label>
            <select id="pad_top" name="pad_top">
                <?php foreach ($padOpts as $v => $l): ?><option value="<?= $v ?>" <?= $padTop === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="pad_bottom">Отступ снизу</label>
            <select id="pad_bottom" name="pad_bottom">
                <?php foreach ($padOpts as $v => $l): ?><option value="<?= $v ?>" <?= $padBottom === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
            </select>
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
            </div>
        </details>

        <?php if (\App\Core\Auth::isSuperAdmin()): ?>
        <details class="form-section">
            <summary>Дополнительно <span class="form-section__hint">собственный CSS блока</span></summary>
            <div class="form-section__body">
        <div class="form-field">
            <label for="custom_css">Собственный CSS блока</label>
            <textarea id="custom_css" name="custom_css" style="min-height:140px; font-family: monospace;"><?= htmlspecialchars($block['custom_css'] ?? '', ENT_QUOTES) ?></textarea>
            <span class="form-hint">
                Стили автоматически изолируются: любой селектор при выводе на сайте получает префикс
                <code>#block-<?= (int) $block['id'] ?></code>, поэтому не может повлиять на остальную страницу.
                Пример: <code>h2 { color: red; }</code> → <code>#block-<?= (int) $block['id'] ?> h2 { color: red; }</code>.
            </span>
        </div>
            </div>
        </details>
        <?php else: ?>
            <?php /* Редактор не может менять кастомный CSS — сохраняем прежнее значение. */ ?>
            <input type="hidden" name="custom_css" value="<?= htmlspecialchars($block['custom_css'] ?? '', ENT_QUOTES) ?>">
        <?php endif; ?>

        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn--primary">Сохранить блок</button>
            <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>" class="btn">Отмена</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
