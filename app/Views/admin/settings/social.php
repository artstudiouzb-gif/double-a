<?php

use App\Core\Csrf;

$pageTitle = 'Соцсети — авто-публикация';
$activeNav = 'social';
require __DIR__ . '/../layout/header.php';

/** @var array $config */
$labels = [
    'telegram' => 'Telegram-канал',
    'facebook' => 'Facebook (страница)',
    'linkedin' => 'LinkedIn (организация/профиль)',
    'instagram' => 'Instagram (Business-аккаунт)',
];
$fieldLabels = [
    'token' => 'Access Token',
    'chat_id' => 'Chat ID канала (@имя_канала или -100…)',
    'page_id' => 'ID страницы',
    'author' => 'Author URN (напр. urn:li:organization:123)',
    'user_id' => 'IG User ID',
    'signature' => 'Подпись под постом (необязательно)',
];
// Подсказки к подписи: формат у сетей разный.
$signatureHints = [
    'telegram' => 'Допустима HTML-разметка Telegram: &lt;b&gt;, &lt;i&gt;, &lt;a href="https://…"&gt;текст&lt;/a&gt;. '
        . 'Например: 🌐 &lt;a href="https://asr.artstudio.uz"&gt;Сайт&lt;/a&gt; | 📘 &lt;a href="https://facebook.com/…"&gt;Facebook&lt;/a&gt;',
    'facebook' => 'Только обычный текст — HTML не поддерживается. Ссылки пишите голыми URL, Facebook сам сделает их кликабельными.',
    'linkedin' => 'Только обычный текст — HTML не поддерживается. Ссылки пишите голыми URL, LinkedIn сам сделает их кликабельными.',
    'instagram' => 'Только обычный текст. Ссылки в подписи Instagram НЕ кликабельны — здесь имеют смысл хештеги и @упоминания.',
];
?>
<div class="form-card">
    <p class="form-hint">
        При публикации новости она автоматически ставится в очередь и
        отправляется в включённые сети CLI-воркером
        (<code>app/Console/social_worker.php</code> по Cron). Токены получаются
        в кабинетах разработчика соответствующих платформ. Instagram требует
        публичную обложку новости. Для Telegram: токен бота — у @BotFather,
        бота нужно добавить администратором канала; если у новости есть
        галерея, она уходит альбомом (до 10 фото) с подписью и ссылкой.
    </p>
    <form method="post" action="/admin/social" class="form-grid">
        <?= Csrf::field() ?>
        <?php foreach ($config as $net => $c): ?>
            <fieldset style="border:1px solid var(--admin-border);border-radius:8px;padding:16px;margin-bottom:8px;">
                <legend style="padding:0 8px;font-weight:600;">
                    <?= htmlspecialchars($labels[$net] ?? $net, ENT_QUOTES) ?>
                    <?php if ($c['enabled'] && !$c['ready']): ?>
                        <span class="badge badge--draft">не заполнено</span>
                    <?php elseif ($c['ready']): ?>
                        <span class="badge badge--published">готово</span>
                    <?php endif; ?>
                </legend>
                <div class="form-field form-field--checkbox">
                    <input type="checkbox" id="<?= $net ?>_enabled" name="<?= $net ?>[enabled]" value="1" <?= $c['enabled'] ? 'checked' : '' ?>>
                    <label for="<?= $net ?>_enabled">Публиковать новости в <?= htmlspecialchars($labels[$net] ?? $net, ENT_QUOTES) ?></label>
                </div>
                <?php foreach ($c['fields'] as $field => $value): ?>
                    <div class="form-field">
                        <label for="<?= $net ?>_<?= $field ?>"><?= htmlspecialchars($fieldLabels[$field] ?? $field, ENT_QUOTES) ?></label>
                        <?php if (in_array($field, \App\Core\SocialSettings::TEXTAREA_FIELDS, true)): ?>
                            <textarea id="<?= $net ?>_<?= $field ?>" name="<?= $net ?>[<?= $field ?>]" rows="3"
                                      style="font-family:monospace;"><?= htmlspecialchars((string) $value, ENT_QUOTES) ?></textarea>
                            <?php if (!empty($signatureHints[$net])): ?>
                                <span class="form-hint"><?= $signatureHints[$net] ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <input type="<?= $field === 'token' ? 'password' : 'text' ?>" id="<?= $net ?>_<?= $field ?>"
                                   name="<?= $net ?>[<?= $field ?>]" value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>"
                                   autocomplete="off">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
        </div>
    </form>
</div>

<?php /** @var array $failedPosts */ $failedPosts = $failedPosts ?? []; ?>
<div class="form-card" style="margin-top:24px;">
    <h2 style="margin-top:0;">Проблемные публикации (dead-letter)</h2>
    <p class="form-hint">Публикации, не отправленные после всех попыток. О переходе в этот статус приходит алерт в Telegram.</p>
    <table class="data-table">
        <thead><tr><th>Новость</th><th>Сеть</th><th>Попыток</th><th>Ошибка</th></tr></thead>
        <tbody>
            <?php if (empty($failedPosts)): ?>
                <tr><td colspan="4" class="data-table__empty">Проблемных публикаций нет.</td></tr>
            <?php endif; ?>
            <?php foreach ($failedPosts as $fp): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($fp['news_title'] ?? ('#' . (int) $fp['news_id'])), ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) $fp['network'], ENT_QUOTES) ?></td>
                    <td><?= (int) ($fp['attempts'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($fp['last_error'] ?? ''), ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
