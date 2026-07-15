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
        <?php $hasFile = false; foreach ($form['fields'] as $f) { if (($f['type'] ?? '') === 'file') { $hasFile = true; break; } } ?>
        <form method="post" action="/forms/<?= htmlspecialchars($form['slug'], ENT_QUOTES) ?>/submit" class="block-form__form"<?= $hasFile ? ' enctype="multipart/form-data"' : '' ?>>
            <?= Csrf::field() ?>
            <?= Csrf::honeypotField() ?>
            <?php foreach ($form['fields'] as $field): ?>
                <?php
                $fieldName = htmlspecialchars($field['name'] ?? '', ENT_QUOTES);
                $fieldLabel = htmlspecialchars($field['label'] ?? '', ENT_QUOTES);
                $fieldType = $field['type'] ?? 'text';
                $required = !empty($field['required']) ? 'required' : '';
                $inputId = 'field-' . $fieldName . '-' . (int) $blockId;
                // Условная логика (задача 135): поле с условием стартует скрытым,
                // JS показывает его при совпадении значения триггера.
                $cond = $field['condition'] ?? null;
                $condAttrs = '';
                $hiddenStyle = '';
                if (is_array($cond) && !empty($cond['field'])) {
                    $condAttrs = ' data-cond-field="' . htmlspecialchars((string) $cond['field'], ENT_QUOTES)
                        . '" data-cond-value="' . htmlspecialchars((string) ($cond['value'] ?? ''), ENT_QUOTES) . '"';
                    $hiddenStyle = ' style="display:none"';
                }
                ?>
                <div class="block-form__field"<?= $condAttrs ?><?= $hiddenStyle ?>>
                    <?php if ($fieldType !== 'checkbox'): ?>
                        <label for="<?= $inputId ?>"><?= $fieldLabel ?></label>
                    <?php endif; ?>

                    <?php if ($fieldType === 'textarea'): ?>
                        <textarea id="<?= $inputId ?>" name="<?= $fieldName ?>" <?= $required ?>></textarea>
                    <?php elseif ($fieldType === 'file'): ?>
                        <input type="file" id="<?= $inputId ?>" name="<?= $fieldName ?>" <?= $required ?>>
                    <?php elseif ($fieldType === 'select'): ?>
                        <?php $opts = array_map('trim', explode(',', (string) ($field['options'] ?? ''))); ?>
                        <select id="<?= $inputId ?>" name="<?= $fieldName ?>" <?= $required ?>>
                            <option value="">Выберите...</option>
                            <?php foreach ($opts as $opt): ?>
                                <?php if ($opt !== ''): ?>
                                    <option value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>"><?= htmlspecialchars($opt, ENT_QUOTES) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($fieldType === 'radio'): ?>
                        <?php $opts = array_map('trim', explode(',', (string) ($field['options'] ?? ''))); ?>
                        <div class="block-form__radio-group" style="display:flex; flex-direction:column; gap:8px; margin-top:6px;">
                            <?php foreach ($opts as $opt): ?>
                                <?php if ($opt !== ''): ?>
                                    <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-weight:normal;">
                                        <input type="radio" name="<?= $fieldName ?>" value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>" <?= $required ?>>
                                        <?= htmlspecialchars($opt, ENT_QUOTES) ?>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($fieldType === 'checkbox_group'): ?>
                        <?php $opts = array_map('trim', explode(',', (string) ($field['options'] ?? ''))); ?>
                        <div class="block-form__checkbox-group" style="display:flex; flex-direction:column; gap:8px; margin-top:6px;">
                            <?php foreach ($opts as $opt): ?>
                                <?php if ($opt !== ''): ?>
                                    <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-weight:normal;">
                                        <input type="checkbox" name="<?= $fieldName ?>[]" value="<?= htmlspecialchars($opt, ENT_QUOTES) ?>">
                                        <?= htmlspecialchars($opt, ENT_QUOTES) ?>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($fieldType === 'checkbox'): ?>
                        <div class="block-form__checkbox-single" style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                            <input type="checkbox" id="<?= $inputId ?>" name="<?= $fieldName ?>" value="1" <?= $required ?>>
                            <label for="<?= $inputId ?>" style="display:inline; cursor:pointer; font-weight:normal;"><?= $fieldLabel ?></label>
                        </div>
                    <?php elseif ($fieldType === 'date'): ?>
                        <input type="date" id="<?= $inputId ?>" name="<?= $fieldName ?>" <?= $required ?>>
                    <?php else: ?>
                        <input type="<?= htmlspecialchars($fieldType, ENT_QUOTES) ?>" id="<?= $inputId ?>" name="<?= $fieldName ?>" <?= $required ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (\App\Core\Captcha::isEnabled()): ?>
                <?= \App\Core\Captcha::field('captcha-' . (int) $blockId) ?>
            <?php endif; ?>
            <?php
            // Согласие на обработку персональных данных (глобальная настройка).
            $consentOn = \App\Models\Setting::get('form_consent_enabled', '0') === '1';
            if ($consentOn):
                $consentText = (string) \App\Models\Setting::get('form_consent_text', 'Я согласен на обработку персональных данных');
                // Ссылка на политику конфиденциальности, если задана страница.
                $ppId = (int) \App\Models\Setting::get('privacy_policy_page_id', '');
                $ppUrl = '';
                if ($ppId > 0) {
                    $pp = \App\Models\Page::findById($ppId);
                    if ($pp && ($pp['status'] ?? '') === 'published') {
                        $ppUrl = \App\Core\Locale::url($pp['slug']);
                    }
                }
                $consentId = 'consent-' . (int) $blockId;
                ?>
                <div class="block-form__consent">
                    <input type="checkbox" id="<?= $consentId ?>" name="_consent" value="1" required>
                    <label for="<?= $consentId ?>">
                        <?= htmlspecialchars($consentText, ENT_QUOTES) ?><?php if ($ppUrl !== ''): ?>
                            (<a href="<?= htmlspecialchars($ppUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener">политика конфиденциальности</a>)
                        <?php endif; ?>
                    </label>
                </div>
            <?php endif; ?>
            <button type="submit" class="block-form__submit"><?= htmlspecialchars(t('Отправить'), ENT_QUOTES) ?></button>
        </form>
    <?php endif; ?>
</div>
