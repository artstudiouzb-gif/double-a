<?php

use App\Core\Csrf;
use App\Core\FooterConfig;

$pageTitle = 'Подвал сайта';
$activeNav = 'footer';
require __DIR__ . '/../layout/header.php';

/** @var array $config */
$widgets = FooterConfig::WIDGETS;

/** Рендер select виджета с выбранным значением. */
$widgetSelect = function (string $name, string $current) use ($widgets): string {
    $out = '<select name="' . $name . '" class="footer-col__widget">';
    foreach ($widgets as $val => $label) {
        $sel = $current === $val ? ' selected' : '';
        $out .= '<option value="' . htmlspecialchars($val, ENT_QUOTES) . '"' . $sel . '>' . htmlspecialchars($label, ENT_QUOTES) . '</option>';
    }
    return $out . '</select>';
};
?>
<div class="form-card">
    <form method="post" action="/admin/footer" class="form-grid">
        <?= Csrf::field() ?>

        <div class="header-builder__group">
            <h3>Стиль подвала</h3>
            <div class="form-field">
                <label for="style">Оформление</label>
                <select id="style" name="style">
                    <option value="columns" <?= $config['style'] === 'columns' ? 'selected' : '' ?>>Колонки (конструктор)</option>
                    <option value="minimal" <?= $config['style'] === 'minimal' ? 'selected' : '' ?>>Минимальный (только копирайт)</option>
                </select>
                <span class="form-hint">В режиме «Колонки» подвал собирается из колонок ниже.</span>
            </div>
        </div>

        <div class="header-builder__group">
            <h3>Колонки подвала</h3>
            <p class="form-hint" style="margin-top:0;">
                До <?= FooterConfig::MAX_COLUMNS ?> колонок. Каждая колонка — заголовок и виджет.
                Для виджета «Текст / HTML» можно вставить произвольную разметку или сниппет
                (скрипты и небезопасные теги вырезаются).
            </p>
            <div data-repeater="footcol" class="fb-grid">
                <?php foreach ($config['columns'] as $i => $col): ?>
                    <div class="repeater-row footer-col fb-card">
                        <div class="fb-card__head">
                            <span class="fb-card__badge">Колонка</span>
                            <span class="fb-card__tools">
                                <button type="button" class="fb-move" data-fb-move="up" aria-label="Выше/левее" title="Переместить">←</button>
                                <button type="button" class="fb-move" data-fb-move="down" aria-label="Ниже/правее" title="Переместить">→</button>
                            </span>
                        </div>
                        <div class="form-field">
                            <label>Заголовок колонки</label>
                            <input type="text" name="columns[<?= $i ?>][heading]" value="<?= htmlspecialchars($col['heading'], ENT_QUOTES) ?>" placeholder="напр. Разделы">
                        </div>
                        <div class="form-field">
                            <label>Виджет</label>
                            <?= $widgetSelect('columns[' . $i . '][widget]', $col['widget']) ?>
                        </div>
                        <div class="form-field footer-col__text">
                            <label>Текст / HTML (для виджета «Текст»)</label>
                            <textarea name="columns[<?= $i ?>][text]" rows="3" placeholder="<p>Произвольный текст…</p>"><?= htmlspecialchars($col['text'], ENT_QUOTES) ?></textarea>
                        </div>
                        <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить колонку</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-repeater-template="footcol">
                <div class="fb-card__head">
                    <span class="fb-card__badge">Колонка</span>
                    <span class="fb-card__tools">
                        <button type="button" class="fb-move" data-fb-move="up" aria-label="Выше/левее" title="Переместить">←</button>
                        <button type="button" class="fb-move" data-fb-move="down" aria-label="Ниже/правее" title="Переместить">→</button>
                    </span>
                </div>
                <div class="form-field">
                    <label>Заголовок колонки</label>
                    <input type="text" name="columns[__INDEX__][heading]" placeholder="напр. Разделы">
                </div>
                <div class="form-field">
                    <label>Виджет</label>
                    <?= $widgetSelect('columns[__INDEX__][widget]', 'menu') ?>
                </div>
                <div class="form-field footer-col__text">
                    <label>Текст / HTML (для виджета «Текст»)</label>
                    <textarea name="columns[__INDEX__][text]" rows="3" placeholder="<p>Произвольный текст…</p>"></textarea>
                </div>
                <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить колонку</button>
            </template>
            <div class="repeater-actions">
                <button type="button" class="btn btn--small" data-repeater-add="footcol">+ Добавить колонку</button>
            </div>
        </div>

        <div class="header-builder__group">
            <h3>Нижняя строка</h3>
            <div class="form-field">
                <label for="bottom">Копирайт / текст</label>
                <input type="text" id="bottom" name="bottom" value="<?= htmlspecialchars($config['bottom'], ENT_QUOTES) ?>">
                <span class="form-hint">Плейсхолдеры: <code>{year}</code> — текущий год, <code>{site}</code> — название сайта.</span>
            </div>
        </div>

        <div class="form-actions form-actions--sticky">
            <button type="submit" class="btn btn--primary">Сохранить подвал</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
