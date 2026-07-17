<?php

use App\Core\Csrf;

$pageTitle = 'Дизайн сайта';
$activeNav = 'design';
require __DIR__ . '/../layout/header.php';

/** @var array $options */
/** @var array $presets */
/** @var array $userPresets */
/** @var array $values */
/** @var string $activePreset */

// Группируем опции точной настройки по разделам.
$grouped = [];
foreach ($options as $key => $opt) {
    $grouped[$opt['group']][$key] = $opt;
}
?>
<p class="form-hint">Готовые конфигурации применяют набор настроек одним кликом. Ниже — точная настройка: выберите вариант для каждого параметра. Изменения сразу применяются к сайту.</p>

<?php // Секции точной настройки свёрнуты — якоря открывают нужную и прокручивают к ней. ?>
<div class="design-nav">
    <nav class="design-anchors" aria-label="Разделы настроек дизайна">
        <a href="#design-presets">Конфигурации</a>
        <?php $gi = 0; foreach (array_keys($grouped) as $gname): ?>
            <a href="#design-g<?= $gi ?>"><?= htmlspecialchars($gname, ENT_QUOTES) ?></a>
        <?php $gi++; endforeach; ?>
    </nav>
    <button type="button" class="btn btn--small" data-design-toggle-all>Развернуть всё</button>
</div>

<section class="design-section" id="design-presets">
    <h2 class="design-section__title">Готовые конфигурации</h2>
    <div class="design-presets">
        <?php foreach ($presets as $pkey => $preset): ?>
            <form method="post" action="/admin/design/preset" class="design-preset<?= $activePreset === $pkey ? ' is-active' : '' ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="preset" value="<?= htmlspecialchars($pkey, ENT_QUOTES) ?>">
                <div class="design-preset__head">
                    <strong><?= htmlspecialchars($preset['label'], ENT_QUOTES) ?></strong>
                    <?php if ($activePreset === $pkey): ?><span class="design-preset__badge">Активна</span><?php endif; ?>
                </div>
                <p class="design-preset__desc"><?= htmlspecialchars($preset['desc'], ENT_QUOTES) ?></p>
                <button type="submit" class="btn btn--small btn--primary">Применить</button>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<section class="design-section">
    <h2 class="design-section__title">Мои конфигурации</h2>
    <?php if (!empty($userPresets)): ?>
        <div class="design-presets" style="margin-bottom:16px;">
            <?php foreach ($userPresets as $uslug => $upreset): ?>
                <div class="design-preset design-preset--user<?= $activePreset === 'user:' . $uslug ? ' is-active' : '' ?>">
                    <div class="design-preset__head">
                        <strong><?= htmlspecialchars((string) $upreset['label'], ENT_QUOTES) ?></strong>
                        <?php if ($activePreset === 'user:' . $uslug): ?><span class="design-preset__badge">Активна</span><?php endif; ?>
                    </div>
                    <p class="design-preset__desc">Сохранённая вами конфигурация.</p>
                    <div style="display:flex;gap:8px;">
                        <form method="post" action="/admin/design/preset" style="margin:0;">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="preset" value="user:<?= htmlspecialchars($uslug, ENT_QUOTES) ?>">
                            <button type="submit" class="btn btn--small btn--primary">Применить</button>
                        </form>
                        <form method="post" action="/admin/design/preset/delete" style="margin:0;" data-confirm="Удалить конфигурацию «<?= htmlspecialchars((string) $upreset['label'], ENT_QUOTES) ?>»?">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="slug" value="<?= htmlspecialchars($uslug, ENT_QUOTES) ?>">
                            <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="form-hint">Сохранённых конфигураций пока нет. Настройте параметры ниже, сохраните — и сможете переключаться в один клик.</p>
    <?php endif; ?>
    <form method="post" action="/admin/design/preset/save" class="design-save-preset">
        <?= Csrf::field() ?>
        <input type="text" name="name" maxlength="40" placeholder="Название конфигурации (напр. «Зимняя тема»)" required>
        <button type="submit" class="btn">Сохранить текущие настройки</button>
    </form>
</section>


