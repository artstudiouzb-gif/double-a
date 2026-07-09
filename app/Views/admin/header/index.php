<?php

use App\Core\Csrf;

$pageTitle = 'Шапка сайта';
$activeNav = 'header';
require __DIR__ . '/../layout/header.php';

/** @var array $config */
$networks = ['telegram' => 'Telegram', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp'];
?>
<div class="form-card">
    <form method="post" action="/admin/header" class="form-grid">
        <?= Csrf::field() ?>

        <div class="header-builder__group">
            <h3>Дизайн шапки</h3>
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
        </div>

        <div class="header-builder__group">
            <h3>Конструктор: элементы по зонам</h3>
            <p class="form-hint" style="margin-top:0;">
                Перетаскивайте элементы между палитрой и зонами (Слева / Центр / Справа).
                Логотип и меню размещаются отдельно (ниже). «Разделитель» можно добавлять
                несколько раз. Порядок в зоне задаётся перетаскиванием.
            </p>
            <?php
            $elements = \App\Core\HeaderConfig::ELEMENTS;
            $placed = $config['elements'];
            // Иконки-подсказки для чипов (мелкие эмодзи-нейтральные метки не нужны — текст).
            $renderChip = function (string $type) use ($elements): string {
                $label = $elements[$type] ?? $type;
                return '<span class="hdr-chip" draggable="true" data-el="' . htmlspecialchars($type, ENT_QUOTES) . '">'
                    . '<span class="hdr-chip__grip" aria-hidden="true">⠿</span>'
                    . htmlspecialchars($label, ENT_QUOTES)
                    . '<button type="button" class="hdr-chip__remove" aria-label="Убрать" title="Убрать">&times;</button>'
                    . '</span>';
            };
            // В палитре — неиспользованные (неповторяемые) типы + разделитель (источник).
            $used = array_merge($placed['left'], $placed['center'], $placed['right']);
            ?>
            <div class="hdr-builder" data-hdr-builder data-labels="<?= htmlspecialchars(json_encode($elements, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">
                <div class="hdr-builder__palette">
                    <div class="hdr-builder__palette-label">Доступные элементы</div>
                    <div class="hdr-builder__dropzone hdr-builder__dropzone--palette" data-hdr-zone="palette">
                        <?php foreach ($elements as $type => $label): ?>
                            <?php if ($type === 'divider' || !in_array($type, $used, true)): ?>
                                <?= $renderChip($type) ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="hdr-builder__zones">
                    <?php foreach (['left' => 'Слева', 'center' => 'Центр', 'right' => 'Справа'] as $zone => $zoneLabel): ?>
                        <div class="hdr-builder__zone">
                            <div class="hdr-builder__zone-label"><?= $zoneLabel ?></div>
                            <div class="hdr-builder__dropzone" data-hdr-zone="<?= $zone ?>">
                                <?php foreach ($placed[$zone] as $type): ?>
                                    <?= $renderChip($type) ?>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="elements[<?= $zone ?>]" data-hdr-input="<?= $zone ?>" value="<?= htmlspecialchars(implode(',', $placed[$zone]), ENT_QUOTES) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="header-builder__group">
            <h3>Расположение логотипа и меню</h3>
            <div class="form-field">
                <label for="logo_position">Логотип</label>
                <select id="logo_position" name="logo_position">
                    <option value="left" <?= $config['logo_position'] === 'left' ? 'selected' : '' ?>>Слева</option>
                    <option value="center" <?= $config['logo_position'] === 'center' ? 'selected' : '' ?>>По центру</option>
                </select>
                <span class="form-hint">Для «Центрированного» макета логотип всегда по центру.</span>
            </div>
            <div class="form-field">
                <label for="menu_position">Меню</label>
                <select id="menu_position" name="menu_position">
                    <option value="left" <?= $config['menu_position'] === 'left' ? 'selected' : '' ?>>Слева</option>
                    <option value="center" <?= $config['menu_position'] === 'center' ? 'selected' : '' ?>>По центру</option>
                    <option value="right" <?= $config['menu_position'] === 'right' ? 'selected' : '' ?>>Справа</option>
                </select>
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

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить шапку</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
