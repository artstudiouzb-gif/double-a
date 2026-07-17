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

<?php
/** @var array<int, array<string, mixed>> $queueLog */
/** @var array{pending:int,sent:int,failed:int} $queueCounts */
/** @var array{last:?int,age:?int,stale:bool,expected:int}|null $workerStatus */
$queueLog = $queueLog ?? [];
$queueCounts = $queueCounts ?? ['pending' => 0, 'sent' => 0, 'failed' => 0];
$workerStatus = $workerStatus ?? null;

$fmtAge = static function (?int $sec): string {
    if ($sec === null) {
        return '';
    }
    if ($sec < 60) {
        return $sec . ' с назад';
    }
    if ($sec < 3600) {
        return intdiv($sec, 60) . ' мин назад';
    }
    if ($sec < 86400) {
        return intdiv($sec, 3600) . ' ч назад';
    }

    return intdiv($sec, 86400) . ' дн назад';
};
$statusMap = [
    'sent' => ['success', 'отправлено'],
    'pending' => ['draft', 'в очереди'],
    'failed' => ['danger', 'ошибка'],
];
$appRoot = defined('APP_ROOT') ? APP_ROOT : '/path/to/site';
$cronBroken = $workerStatus !== null && ($workerStatus['last'] === null || !empty($workerStatus['stale']));
?>
<div class="form-card" style="margin-top:24px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
        <div>
            <h2 style="margin-top:0;">Очередь публикаций и воркер</h2>
            <p class="form-hint" style="margin:0;">
                Публикации отправляет фоновый воркер по Cron (каждые ~5 минут). Здесь
                видно, что он делает; кнопкой можно обработать очередь вручную,
                не дожидаясь расписания.
            </p>
        </div>
        <form method="post" action="/admin/social/run"
              data-confirm="Отправить сейчас всё, что находится в очереди?">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn--primary">Запустить отправку сейчас</button>
        </form>
    </div>

    <div style="display:flex;gap:28px;flex-wrap:wrap;margin:18px 0 4px;">
        <div>
            <div class="form-hint" style="margin-bottom:4px;">Последний запуск воркера</div>
            <?php if ($workerStatus === null || $workerStatus['last'] === null): ?>
                <span class="badge badge--draft">ни разу не запускался</span>
            <?php elseif (!empty($workerStatus['stale'])): ?>
                <span class="badge badge--danger">молчит</span>
                <span style="color:var(--admin-muted);"><?= htmlspecialchars(date('d.m.Y H:i', (int) $workerStatus['last']), ENT_QUOTES) ?> · <?= $fmtAge($workerStatus['age']) ?></span>
            <?php else: ?>
                <span class="badge badge--success">активен</span>
                <span style="color:var(--admin-muted);"><?= htmlspecialchars(date('d.m.Y H:i', (int) $workerStatus['last']), ENT_QUOTES) ?> · <?= $fmtAge($workerStatus['age']) ?></span>
            <?php endif; ?>
        </div>
        <div>
            <div class="form-hint" style="margin-bottom:4px;">Очередь</div>
            <span class="badge badge--draft">в очереди: <?= (int) $queueCounts['pending'] ?></span>
            <span class="badge badge--success">отправлено: <?= (int) $queueCounts['sent'] ?></span>
            <span class="badge badge--danger">ошибок: <?= (int) $queueCounts['failed'] ?></span>
        </div>
    </div>

    <?php if ($cronBroken): ?>
        <p class="form-hint" style="background:var(--admin-danger-soft);border:1px solid var(--admin-danger-border);border-radius:var(--admin-radius);padding:10px 12px;margin-top:12px;">
            Похоже, Cron не настроен или воркер остановлен. Добавьте задание на хостинге (каждые 5 минут):<br>
            <code>*/5 * * * * php <?= htmlspecialchars($appRoot, ENT_QUOTES) ?>/app/Console/social_worker.php &gt;&gt; <?= htmlspecialchars($appRoot, ENT_QUOTES) ?>/storage/logs/social_worker.log 2&gt;&amp;1</code><br>
            Пока Cron нет — публикуйте и жмите «Запустить отправку сейчас».
        </p>
    <?php endif; ?>

    <h3 style="margin:20px 0 8px;">Журнал очереди</h3>
    <table class="data-table">
        <thead><tr><th>Новость</th><th>Сеть</th><th>Статус</th><th>Попыток</th><th>Обновлено</th><th>Ошибка</th></tr></thead>
        <tbody>
            <?php if (empty($queueLog)): ?>
                <tr><td colspan="6" class="data-table__empty">Публикаций пока не было.</td></tr>
            <?php endif; ?>
            <?php foreach ($queueLog as $row): ?>
                <?php
                $st = (string) ($row['status'] ?? '');
                [$cls, $label] = $statusMap[$st] ?? ['draft', $st];
                $when = $row['sent_at'] ?? $row['created_at'] ?? null;
                ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($row['news_title'] ?? ('#' . (int) $row['news_id'])), ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars((string) $row['network'], ENT_QUOTES) ?></td>
                    <td><span class="badge badge--<?= $cls ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></span></td>
                    <td><?= (int) ($row['attempts'] ?? 0) ?></td>
                    <td style="white-space:nowrap;color:var(--admin-muted);"><?= $when ? htmlspecialchars((string) $when, ENT_QUOTES) : '—' ?></td>
                    <td style="color:var(--admin-danger);"><?= htmlspecialchars((string) ($row['last_error'] ?? ''), ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
