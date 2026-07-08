<?php

use App\Core\Csrf;
use App\Models\SessionRegistry;

$pageTitle = 'Профиль и безопасность';
$activeNav = 'profile';
require __DIR__ . '/../layout/header.php';

/** @var array $sessions */
/** @var string $currentHash */
/** @var array|null $profileUser */

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
    <h2 style="margin-top:0;">Код входа через Telegram-бота (бесплатно)</h2>
    <?php if (!($botConfigured ?? false)): ?>
        <p class="form-hint">Бот не настроен. Супер-администратор может создать бота у @BotFather (бесплатно) и указать токен в «Настройках».</p>
    <?php elseif ($botLinked ?? false): ?>
        <p><span class="badge badge--success">Привязан</span> Коды входа приходят вам в Telegram от бота.</p>
        <form method="post" action="/admin/profile/telegram/unlink" class="form-grid" style="max-width:480px;">
            <?= Csrf::field() ?>
            <div class="form-field">
                <label for="tg_password">Подтвердите паролем, чтобы отвязать</label>
                <input type="password" id="tg_password" name="password" autocomplete="current-password" required>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn--danger">Отвязать Telegram</button></div>
        </form>
    <?php else: ?>
        <p class="form-hint">Привяжите свой Telegram — и коды входа будут приходить от бота бесплатно (без QR-кодов и секретов на экране):</p>
        <ol style="margin:0 0 14px 18px;line-height:1.8;">
            <li>Откройте бота<?php if (!empty($botUsername)): ?> <a href="https://t.me/<?= htmlspecialchars($botUsername, ENT_QUOTES) ?>" target="_blank" rel="noopener">@<?= htmlspecialchars($botUsername, ENT_QUOTES) ?></a><?php endif; ?> и нажмите <strong>Start</strong>.</li>
            <li>Отправьте боту код: <code style="font-size:15px;padding:3px 8px;background:var(--admin-accent-soft,#eef0fe);border-radius:6px;"><?= htmlspecialchars((string) ($linkCode ?? ''), ENT_QUOTES) ?></code></li>
            <li>Нажмите кнопку ниже.</li>
        </ol>
        <form method="post" action="/admin/profile/telegram/link">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn--primary">Проверить привязку</button>
        </form>
    <?php endif; ?>
</div>

<div class="form-card" style="margin-top:24px;">
    <h2 style="margin-top:0;">Резервный канал: телефон (платный шлюз)</h2>
    <p class="form-hint">Код входа приходит в Telegram от официального канала
       <strong>Verification&nbsp;Codes</strong> (t.me/VerificationCodes) на номер, привязанный к вашему
       Telegram-аккаунту. Без телефона вход выполняется только по паролю.</p>
    <form method="post" action="/admin/profile/phone" class="form-grid" style="max-width:480px;">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="phone">Телефон (международный формат)</label>
            <input type="tel" id="phone" name="phone" placeholder="+998901234567" value="<?= htmlspecialchars((string) ($profileUser['phone'] ?? ''), ENT_QUOTES) ?>" autocomplete="tel">
            <span class="form-hint">Оставьте пустым, чтобы отключить код подтверждения для своего аккаунта.</span>
        </div>
        <div class="form-field">
            <label for="ph_password">Подтвердите паролем</label>
            <input type="password" id="ph_password" name="password" autocomplete="current-password" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Сохранить телефон</button>
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
