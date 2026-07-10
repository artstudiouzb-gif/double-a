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
        </section>
    <?php endforeach; ?>

    <section class="design-section">
        <h2 class="design-section__title">Google-шрифты</h2>
        <p class="form-hint" style="margin:0 0 12px;">
            Отдельные шрифты для заголовков и текста из каталога Google Fonts
            (все — с поддержкой кириллицы, подключаются с fonts.googleapis.com).
            «Стандарт» возвращает роль к встроенным PT Serif / PT Sans.
        </p>
        <?php
        $gHeading = \App\Models\Setting::get('design_font_google_heading', '');
        $gBody = \App\Models\Setting::get('design_font_google_body', '');
        ?>
        <div class="design-opt">
            <div class="design-opt__label"><span>Шрифт заголовков</span></div>
            <div class="design-opt__choices">
                <select name="font_google_heading" class="form-select" style="min-width:280px;">
                    <option value="">Стандарт (PT Serif)</option>
                    <?php foreach (\App\Core\DesignSettings::GOOGLE_FONTS as $slug => $f): ?>
                        <option value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>" <?= $gHeading === $slug ? 'selected' : '' ?>><?= htmlspecialchars($f[0], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="design-opt">
            <div class="design-opt__label"><span>Шрифт текста</span></div>
            <div class="design-opt__choices">
                <select name="font_google_body" class="form-select" style="min-width:280px;">
                    <option value="">Стандарт (PT Sans)</option>
                    <?php foreach (\App\Core\DesignSettings::GOOGLE_FONTS as $slug => $f): ?>
                        <option value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>" <?= $gBody === $slug ? 'selected' : '' ?>><?= htmlspecialchars($f[0], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
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

    // Пересобираем src превью из выбранных радио-опций (с дебаунсом).
    var timer = null;
    function refresh() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var params = new URLSearchParams();
            document.querySelectorAll('.design-fine input[type=radio]:checked').forEach(function (r) {
                params.set(r.name, r.value);
            });
            frame.src = '/admin/design/preview?' + params.toString();
        }, 250);
    }
    document.querySelectorAll('.design-fine input[type=radio]').forEach(function (r) {
        r.addEventListener('change', refresh);
    });

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
