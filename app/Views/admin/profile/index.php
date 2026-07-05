<?php

use App\Core\Csrf;
use App\Models\SessionRegistry;

$pageTitle = 'Профиль и безопасность';
$activeNav = 'profile';
require __DIR__ . '/../layout/header.php';

/** @var array $sessions */
/** @var string $currentHash */
/** @var int $backupRemaining */
/** @var array<int,string>|null $freshCodes */

function ua_short(?string $ua): string
{
    $ua = (string) $ua;
    $browser = 'Браузер';
    if (stripos($ua, 'Firefox') !== false) { $browser = 'Firefox'; }
    elseif (stripos($ua, 'Edg') !== false) { $browser = 'Edge'; }
    elseif (stripos($ua, 'Chrome') !== false) { $browser = 'Chrome'; }
    elseif (stripos($ua, 'Safari') !== false) { $browser = 'Safari'; }
    $os = '';
    if (stripos($ua, 'Windows') !== false) { $os = 'Windows'; }
    elseif (stripos($ua, 'Android') !== false) { $os = 'Android'; }
    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) { $os = 'iOS'; }
    elseif (stripos($ua, 'Mac') !== false) { $os = 'macOS'; }
    elseif (stripos($ua, 'Linux') !== false) { $os = 'Linux'; }
    return trim($browser . ($os !== '' ? ' · ' . $os : ''));
}
?>

<?php if (!empty($freshCodes)): ?>
<div class="form-card" style="border:2px solid var(--color-accent,#e63946); margin-bottom:24px;">
    <h2 style="margin-top:0;">Ваши резервные коды 2FA</h2>
    <p class="form-hint">Сохраните эти коды в надёжном месте. Каждый код работает <strong>один раз</strong>
       и заменяет код из приложения-аутентификатора, если вы потеряете доступ к нему.
       Коды показываются <strong>только сейчас</strong>.</p>
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; font-family:monospace; font-size:16px; margin-top:12px;">
        <?php foreach ($freshCodes as $c): ?>
            <div style="padding:10px 14px; background:var(--bg-surface,#f4f5f7); border-radius:8px; text-align:center; letter-spacing:1px;"><?= htmlspecialchars($c, ENT_QUOTES) ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="form-card">
    <h2 style="margin-top:0;">Смена пароля</h2>
    <form method="post" action="/admin/profile/password" class="form-grid" style="max-width:480px;">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="current_password">Текущий пароль</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
        </div>
        <div class="form-field">
            <label for="new_password">Новый пароль</label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
            <span class="form-hint">Минимум 10 символов, минимум две группы символов, не из списка популярных паролей.</span>
        </div>
        <div class="form-field">
            <label for="new_password_confirm">Повторите новый пароль</label>
            <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Изменить пароль</button>
        </div>
    </form>
</div>

<div class="form-card" style="margin-top:24px;">
    <h2 style="margin-top:0;">Резервные коды 2FA</h2>
    <p class="form-hint">Осталось неиспользованных кодов: <strong><?= (int) $backupRemaining ?></strong>.
       Перевыпуск полностью заменит старый набор — прежние коды перестанут работать.</p>
    <form method="post" action="/admin/profile/backup-codes" class="form-grid" style="max-width:480px;">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="bc_password">Подтвердите паролем</label>
            <input type="password" id="bc_password" name="password" autocomplete="current-password" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Перевыпустить коды</button>
        </div>
    </form>
</div>

<div class="form-card" style="margin-top:24px;">
    <h2 style="margin-top:0;">Активные сессии</h2>
    <p class="form-hint">Список устройств, где выполнен вход. Отзыв сессии мгновенно завершает её на сервере.</p>
    <table class="data-table">
        <thead>
            <tr><th>Устройство</th><th>IP</th><th>Вход</th><th>Активность</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($sessions as $s): ?>
            <?php $isCurrent = hash_equals((string) $currentHash, (string) $s['sid_hash']); ?>
            <tr>
                <td><?= htmlspecialchars(ua_short($s['user_agent'] ?? ''), ENT_QUOTES) ?>
                    <?php if ($isCurrent): ?><span class="badge">текущая</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) ($s['ip_address'] ?? '—'), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string) $s['created_at'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string) $s['last_seen_at'], ENT_QUOTES) ?></td>
                <td>
                    <?php if (!$isCurrent): ?>
                    <form method="post" action="/admin/profile/sessions/<?= (int) $s['id'] ?>/revoke">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn--small">Отозвать</button>
                    </form>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form method="post" action="/admin/profile/sessions/revoke-others" style="margin-top:16px;">
        <?= Csrf::field() ?>
        <button type="submit" class="btn">Выйти на всех других устройствах</button>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
