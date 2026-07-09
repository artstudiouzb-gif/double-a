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

        <?php if (in_array($type, ['text', 'cta', 'advantages', 'gallery', 'testimonials', 'counters', 'team_list', 'projects_list', 'news_latest', 'partners', 'banner', 'faq', 'subscribe', 'contact_cards', 'hero', 'categories_grid', 'media_materials', 'cards_grid', 'image_cards', 'media_gallery', 'news_feature'], true)): ?>
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
                <label for="button_text">Текст кнопки</label>
                <input type="text" id="button_text" name="button_text" value="<?= htmlspecialchars($data['button_text'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="form-field">
                <label for="button_url">Ссылка кнопки</label>
                <input type="text" id="button_url" name="button_url" value="<?= htmlspecialchars($data['button_url'] ?? '', ENT_QUOTES) ?>" placeholder="/catalog/documenty">
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
            <div class="form-field">
                <label for="eyebrow">Надзаголовок (мелкий текст над заголовком)</label>
                <input type="text" id="eyebrow" name="eyebrow" value="<?= htmlspecialchars($data['eyebrow'] ?? '', ENT_QUOTES) ?>" placeholder="СТРАТЕГИЯ. РЕФОРМЫ. РАЗВИТИЕ.">
            </div>
            <div class="form-field">
                <label for="subtitle">Подзаголовок</label>
                <textarea id="subtitle" name="subtitle" rows="2"><?= htmlspecialchars($data['subtitle'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <?= \App\Core\AdminUi::imageField('image', $data['image'] ?? '', ['label' => 'Фото/постер фона', 'hint' => 'Фоновое изображение героя (и постер для видео).']) ?>
            <div class="form-field">
                <label for="video_url">Видео-фон (URL mp4, необязательно)</label>
                <input type="text" id="video_url" name="video_url" value="<?= htmlspecialchars($data['video_url'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/hero.mp4">
                <span class="form-hint">Загрузите mp4 в «Файлы» и вставьте ссылку. Видео зациклено, без звука.</span>
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
            <?php if ($type === 'cards_grid'): ?>
                <div class="form-field"><label for="columns">Колонок</label>
                    <select id="columns" name="columns">
                        <?php foreach ([2,3,4,5] as $n): ?><option value="<?= $n ?>" <?= (int)($data['columns'] ?? 5)===$n?'selected':'' ?>><?= $n ?></option><?php endforeach; ?>
                    </select>
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
