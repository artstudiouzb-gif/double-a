<?php

use App\Core\Csrf;

$pageTitle = 'Хранилище: пользователи портала';
$activeNav = 'repository';
require __DIR__ . '/../layout/header.php';

/** @var array $users */
/** @var string|null $error */
?>
<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="/admin/repository" class="btn btn--small">Файлы</a>
    <a href="/admin/repository/users" class="btn btn--small btn--primary">Пользователи портала</a>
</div>
<p class="form-hint">Учётные записи для входа в файловый портал <code>/repo</code>. Это отдельные аккаунты, не связанные с пользователями админ-панели. 2FA пользователь включает самостоятельно после первого входа.</p>

<table class="data-table" style="margin-bottom:30px;">
    <thead>
        <tr><th>Логин</th><th>Имя</th><th>Email</th><th>Статус</th><th>2FA</th><th>Последний вход</th><th></th></tr>
    </thead>
    <tbody>
        <?php if (empty($users)): ?>
            <tr><td colspan="7" style="text-align:center;color:#888;padding:24px;">Пользователей пока нет.</td></tr>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) $u['username'], ENT_QUOTES) ?></strong></td>
                    <td><?= $u['full_name'] !== '' ? htmlspecialchars((string) $u['full_name'], ENT_QUOTES) : '—' ?></td>
                    <td><?= htmlspecialchars((string) $u['email'], ENT_QUOTES) ?></td>
                    <td>
                        <?php if ((int) $u['is_active'] === 1): ?>
                            <span class="badge badge--success">Активен</span>
                        <?php else: ?>
                            <span class="badge badge--muted">Отключён</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $u['totp_enabled'] === 1 ? '✓' : '—' ?></td>
                    <td class="form-hint"><?= htmlspecialchars((string) ($u['last_login_at'] ?? '—'), ENT_QUOTES) ?></td>
                    <td class="data-table__actions" style="display:flex;gap:6px;flex-wrap:wrap;">
                        <form method="post" action="/admin/repository/users/<?= (int) $u['id'] ?>/toggle">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--small"><?= (int) $u['is_active'] === 1 ? 'Отключить' : 'Включить' ?></button>
                        </form>
                        <details class="repo-reset">
                            <summary class="btn btn--small">Сбросить пароль</summary>
                            <form method="post" action="/admin/repository/users/<?= (int) $u['id'] ?>/reset-password" style="margin-top:8px;display:flex;gap:6px;">
                                <?= Csrf::field() ?>
                                <input type="password" name="password" placeholder="Новый пароль" required autocomplete="new-password">
                                <button type="submit" class="btn btn--small btn--primary">OK</button>
                            </form>
                        </details>
                        <form method="post" action="/admin/repository/users/<?= (int) $u['id'] ?>/delete" data-confirm="Удалить пользователя «<?= htmlspecialchars((string) $u['username'], ENT_QUOTES) ?>»?">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<div class="form-card">
    <h2 style="margin-top:0;">Добавить пользователя портала</h2>
    <?php if (!empty($error)): ?><div class="alert alert--error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="/admin/repository/users/create" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="username">Логин</label>
            <input type="text" id="username" name="username" required autocomplete="off">
        </div>
        <div class="form-field">
            <label for="full_name">Имя (необязательно)</label>
            <input type="text" id="full_name" name="full_name">
        </div>
        <div class="form-field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-field">
            <label for="password">Пароль (минимум 10 символов)</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">
        </div>
        <div class="form-actions"><button type="submit" class="btn btn--primary">Создать</button></div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
