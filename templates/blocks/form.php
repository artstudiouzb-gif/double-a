<?php

use App\Core\Csrf;

/** @var array $data */
/** @var int $blockId */
$form = $data['form'] ?? null;
?>
<div class="block-form">
    <?php if ($form === null): ?>
        <p class="block-form__missing">Форма не найдена или ещё не выбрана в настройках блока.</p>
    <?php else: ?>
        <?php if (!empty($form['name'])): ?><h2><?= htmlspecialchars($form['name'], ENT_QUOTES) ?></h2><?php endif; ?>
        <form method="post" action="/forms/<?= htmlspecialchars($form['slug'], ENT_QUOTES) ?>/submit" class="block-form__form">
            <?= Csrf::field() ?>
            <?php foreach ($form['fields'] as $field): ?>
                <?php
                $fieldName = htmlspecialchars($field['name'] ?? '', ENT_QUOTES);
                $fieldLabel = htmlspecialchars($field['label'] ?? '', ENT_QUOTES);
                $fieldType = $field['type'] ?? 'text';
                $required = !empty($field['required']) ? 'required' : '';
                $inputId = 'field-' . $fieldName . '-' . (int) $blockId;
                ?>
                <div class="block-form__field">
                    <label for="<?= $inputId ?>"><?= $fieldLabel ?></label>
                    <?php if ($fieldType === 'textarea'): ?>
                        <textarea id="<?= $inputId ?>" name="<?= $fieldName ?>" <?= $required ?>></textarea>
                    <?php else: ?>
                        <input type="<?= htmlspecialchars($fieldType, ENT_QUOTES) ?>" id="<?= $inputId ?>" name="<?= $fieldName ?>" <?= $required ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="block-form__submit">Отправить</button>
        </form>
    <?php endif; ?>
</div>
