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

<section class="design-section">
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

<section class="design-section">
    <h2 class="design-section__title">Живое превью</h2>
    <div class="design-preview">
        <div class="design-preview__bar">
            <button type="button" class="btn btn--small is-active" data-pv-width="100%">Десктоп</button>
            <button type="button" class="btn btn--small" data-pv-width="768px">Планшет</button>
            <button type="button" class="btn btn--small" data-pv-width="390px">Телефон</button>
            <span class="form-hint" style="margin-left:auto;">Обновляется при выборе опций ниже — до сохранения.</span>
        </div>
        <div class="design-preview__stage">
            <iframe class="design-preview__frame" data-design-preview src="/admin/design/preview" title="Превью сайта" loading="lazy"></iframe>
        </div>
    </div>
</section>

<form method="post" action="/admin/design" class="design-fine">
    <?= Csrf::field() ?>
    <?php foreach ($grouped as $groupName => $groupOpts): ?>
        <section class="design-section">
            <h2 class="design-section__title"><?= htmlspecialchars($groupName, ENT_QUOTES) ?></h2>
            <?php foreach ($groupOpts as $key => $opt): ?>
                <?php if ($key === 'font_style') { continue; } // Ниже выводится единый выбор всех источников шрифта. ?>
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

            <?php if ($groupName === 'Цвета и шрифт'): ?>
                <?php
                $customAppearance = \App\Core\DesignSettings::customAppearance();
                $primary = $customAppearance['color_primary'];
                $accent = $customAppearance['color_accent'];
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
        </section>
    <?php endforeach; ?>

    <section class="design-section">
        <h2 class="design-section__title">Точные размеры</h2>
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
                <span>Базовый размер текста</span>
                <small>От 12 до 24 px с шагом 0,5. Пусто — использовать выбранный вариант размера выше.</small>
            </div>
            <div class="design-opt__choices">
                <?php $fontSizeCustom = preg_replace('/px$/', '', \App\Core\DesignSettings::fontSizeCustom()); ?>
                <input type="number" name="font_size_custom" min="12" max="24" step="0.5" inputmode="decimal"
                       value="<?= htmlspecialchars((string) $fontSizeCustom, ENT_QUOTES) ?>" placeholder="напр. 16.5" style="max-width:220px;" data-design-preview-field>
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
    </section>

    <div class="design-actions">
        <button type="submit" class="btn btn--primary">Сохранить настройки дизайна</button>
    </div>
</form>

<script nonce="<?= \App\Core\SecurityHeaders::nonce() ?>">
(function () {
    'use strict';
    var frame = document.querySelector('[data-design-preview]');
    if (!frame) { return; }

    // Пересобираем src превью из всех настроек внешнего вида (с дебаунсом).
    var timer = null;
    function refresh() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var params = new URLSearchParams();
            document.querySelectorAll('.design-fine input[type=radio]:checked').forEach(function (r) {
                params.set(r.name, r.value);
            });
            document.querySelectorAll('.design-fine [data-design-preview-field]').forEach(function (field) {
                params.set(field.name, field.value);
            });
            frame.src = '/admin/design/preview?' + params.toString();
        }, 250);
    }
    document.querySelectorAll('.design-fine input[type=radio], .design-fine [data-design-preview-field]').forEach(function (field) {
        field.addEventListener('change', refresh);
        if (field.matches('input[type="text"], input[type="number"]')) { field.addEventListener('input', refresh); }
    });

    var fontChoice = document.querySelector('[data-font-body-choice]');
    var customFontFields = document.querySelector('[data-custom-font-fields]');
    function syncCustomFontFields() {
        if (fontChoice && customFontFields) {
            customFontFields.hidden = fontChoice.value !== 'style:custom';
        }
    }
    if (fontChoice) { fontChoice.addEventListener('change', syncCustomFontFields); }
    syncCustomFontFields();

    // Переключатель ширины (десктоп/планшет/телефон).
    document.querySelectorAll('[data-pv-width]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-pv-width]').forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            frame.style.width = btn.getAttribute('data-pv-width');
        });
    });
})();
</script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