<form method="post" action="/admin/design" class="design-fine">
    <?= Csrf::field() ?>
    <?php $gi = 0; foreach ($grouped as $groupName => $groupOpts): ?>
        <details class="design-section design-section--collapsible" id="design-g<?= $gi; $gi++; ?>">
            <summary class="design-section__title"><?= htmlspecialchars($groupName, ENT_QUOTES) ?></summary>
            <?php foreach ($groupOpts as $key => $opt): ?>
                <?php if ($key === 'font_style') { continue; } // Ниже выводится единый выбор всех источников шрифта. ?>
                <?php if ($key === 'font_size' || $key === 'line_height') { continue; } // Типографика управляется точными числами ниже. ?>
                <div class="design-opt">
                    <div class="design-opt__label">
                        <span><?= htmlspecialchars($opt['label'], ENT_QUOTES) ?></span>
                        <?php if (!empty($opt['hint'])): ?><small><?= htmlspecialchars($opt['hint'], ENT_QUOTES) ?></small><?php endif; ?>
                    </div>
                    <div class="design-opt__choices">
                        <?php foreach ($opt['choices'] as $val => $label): ?>
                            <label class="design-card">
                                <input type="radio" name="<?= htmlspecialchars($key, ENT_QUOTES) ?>" value="<?= htmlspecialchars($val, ENT_QUOTES) ?>" <?= ($values[$key] ?? '') === $val ? 'checked' : '' ?>>
                                <span class="design-card__preview design-card__preview--<?= htmlspecialchars($key . '-' . $val, ENT_QUOTES) ?>"></span>
                                <span class="design-card__label"><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($groupName === 'Типографика'): ?>
                <?php
                // Пресетные значения остаются форматом хранения (и фолбэком при
                // пустых полях), поэтому передаются скрытыми полями без карточек.
                $fontSizePreset = ['sm' => '15', 'md' => '16', 'lg' => '17', 'xl' => '18'][$values['font_size'] ?? 'md'] ?? '16';
                $lineHeightPreset = ['tight' => '1.45', 'normal' => '1.6', 'relaxed' => '1.8'][$values['line_height'] ?? 'normal'] ?? '1.6';
                $fontSizeCustom = preg_replace('/px$/', '', \App\Core\DesignSettings::fontSizeCustom());
                $lineHeightCustom = \App\Core\DesignSettings::lineHeightCustom();
                ?>
                <input type="hidden" name="font_size" value="<?= htmlspecialchars((string) ($values['font_size'] ?? 'md'), ENT_QUOTES) ?>">
                <input type="hidden" name="line_height" value="<?= htmlspecialchars((string) ($values['line_height'] ?? 'normal'), ENT_QUOTES) ?>">
                <div class="design-opt">
                    <div class="design-opt__label">
                        <span>Основной текст (p, span)</span>
                        <small>Базовый размер текста сайта — абзацы и обычный текст наследуют его. От 12 до 24 px с шагом 0,5. Пусто — значение конфигурации (<?= htmlspecialchars($fontSizePreset, ENT_QUOTES) ?> px).</small>
                    </div>
                    <div class="design-opt__choices">
                        <input type="number" name="font_size_custom" min="12" max="24" step="0.5" inputmode="decimal"
                               value="<?= htmlspecialchars((string) $fontSizeCustom, ENT_QUOTES) ?>" placeholder="напр. <?= htmlspecialchars($fontSizePreset, ENT_QUOTES) ?>" style="max-width:220px;" data-design-preview-field>
                    </div>
                </div>
                <div class="design-opt">
                    <div class="design-opt__label">
                        <span>Межстрочный интервал</span>
                        <small>Высота строки основного текста, от 1 до 2,5 (множитель размера шрифта). Пусто — значение конфигурации (<?= htmlspecialchars($lineHeightPreset, ENT_QUOTES) ?>).</small>
                    </div>
                    <div class="design-opt__choices">
                        <input type="number" name="line_height_custom" min="1" max="2.5" step="0.05" inputmode="decimal"
                               value="<?= htmlspecialchars($lineHeightCustom, ENT_QUOTES) ?>" placeholder="напр. <?= htmlspecialchars($lineHeightPreset, ENT_QUOTES) ?>" style="max-width:220px;" data-design-preview-field>
                    </div>
                </div>
                <?php $typoSizes = \App\Core\DesignSettings::typographySizes(); ?>
                <div class="design-manual">
                    <div class="design-manual__head">
                        <strong>Размеры по элементам</strong>
                        <span>Точный размер (px) для конкретных элементов сайта. Пустое поле — размер темы не меняется; в поле показан ориентировочный размер по умолчанию.</span>
                    </div>
                    <div class="design-manual__grid">
                        <?php foreach (\App\Core\DesignSettings::TYPO_SIZES as $fsKey => $fsMeta): ?>
                            <div class="form-field">
                                <label for="design_<?= htmlspecialchars($fsKey, ENT_QUOTES) ?>"><?= htmlspecialchars($fsMeta[0], ENT_QUOTES) ?>, px</label>
                                <input type="number" id="design_<?= htmlspecialchars($fsKey, ENT_QUOTES) ?>" name="<?= htmlspecialchars($fsKey, ENT_QUOTES) ?>"
                                       min="8" max="96" step="0.5" inputmode="decimal"
                                       value="<?= htmlspecialchars(preg_replace('/px$/', '', $typoSizes[$fsKey]) ?? '', ENT_QUOTES) ?>"
                                       placeholder="напр. <?= htmlspecialchars($fsMeta[2], ENT_QUOTES) ?>" data-design-preview-field>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($groupName === 'Цвета и шрифт'): ?>
                <?php
                $customAppearance = \App\Core\DesignSettings::customAppearance();
                $primary = $customAppearance['color_primary'];
                $accent = $customAppearance['color_accent'];
                $semanticColors = \App\Core\DesignSettings::semanticColors();
                $semanticSpacings = \App\Core\DesignSettings::semanticSpacings();
                $defaultTheme = (string) \App\Models\Setting::get('default_theme', 'light');
                if (!in_array($defaultTheme, ['light', 'dark', 'auto'], true)) { $defaultTheme = 'light'; }
                $bodyFontChoice = \App\Core\DesignSettings::bodyFontChoice();
                $gHeading = (string) \App\Models\Setting::get('design_font_google_heading', '');
                ?>
                <div class="design-manual">
                    <div class="design-manual__head">
                        <strong>Цвета и шрифты</strong>
                        <span>Все источники шрифта собраны здесь; выбранный вариант применяется без скрытых переопределений.</span>
                    </div>
                    <div class="design-manual__grid">
                        <div class="form-field">
                            <label for="design_color_primary">Основной цвет</label>
                            <input type="color" id="design_color_primary" name="color_primary" value="<?= htmlspecialchars($primary, ENT_QUOTES) ?>" data-design-preview-field>
                        </div>
                        <div class="form-field">
                            <label for="design_color_accent">Акцентный цвет</label>
                            <input type="color" id="design_color_accent" name="color_accent" value="<?= htmlspecialchars($accent, ENT_QUOTES) ?>" data-design-preview-field>
                        </div>
                        <?php foreach ([
                            'bg_primary' => 'Фон страницы',
                            'bg_surface' => 'Фон поверхностей и карточек',
                            'text_main' => 'Основной цвет текста',
                            'text_muted' => 'Приглушённый цвет текста',
                            'border_color' => 'Цвет границ',
                        ] as $colorKey => $colorLabel): ?>
                            <div class="form-field">
                                <label for="design_<?= htmlspecialchars($colorKey, ENT_QUOTES) ?>"><?= htmlspecialchars($colorLabel, ENT_QUOTES) ?></label>
                                <input type="color" id="design_<?= htmlspecialchars($colorKey, ENT_QUOTES) ?>" name="<?= htmlspecialchars($colorKey, ENT_QUOTES) ?>" value="<?= htmlspecialchars($semanticColors[$colorKey], ENT_QUOTES) ?>" data-design-preview-field>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ([
                            'space_small' => 'Малый отступ (space-small)',
                            'space_premium' => 'Премиальный отступ (space-premium)',
                            'space_max' => 'Максимальный отступ (space-max)',
                        ] as $spaceKey => $spaceLabel): ?>
                            <div class="form-field">
                                <label for="design_<?= htmlspecialchars($spaceKey, ENT_QUOTES) ?>"><?= htmlspecialchars($spaceLabel, ENT_QUOTES) ?></label>
                                <input type="text" id="design_<?= htmlspecialchars($spaceKey, ENT_QUOTES) ?>" name="<?= htmlspecialchars($spaceKey, ENT_QUOTES) ?>" value="<?= htmlspecialchars($semanticSpacings[$spaceKey], ENT_QUOTES) ?>" data-design-preview-field placeholder="clamp(...) или px/rem">
                            </div>
                        <?php endforeach; ?>
                        <div class="form-field design-manual__wide">
                            <label for="design_font_body_choice">Основной шрифт текста</label>
                            <select id="design_font_body_choice" name="font_body_choice" data-design-preview-field data-font-body-choice>
                                <optgroup label="Базовые шрифты">
                                    <?php foreach (\App\Core\DesignSettings::FONTS as $fontKey => $fontData): ?>
                                        <?php if ($fontKey === 'custom') { continue; } ?>
                                        <?php $choice = 'style:' . $fontKey; ?>
                                        <option value="<?= htmlspecialchars($choice, ENT_QUOTES) ?>" <?= $bodyFontChoice === $choice ? 'selected' : '' ?>><?= htmlspecialchars($fontData[0], ENT_QUOTES) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Google Fonts">
                                    <?php foreach (\App\Core\DesignSettings::GOOGLE_FONTS as $slug => $fontData): ?>
                                        <?php $choice = 'google:' . $slug; ?>
                                        <option value="<?= htmlspecialchars($choice, ENT_QUOTES) ?>" <?= $bodyFontChoice === $choice ? 'selected' : '' ?>><?= htmlspecialchars($fontData[0], ENT_QUOTES) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Собственный шрифт">
                                    <option value="style:custom" <?= $bodyFontChoice === 'style:custom' ? 'selected' : '' ?>>Свой CSS-стек или файл</option>
                                </optgroup>
                            </select>
                            <small class="form-hint">Внешние шрифты загружаются с fonts.googleapis.com; базовые и собственный шрифт не требуют такого подключения.</small>
                        </div>
                        <div class="design-manual__custom-font design-manual__wide" data-custom-font-fields<?= $bodyFontChoice !== 'style:custom' ? ' hidden' : '' ?>>
                            <div class="form-field design-manual__wide">
                                <label for="design_font_family">Свой шрифт (CSS font-family)</label>
                                <input type="text" id="design_font_family" name="font_family" maxlength="200"
                                       value="<?= htmlspecialchars($customAppearance['font_family'], ENT_QUOTES) ?>"
                                       placeholder="'MyBrandFont', system-ui, sans-serif" data-design-preview-field>
                            </div>
                            <div class="form-field">
                                <label for="design_font_face_name">Имя семейства</label>
                                <input type="text" id="design_font_face_name" name="font_face_name" maxlength="80"
                                       value="<?= htmlspecialchars((string) \App\Models\Setting::get('font_face_name', ''), ENT_QUOTES) ?>"
                                       placeholder="MyBrandFont" data-design-preview-field>
                            </div>
                            <div class="form-field">
                                <label for="design_font_url">Файл .woff2</label>
                                <input type="text" id="design_font_url" name="font_url" maxlength="500"
                                       value="<?= htmlspecialchars((string) \App\Models\Setting::get('font_url', ''), ENT_QUOTES) ?>"
                                       placeholder="/uploads/public/font.woff2" data-design-preview-field>
                            </div>
                        </div>
                        <div class="form-field design-manual__wide">
                            <label for="design_font_heading">Шрифт заголовков</label>
                            <select id="design_font_heading" name="font_google_heading" data-design-preview-field>
                                <option value="">Стандартный (PT Serif)</option>
                                <?php foreach (\App\Core\DesignSettings::GOOGLE_FONTS as $slug => $fontData): ?>
                                    <option value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>" <?= $gHeading === $slug ? 'selected' : '' ?>><?= htmlspecialchars($fontData[0], ENT_QUOTES) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="design_default_theme">Тема для посетителей</label>
                            <select id="design_default_theme" name="default_theme" data-design-preview-field>
                                <option value="light" <?= $defaultTheme === 'light' ? 'selected' : '' ?>>Светлая</option>
                                <option value="dark" <?= $defaultTheme === 'dark' ? 'selected' : '' ?>>Тёмная</option>
                                <option value="auto" <?= $defaultTheme === 'auto' ? 'selected' : '' ?>>Авто (по системе)</option>
                            </select>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($groupName === 'Общие'): ?>
                <?php // Точные значения — рядом со своими пресетами (ширина, скругление). ?>
                <div class="design-opt">
                    <div class="design-opt__label">
                        <span>Точная ширина</span>
                        <small>Перекрывает выбор «Ширина контейнера» выше. Напр. <code>1440px</code>, <code>90%</code> или число <code>1440</code> (px). Пусто — использовать пресет.</small>
                    </div>
                    <div class="design-opt__choices">
                        <input type="text" name="container_custom" value="<?= htmlspecialchars((string) \App\Models\Setting::get('design_container_custom', ''), ENT_QUOTES) ?>" placeholder="напр. 1440px" style="max-width:220px;" data-design-preview-field>
                    </div>
                </div>
                <div class="design-opt">
                    <div class="design-opt__label">
                        <span>Точное скругление</span>
                        <small>От 0 до 48 px. Применяется к карточкам и полям, а также к кнопкам с формой «Скруглённые». Пусто — использовать вариант выше.</small>
                    </div>
                    <div class="design-opt__choices">
                        <?php $radiusCustom = preg_replace('/px$/', '', \App\Core\DesignSettings::radiusCustom()); ?>
                        <input type="number" name="radius_custom" min="0" max="48" step="0.5" inputmode="decimal"
                               value="<?= htmlspecialchars((string) $radiusCustom, ENT_QUOTES) ?>" placeholder="напр. 12" style="max-width:220px;" data-design-preview-field>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($groupName === 'Шапка'): ?>
                <?php // Разделители главного меню — часть настроек шапки. ?>
                <div class="design-opt">
                    <div class="design-opt__label">
                        <span>Цвет разделителей меню</span>
                        <small>Цвет вертикальных разделительных линий в главном меню.</small>
                    </div>
                    <div class="design-opt__choices" style="display:flex;align-items:center;gap:12px;">
                        <input type="color" name="menu_divider_color" value="<?= htmlspecialchars((string) \App\Models\Setting::get('design_menu_divider_color', '#ffffff'), ENT_QUOTES) ?>" style="width:64px;height:38px;padding:4px;" data-design-preview-field>
                        <label style="display:inline-flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;">
                            <input type="checkbox" name="menu_divider_color_use" value="1" <?= \App\Models\Setting::get('design_menu_divider_color_use', '') === '1' ? 'checked' : '' ?> data-design-preview-field>
                            Использовать свой цвет (иначе автоматический)
                        </label>
                    </div>
                </div>
                <div class="design-opt">
                    <div class="design-opt__label">
                        <span>Толщина разделителей меню</span>
                        <small>Ширина разделительной линии в пикселях. От 0 до 10 px. Пусто — 1px.</small>
                    </div>
                    <div class="design-opt__choices">
                        <?php $divThickness = preg_replace('/px$/', '', (string) \App\Models\Setting::get('design_menu_divider_thickness', '')); ?>
                        <input type="number" name="menu_divider_thickness" min="0" max="10" step="0.5" inputmode="decimal"
                               value="<?= htmlspecialchars((string) $divThickness, ENT_QUOTES) ?>" placeholder="напр. 1" style="max-width:220px;" data-design-preview-field>
                    </div>
                </div>
                <div class="design-opt">
                    <div class="design-opt__label">
                        <span>Высота разделителей меню</span>
                        <small>Высота разделительной линии в пикселях. От 2 до 100 px. Пусто — 18px.</small>
                    </div>
                    <div class="design-opt__choices">
                        <?php $divHeight = preg_replace('/px$/', '', (string) \App\Models\Setting::get('design_menu_divider_height', '')); ?>
                        <input type="number" name="menu_divider_height" min="2" max="100" step="1" inputmode="numeric"
                               value="<?= htmlspecialchars((string) $divHeight, ENT_QUOTES) ?>" placeholder="напр. 18" style="max-width:220px;" data-design-preview-field>
                    </div>
                </div>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>

    <div class="design-actions">
        <button type="submit" class="btn btn--primary">Сохранить настройки дизайна</button>
    </div>
