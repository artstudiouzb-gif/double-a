<?php

use App\Core\Auth;
use App\Core\Csrf;

$pageTitle = 'Пользователи';
$activeNav = 'users';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
/** @var string|null $error */

$roleLabels = ['admin' => 'Супер-администратор', 'super_admin' => 'Супер-администратор', 'editor' => 'Редактор'];
?>
<p class="form-hint">Редактор управляет только контентом (страницы, новости, проекты, команда, формы, файлы). Системные разделы доступны супер-администратору.</p>

<table class="data-table" style="margin-bottom:30px;">
    <thead>
        <tr><th>Логин</th><th>Email</th><th>Роль</th><th>2FA</th><th>Последний вход</th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['username'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($item['email'], ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($roleLabels[$item['role']] ?? $item['role'], ENT_QUOTES) ?></td>
                <td><?= $item['totp_enabled'] ? '✓' : '—' ?></td>
                <td><?= htmlspecialchars((string) ($item['last_login_at'] ?? '—'), ENT_QUOTES) ?></td>
                <td class="data-table__actions">
                    <?php if ((int) $item['id'] !== Auth::id()): ?>
                        <form method="post" action="/admin/users/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить пользователя «<?= htmlspecialchars($item['username'], ENT_QUOTES) ?>»?">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                        </form>
                    <?php else: ?>
                        <span class="form-hint">это вы</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="form-card">
    <h2 style="margin-top:0;">Добавить пользователя</h2>
    <?php if (!empty($error)): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="/admin/users/create" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="username">Логин</label>
            <input type="text" id="username" name="username" required autocomplete="off">
        </div>
        <div class="form-field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-field">
            <label for="password">Пароль (минимум 10 символов)</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">
        </div>
        <div class="form-field">
            <label for="role">Роль</label>
            <select id="role" name="role">
                <option value="editor">Редактор (только контент)</option>
                <option value="admin">Супер-администратор (полный доступ)</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Создать</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
