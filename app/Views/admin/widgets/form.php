<?php

use App\Core\Csrf;
use App\Models\Widget;

$isEdit = !empty($widget['id']);
$pageTitle = $isEdit ? 'Редактирование виджета' : 'Новый виджет';
$activeNav = 'widgets';
require __DIR__ . '/../layout/header.php';

/** @var array|null $widget */
/** @var array $data */
/** @var array $languages */
/** @var string|null $error */

$action = $isEdit ? '/admin/widgets/' . (int) $widget['id'] . '/edit' : '/admin/widgets/create';
$currentType = $widget['type'] ?? 'latest_news';
?>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="<?= $action ?>" class="form-grid">
        <?= Csrf::field() ?>

        <div class="form-field">
            <label for="type">Тип виджета</label>
            <?php if ($isEdit): ?>
                <input type="text" value="<?= htmlspecialchars(Widget::TYPE_LABELS[$currentType] ?? $currentType, ENT_QUOTES) ?>" disabled>
            <?php else: ?>
                <select id="type" name="type" data-widget-type-select>
                    <?php foreach (Widget::TYPE_LABELS as $val => $label): ?>
                        <option value="<?= $val ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="form-field">
            <label for="title">Заголовок виджета (необязательно)</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($widget['title'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-field">
            <label for="sidebar">Колонка</label>
            <select id="sidebar" name="sidebar">
                <option value="left" <?= ($widget['sidebar'] ?? 'left') === 'left' ? 'selected' : '' ?>>Левая</option>
                <option value="right" <?= ($widget['sidebar'] ?? '') === 'right' ? 'selected' : '' ?>>Правая</option>
            </select>
        </div>

        <div class="form-field">
            <label for="lang">Язык</label>
            <select id="lang" name="lang">
                <option value="">Все языки</option>
                <?php foreach ($languages as $l): ?>
                    <option value="<?= htmlspecialchars($l['code'], ENT_QUOTES) ?>" <?= ($widget['lang'] ?? '') === $l['code'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name'], ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php $showType = static fn (string $t) => $isEdit ? ($currentType === $t) : true; ?>

        <!-- Настройки: количество (latest_news / projects_list / team_list) -->
        <?php foreach (['latest_news', 'projects_list', 'team_list'] as $countType): ?>
            <?php if ($showType($countType)): ?>
                <div class="form-field" data-wtype="<?= $countType ?>" style="<?= (!$isEdit && $countType !== 'latest_news') ? 'display:none;' : 'display:flex;' ?>">
                    <label>Сколько элементов показывать</label>
                    <input type="number" name="count" value="<?= (int) ($data['count'] ?? 5) ?>" min="1" max="20">
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Настройки: contacts -->
        <?php if ($showType('contacts')): ?>
            <div class="form-field form-field--checkbox" data-wtype="contacts" style="<?= (!$isEdit) ? 'display:none;' : 'display:flex;' ?>">
                <input type="checkbox" id="show_socials" name="show_socials" value="1" <?= !empty($data['show_socials']) ? 'checked' : '' ?>>
                <label for="show_socials">Показывать иконки соцсетей (из настроек шапки)</label>
            </div>
        <?php endif; ?>

        <!-- Настройки: custom_html -->
        <?php if ($showType('custom_html')): ?>
            <div class="form-field" data-wtype="custom_html" style="<?= (!$isEdit) ? 'display:none;' : 'display:flex;' ?>">
                <label>HTML-код виджета</label>
                <textarea name="html" style="min-height:160px; font-family: monospace;"><?= htmlspecialchars($data['html'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
        <?php endif; ?>

        <?php $design = \App\Core\WidgetRenderer::normalizeDesign($data); ?>
        <fieldset style="border:1px solid var(--admin-border);border-radius:8px;padding:16px;">
            <legend style="padding:0 8px;font-weight:600;">Оформление</legend>
            <div class="form-field">
                <label for="design_style">Стиль</label>
                <select id="design_style" name="design_style">
                    <option value="default" <?= $design['style'] === 'default' ? 'selected' : '' ?>>Стандарт (без фона)</option>
                    <option value="card" <?= $design['style'] === 'card' ? 'selected' : '' ?>>Карточка (рамка и фон)</option>
                    <option value="tinted" <?= $design['style'] === 'tinted' ? 'selected' : '' ?>>Подложка (лёгкий фон)</option>
                    <option value="navy" <?= $design['style'] === 'navy' ? 'selected' : '' ?>>Тёмный (акцентный)</option>
                </select>
            </div>
            <div class="form-field">
                <label for="design_pad">Внутренние отступы</label>
                <select id="design_pad" name="design_pad">
                    <option value="compact" <?= $design['pad'] === 'compact' ? 'selected' : '' ?>>Компактные</option>
                    <option value="normal" <?= $design['pad'] === 'normal' ? 'selected' : '' ?>>Обычные</option>
                    <option value="spacious" <?= $design['pad'] === 'spacious' ? 'selected' : '' ?>>Просторные</option>
                </select>
            </div>
            <div class="form-field form-field--checkbox">
                <input type="checkbox" id="design_accent" name="design_accent" value="1" <?= $design['accent'] ? 'checked' : '' ?>>
                <label for="design_accent">Акцентная линия под заголовком</label>
            </div>
        </fieldset>

        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= (!$isEdit || !empty($widget['is_active'])) ? 'checked' : '' ?>>
            <label for="is_active">Активен</label>
        </div>

        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn--primary">Сохранить</button>
            <a href="/admin/widgets" class="btn">Отмена</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
