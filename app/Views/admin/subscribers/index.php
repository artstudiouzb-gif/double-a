<?php

use App\Core\Csrf;

$pageTitle = 'Подписчики дайджеста';
$activeNav = 'subscribers';
require __DIR__ . '/../layout/header.php';

/** @var array $items */
/** @var int $total */
?>
<p class="form-hint">Адреса собираются блоком «Подписка на дайджест» на сайте. Раз в неделю <code>digest_worker</code> (cron) отправляет им список новостей за 7 дней; в каждом письме есть персональная ссылка отписки.</p>

<p class="form-hint">Всего подписчиков: <strong><?= (int) $total ?></strong></p>

<?php if (empty($items)): ?>
    <p class="form-hint">Подписчиков пока нет. Добавьте блок «Подписка на дайджест» на любую страницу.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
            <tr><th>Email</th><th>Подписан</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $item['email'], ENT_QUOTES) ?></td>
                    <td style="white-space:nowrap;"><?= htmlspecialchars((string) $item['created_at'], ENT_QUOTES) ?></td>
                    <td class="data-table__actions">
                        <form method="post" action="/admin/subscribers/<?= (int) $item['id'] ?>/delete" data-confirm="Удалить подписчика «<?= htmlspecialchars((string) $item['email'], ENT_QUOTES) ?>»?">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn--small btn--danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
