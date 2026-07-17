<?php

use App\Core\Csrf;
use App\Core\HeaderConfig;

$pageTitle = 'Шапка сайта';
$activeNav = 'header';
require __DIR__ . '/../layout/header.php';

/** @var array $config */
$networks = ['telegram' => 'Telegram', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp'];

$elements = HeaderConfig::ELEMENTS;
// SVG-иконки элементов конструктора (единый линейный стиль, 1.6px).
$elementIcons = [
    'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>',
    'language' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>',
    'social' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="6" cy="12" r="2.5"/><circle cx="17" cy="6" r="2.5"/><circle cx="17" cy="18" r="2.5"/><path d="m8.2 10.8 6.6-3.6m-6.6 6 6.6 3.6"/></svg>',
    'button' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="8" width="18" height="8" rx="4"/><path d="M8 12h8"/></svg>',
    'theme' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/></svg>',
    'a11y' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="5" r="2"/><path d="M4 9h16M12 9v6m0 0-3.5 6M12 15l3.5 6"/></svg>',
    'phone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>',
    'email' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>',
    'snippet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m8 8-4 4 4 4m8-8 4 4-4 4M14 5l-4 14"/></svg>',
    'divider' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 4v16"/></svg>',
    'spacer' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 12h16M4 12l3-3M4 12l3 3M20 12l-3-3M20 12l-3 3"/></svg>',
    'space' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 5v14M18 5v14"/></svg>',
];

$renderChip = function (string $type) use ($elements, $elementIcons): string {
    $label = $elements[$type] ?? $type;
    return '<span class="hdr-chip hb-el" draggable="true" data-el="' . htmlspecialchars($type, ENT_QUOTES) . '"'
        . ' title="' . htmlspecialchars($label, ENT_QUOTES) . '">'
        . '<span class="hb-el__grip" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor" width="10" height="14"><circle cx="8" cy="5" r="1.6"/><circle cx="16" cy="5" r="1.6"/><circle cx="8" cy="12" r="1.6"/><circle cx="16" cy="12" r="1.6"/><circle cx="8" cy="19" r="1.6"/><circle cx="16" cy="19" r="1.6"/></svg></span>'
        . '<span class="hb-el__icon">' . ($elementIcons[$type] ?? '') . '</span>'
        . '<span class="hb-el__label">' . htmlspecialchars($label, ENT_QUOTES) . '</span>'
        . '<button type="button" class="hb-el__remove hdr-chip__remove" aria-label="Убрать" title="Убрать">&times;</button>'
        . '</span>';
};

/**
 * Зоны одной секции конструктора. $placed — раскладка left/center/right,
 * $inputName — префикс скрытых полей. Использует общий drag-and-drop admin.js
 * (селекторы data-hdr-*).
 */
$renderZones = function (array $placed, string $inputName) use ($renderChip): string {
    ob_start(); ?>
    <div class="hb-zones">
        <?php foreach (['left' => 'Слева', 'center' => 'Центр', 'right' => 'Справа'] as $zone => $zoneLabel): ?>
            <div class="hb-zone">
                <div class="hb-zone__label"><?= $zoneLabel ?></div>
                <div class="hb-zone__drop hdr-builder__dropzone" data-hdr-zone="<?= $zone ?>">
                    <?php foreach ($placed[$zone] ?? [] as $type): ?>
                        <?= $renderChip($type) ?>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="<?= $inputName ?>[<?= $zone ?>]" data-hdr-input="<?= $zone ?>" value="<?= htmlspecialchars(implode(',', $placed[$zone] ?? []), ENT_QUOTES) ?>">
            </div>
        <?php endforeach; ?>
    </div>
    <?php return (string) ob_get_clean();
};

$labelsJson = htmlspecialchars(json_encode($elements, JSON_UNESCAPED_UNICODE), ENT_QUOTES);

/** Селектор высоты секции. */
$heightSelect = function (string $name, string $current): string {
    $out = '<select name="' . $name . '" class="hb-select" aria-label="Высота секции">';
    foreach (['slim' => 'Компактная', 'normal' => 'Обычная', 'tall' => 'Высокая'] as $v => $l) {
        $out .= '<option value="' . $v . '"' . ($current === $v ? ' selected' : '') . '>' . $l . '</option>';
    }
    return $out . '</select>';
};
?>
<div class="admin-builder-workspace">
    <form method="post" action="/admin/header" class="form-grid" enctype="multipart/form-data">
        <?= Csrf::field() ?>

        <div class="header-builder__group">
            <h3>Дизайн меню и макет шапки</h3>
            <?php
            $layouts = [
                'stacked' => ['Двухрядный', 'Логотип и утилиты сверху, меню — отдельной полосой во всю ширину.'],
                'inline' => ['В одну строку', 'Логотип, меню и утилиты в одном компактном ряду.'],
                'centered' => ['Центрированный', 'Логотип по центру сверху, меню центрировано под ним.'],
                'drawer' => ['Боковая панель', 'Меню скрыто за кнопкой и выезжает сбоку (на всех экранах).'],
            ];
            $currentLayout = $config['layout'] ?? 'stacked';
            ?>
            <div class="header-layout-picker">
                <?php foreach ($layouts as $val => [$label, $desc]): ?>
                    <label class="header-layout-picker__option<?= $currentLayout === $val ? ' is-selected' : '' ?>">
                        <input type="radio" name="layout" value="<?= $val ?>" <?= $currentLayout === $val ? 'checked' : '' ?>>
                        <span class="header-layout-picker__preview header-layout-picker__preview--<?= $val ?>" aria-hidden="true">
                            <span class="hlp-logo"></span><span class="hlp-nav"></span>
                        </span>
                        <span class="header-layout-picker__label"><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
                        <span class="header-layout-picker__desc"><?= htmlspecialchars($desc, ENT_QUOTES) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="hb-inline-fields">
                <div class="form-field">
                    <label for="logo_position">Логотип</label>
                    <select id="logo_position" name="logo_position">
                        <option value="left" <?= $config['logo_position'] === 'left' ? 'selected' : '' ?>>Слева</option>
                        <option value="center" <?= $config['logo_position'] === 'center' ? 'selected' : '' ?>>По центру</option>
                    </select>
                    <span class="form-hint">Здесь — положение. Само изображение логотипа загружается в <a href="/admin/settings#logo">Настройки → Логотип</a> (там кнопка загрузки и медиабиблиотека).</span>
                </div>
                <div class="form-field">
                    <label for="logo_width">Ширина логотипа, px</label>
                    <input id="logo_width" name="logo_width" type="number" min="40" max="600" step="1" value="<?= (int) ($config['logo_width'] ?? 240) ?>" inputmode="numeric">
                    <span class="form-hint">От 40 до 600 px. На узком экране логотип автоматически уменьшится.</span>
                </div>
                <div class="form-field">
                    <label for="logo_height">Высота логотипа, px</label>
                    <input id="logo_height" name="logo_height" type="number" min="20" max="200" step="1" value="<?= (int) ($config['logo_height'] ?? 48) ?>" inputmode="numeric">
                    <span class="form-hint">От 20 до 200 px. Пропорции изображения сохраняются.</span>
                </div>
                <div class="form-field">
                    <label for="menu_position">Выравнивание меню</label>
                    <select id="menu_position" name="menu_position">
                        <option value="left" <?= $config['menu_position'] === 'left' ? 'selected' : '' ?>>Слева</option>
                        <option value="center" <?= $config['menu_position'] === 'center' ? 'selected' : '' ?>>По центру</option>
                        <option value="right" <?= $config['menu_position'] === 'right' ? 'selected' : '' ?>>Справа</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>Пункты меню</label>
                    <a class="btn btn--small" href="/admin/menu">Управлять пунктами меню →</a>
                    <span class="form-hint">Иконки и разделители пунктов — в разделе «Меню».</span>
                </div>
            </div>
            <div class="form-field hb-divider-field">
                <label for="borders">Разделительные линии секций</label>
                <select id="borders" name="borders">
                    <option value="full" <?= ($config['borders'] ?? 'full') === 'full' ? 'selected' : '' ?>>Во всю ширину экрана</option>
                    <option value="container" <?= ($config['borders'] ?? '') === 'container' ? 'selected' : '' ?>>По ширине контента шапки</option>
                    <option value="none" <?= ($config['borders'] ?? '') === 'none' ? 'selected' : '' ?>>Без линий</option>
                </select>
            </div>
            <div class="hb-behavior">
                <div class="hb-behavior__options">
                    <label class="hb-behavior-card">
                        <span class="hb-switch">
                            <input type="checkbox" name="header_sticky" value="1" <?= !empty($config['sticky']) ? 'checked' : '' ?>>
                            <span class="hb-switch__track"></span>
                            <span class="hb-behavior-card__title">Липкая шапка</span>
                        </span>
                        <span class="hb-behavior-card__hint">Остаётся в верхней части экрана при прокрутке страницы.</span>
                    </label>
                    <label class="hb-behavior-card">
                        <span class="hb-switch">
                            <input type="checkbox" name="header_transparent" value="1" <?= !empty($config['transparent']) ? 'checked' : '' ?>>
                            <span class="hb-switch__track"></span>
                            <span class="hb-behavior-card__title">Прозрачная шапка</span>
                        </span>
                        <span class="hb-behavior-card__hint">Располагается поверх первого полноэкранного блока и становится сплошной при прокрутке.</span>
                    </label>
                    <label class="hb-behavior-card">
                        <span class="hb-switch">
                            <input type="checkbox" name="header_shadow" value="1" <?= !empty($config['shadow']['enabled']) ? 'checked' : '' ?>>
                            <span class="hb-switch__track"></span>
                            <span class="hb-behavior-card__title">Тень под шапкой</span>
                        </span>
                        <span class="hb-behavior-card__hint">Лёгкая тень отделяет шапку от содержимого. В прозрачном режиме не рисуется (до прокрутки).</span>
                        <span style="display:flex;align-items:center;gap:8px;margin-top:8px;">
                            <label for="header_shadow_size" style="font-size:13px;">Размер, px</label>
                            <input type="number" id="header_shadow_size" name="header_shadow_size" min="2" max="60" step="1"
                                   value="<?= (int) ($config['shadow']['size'] ?? 14) ?>" style="width:90px;">
                        </span>
                    </label>
                </div>
                <p class="hb-behavior__note">Прозрачный режим лучше всего работает с первым блоком на основе фото или видео. Текст, элементы управления и эмблема без отдельного изображения автоматически становятся светлыми.</p>
                <div class="hb-behavior__media">
                    <?= \App\Core\AdminUi::imageField('logo_light', $config['logo_light'] ?? '', [
                        'label' => 'Светлый логотип для прозрачной шапки (необязательно)',
                        'file' => 'logo_light_file',
                        'hint' => 'Если основной логотип — картинка, здесь задаётся его белая версия. '
                            . 'Эмблема-звезда без картинки перекрашивается автоматически.',
                    ]) ?>
                </div>
            </div>

            <?php $hdrLangs = \App\Models\Language::active(); ?>
            <?php if (count($hdrLangs) > 1): ?>
                <div class="hb-behavior" style="margin-top:14px;">
                    <label class="form-label" style="font-weight:700;">Логотип для каждого языка (необязательно)</label>
                    <span class="form-hint">Свой логотип на страницах конкретного языка. Пусто — берётся общий логотип из <a href="/admin/settings#logo">Настроек</a>. Поддерживается SVG/PNG. Светлый вариант — для прозрачной шапки.</span>
                    <?php foreach ($hdrLangs as $hlang): $hc = htmlspecialchars((string) $hlang['code'], ENT_QUOTES); ?>
                        <div class="hb-langlogo" style="margin-top:12px; padding-top:12px; border-top:1px solid var(--admin-border);">
                            <div class="hb-langlogo__name" style="font-weight:600; margin-bottom:8px;"><?= htmlspecialchars((string) $hlang['name'], ENT_QUOTES) ?> <span style="color:var(--admin-muted); font-weight:400;">(<?= $hc ?>)</span></div>
                            <div class="hb-inline-fields">
                                <?= \App\Core\AdminUi::imageField('logo_lang_' . $hc, (string) ($config['logo_by_lang'][$hlang['code']] ?? ''), [
                                    'label' => 'Логотип',
                                    'file' => 'logo_lang_' . $hc . '_file',
                                ]) ?>
                                <?= \App\Core\AdminUi::imageField('logo_light_lang_' . $hc, (string) ($config['logo_light_by_lang'][$hlang['code']] ?? ''), [
                                    'label' => 'Светлый логотип (для прозрачной шапки)',
                                    'file' => 'logo_light_lang_' . $hc . '_file',
                                ]) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="header-builder__group">
            <h3>Конструктор секций</h3>
            <p class="form-hint" style="margin-top:0;">
                Шапка состоит из трёх секций. Перетаскивайте элементы из палитры в зоны секций;
                «Разделитель» можно использовать много раз, остальные — по одному в секции
                (но можно повторить в разных секциях). Порядок — перетаскиванием.
            </p>

            <div class="hb-palette hdr-builder" data-hdr-builder data-labels="<?= $labelsJson ?>">
                <div class="hb-palette__label">Палитра элементов</div>
                <div class="hb-palette__items hdr-builder__dropzone" data-hdr-zone="palette">
                    <?php foreach ($elements as $type => $label): ?>
                        <?= $renderChip($type) ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php // ---------- TOP SECTION ---------- ?>
            <section class="hb-section hb-section--top hdr-builder" data-hdr-builder data-labels="<?= $labelsJson ?>">
                <header class="hb-section__head">
                    <span class="hb-section__badge">Top</span>
                    <span class="hb-section__title">Верхняя полоса</span>
                    <span class="hb-section__controls">
                        <label class="hb-switch"><input type="checkbox" name="topbar_enabled" value="1" <?= !empty($config['topbar']['enabled']) ? 'checked' : '' ?>><span class="hb-switch__track"></span> Включена</label>
                        <select name="topbar_style" class="hb-select" aria-label="Стиль полосы">
                            <option value="navy" <?= ($config['topbar']['style'] ?? 'navy') === 'navy' ? 'selected' : '' ?>>Тёмная (navy)</option>
                            <option value="light" <?= ($config['topbar']['style'] ?? '') === 'light' ? 'selected' : '' ?>>Светлая</option>
                            <option value="teal" <?= ($config['topbar']['style'] ?? '') === 'teal' ? 'selected' : '' ?>>Бирюзовая</option>
                        </select>
                        <label class="hb-switch"><input type="checkbox" name="topbar_mobile" value="1" <?= !empty($config['topbar']['show_mobile']) ? 'checked' : '' ?>><span class="hb-switch__track"></span> Показывать на мобильном</label>
                        <?= $heightSelect('topbar_height', $config['topbar']['height'] ?? 'normal') ?>
                    </span>
                </header>
                <?= $renderZones($config['topbar']['zones'] ?? [], 'topbar_zones') ?>
            </section>

            <?php // ---------- MIDDLE SECTION ---------- ?>
            <section class="hb-section hb-section--middle">
                <header class="hb-section__head">
                    <span class="hb-section__badge hb-section__badge--middle">Middle</span>
                    <span class="hb-section__title">Основная секция (логотип + утилиты)</span>
                    <span class="hb-section__controls">
                        <?= $heightSelect('middlebar_height', $config['middlebar']['height'] ?? 'normal') ?>
                        <label class="hb-switch" title="Иначе фон берётся из темы">
                            <input type="checkbox" name="middlebar_bg_use" value="1" <?= ($config['middlebar']['bg'] ?? '') !== '' ? 'checked' : '' ?>>
                            <span class="hb-switch__track"></span> Свой фон
                        </label>
                        <input type="color" name="middlebar_bg" value="<?= htmlspecialchars(($config['middlebar']['bg'] ?? '') !== '' ? $config['middlebar']['bg'] : '#ffffff', ENT_QUOTES) ?>" aria-label="Цвет фона основной секции" style="width:44px;height:30px;padding:2px;">
                        <span class="hb-tabs" data-hdr-tabs>
                            <button type="button" class="hb-tabs__tab hdr-tabs__tab is-active" data-hdr-tab="desktop">Десктоп</button>
                            <button type="button" class="hb-tabs__tab hdr-tabs__tab" data-hdr-tab="mobile">Мобильный</button>
                        </span>
                    </span>
                </header>
                <div class="hdr-tabs__panel is-active hdr-builder" data-hdr-panel="desktop" data-hdr-builder data-labels="<?= $labelsJson ?>">
                    <?= $renderZones($config['elements'], 'elements') ?>
                </div>
                <div class="hdr-tabs__panel hdr-builder" data-hdr-panel="mobile" data-hdr-builder data-labels="<?= $labelsJson ?>">
                    <?= $renderZones($config['elements_mobile'], 'elements_mobile') ?>
                </div>
                <p class="form-hint">Логотип размещается автоматически по настройке «Логотип» выше.</p>
            </section>

            <?php // ---------- BOTTOM SECTION ---------- ?>
            <section class="hb-section hb-section--bottom hdr-builder" data-hdr-builder data-labels="<?= $labelsJson ?>">
                <header class="hb-section__head">
                    <span class="hb-section__badge hb-section__badge--bottom">Bottom</span>
                    <span class="hb-section__title">Нижняя полоса (меню + элементы)</span>
                    <span class="hb-section__controls">
                        <?= $heightSelect('bottombar_height', $config['bottombar']['height'] ?? 'normal') ?>
                        <label class="hb-switch" title="Иначе фон берётся из темы">
                            <input type="checkbox" name="bottombar_bg_use" value="1" <?= ($config['bottombar']['bg'] ?? '') !== '' ? 'checked' : '' ?>>
                            <span class="hb-switch__track"></span> Свой фон
                        </label>
                        <input type="color" name="bottombar_bg" value="<?= htmlspecialchars(($config['bottombar']['bg'] ?? '') !== '' ? $config['bottombar']['bg'] : '#ffffff', ENT_QUOTES) ?>" aria-label="Цвет фона нижней полосы" style="width:44px;height:30px;padding:2px;">
                        <span class="hb-note">Меню занимает полосу автоматически; элементы встают рядом.</span>
                    </span>
                </header>
                <?= $renderZones($config['bottombar']['zones'] ?? [], 'bottombar_zones') ?>
            </section>
        </div>

        <div class="header-builder__group">
            <h3>Данные элементов</h3>
            <div class="hb-inline-fields">
                <div class="form-field">
                    <label for="contact_phone">Телефон (элемент «Телефон»)</label>
                    <input type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($config['contacts']['phone'] ?? '', ENT_QUOTES) ?>" placeholder="+998 71 203 10 00">
                </div>
                <div class="form-field">
                    <label for="contact_email">E-mail (элемент «E-mail»)</label>
                    <input type="text" id="contact_email" name="contact_email" value="<?= htmlspecialchars($config['contacts']['email'] ?? '', ENT_QUOTES) ?>" placeholder="info@strategy.uz">
                </div>
            </div>
            <div class="form-field">
                <label for="snippet">Сниппет (элемент «Сниппет», HTML проходит санитайзер)</label>
                <textarea id="snippet" name="snippet" rows="2" style="font-family:monospace;"><?= htmlspecialchars($config['snippet'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
        </div>

        <div class="header-builder__group">
            <h3>Переключатель языков</h3>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="ls_enabled" name="ls_enabled" value="1" <?= $config['language_switcher']['enabled'] ? 'checked' : '' ?>>
                <label for="ls_enabled">Показывать переключатель языков</label>
            </div>
            <div class="form-field">
                <label for="ls_format">Формат вывода</label>
                <select id="ls_format" name="ls_format">
                    <option value="code" <?= $config['language_switcher']['format'] === 'code' ? 'selected' : '' ?>>ISO-код (RU, UZ)</option>
                    <option value="name" <?= $config['language_switcher']['format'] === 'name' ? 'selected' : '' ?>>Полное название</option>
                    <option value="flag" <?= $config['language_switcher']['format'] === 'flag' ? 'selected' : '' ?>>Флаг (эмодзи по коду страны)</option>
                </select>
            </div>
        </div>

        <div class="header-builder__group">
            <h3>Кнопки соцсетей</h3>
            <div data-repeater="social">
                <?php foreach ($config['social_buttons'] as $i => $btn): ?>
                    <div class="repeater-row">
                        <div class="form-field">
                            <label>Сеть</label>
                            <select name="social[<?= $i ?>][network]">
                                <?php foreach ($networks as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($btn['network'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Ссылка</label>
                            <input type="text" name="social[<?= $i ?>][url]" value="<?= htmlspecialchars($btn['url'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-repeater-template="social">
                <div class="form-field">
                    <label>Сеть</label>
                    <select name="social[__INDEX__][network]">
                        <?php foreach ($networks as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Ссылка</label>
                    <input type="text" name="social[__INDEX__][url]">
                </div>
                <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
            </template>
            <div class="repeater-actions">
                <button type="button" class="btn btn--small" data-repeater-add="social">+ Добавить соцсеть</button>
            </div>
        </div>

        <div class="header-builder__group">
            <h3>CTA-кнопка</h3>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="cta_enabled" name="cta_enabled" value="1" <?= $config['cta']['enabled'] ? 'checked' : '' ?>>
                <label for="cta_enabled">Показывать кнопку призыва к действию</label>
            </div>
            <div class="hb-inline-fields">
                <div class="form-field">
                    <label for="cta_text">Текст кнопки</label>
                    <input type="text" id="cta_text" name="cta_text" value="<?= htmlspecialchars($config['cta']['text'], ENT_QUOTES) ?>">
                </div>
                <div class="form-field">
                    <label for="cta_url">Ссылка кнопки</label>
                    <input type="text" id="cta_url" name="cta_url" value="<?= htmlspecialchars($config['cta']['url'], ENT_QUOTES) ?>">
                </div>
                <div class="form-field">
                    <label for="cta_style">Стиль</label>
                    <select id="cta_style" name="cta_style">
                        <option value="filled" <?= $config['cta']['style'] === 'filled' ? 'selected' : '' ?>>Залитая</option>
                        <option value="outline" <?= $config['cta']['style'] === 'outline' ? 'selected' : '' ?>>Контурная</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn--primary">Сохранить шапку</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