</form>

<script nonce="<?= \App\Core\SecurityHeaders::nonce() ?>">
(function () {
    'use strict';
    var fontChoice = document.querySelector('[data-font-body-choice]');
    var customFontFields = document.querySelector('[data-custom-font-fields]');
    function syncCustomFontFields() {
        if (fontChoice && customFontFields) {
            customFontFields.hidden = fontChoice.value !== 'style:custom';
        }
    }
    if (fontChoice) { fontChoice.addEventListener('change', syncCustomFontFields); }
    syncCustomFontFields();

    // Сворачиваемые секции: клик по якорю открывает нужную и прокручивает к ней.
    function openFromHash() {
        var id = (location.hash || '').slice(1);
        if (!id) { return; }
        var el = document.getElementById(id);
        if (el && el.tagName === 'DETAILS') { el.open = true; }
    }
    document.querySelectorAll('.design-anchors a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function () {
            var el = document.getElementById(a.getAttribute('href').slice(1));
            if (el && el.tagName === 'DETAILS') { el.open = true; }
        });
    });
    window.addEventListener('hashchange', openFromHash);
    openFromHash();

    // Кнопка «развернуть/свернуть всё».
    var toggleAll = document.querySelector('[data-design-toggle-all]');
    if (toggleAll) {
        toggleAll.addEventListener('click', function () {
            var sections = document.querySelectorAll('details.design-section--collapsible');
            var anyClosed = [].some.call(sections, function (d) { return !d.open; });
            sections.forEach(function (d) { d.open = anyClosed; });
            toggleAll.textContent = anyClosed ? 'Свернуть всё' : 'Развернуть всё';
        });
    }
})();
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
