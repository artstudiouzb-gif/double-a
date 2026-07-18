<?php

use App\Core\Csrf;

$pageTitle = 'Telegram';
$activeNav = 'telegram';
require __DIR__ . '/../layout/header.php';

/** @var string $botToken */
/** @var string $botUsername */
/** @var bool $botOk */
/** @var bool $linked */
/** @var int $myChatId */
/** @var string|null $linkCode */
/** @var array<string,string> $channel */
/** @var string $channelOwnToken */
/** @var bool $channelEnabled */
/** @var string $notifyChatIds */
/** @var string $gatewayToken */

// Значок шага: пройден / требует внимания / ещё не начат.
$mark = static function (bool $done, bool $started = true): string {
    if (!$started) {
        return '<span class="badge badge--draft">не настроено</span>';
    }

    return $done
        ? '<span class="badge badge--published">готово</span>'
        : '<span class="badge badge--danger">требует внимания</span>';
};
?>
<div class="form-card">
    <p class="form-hint">
        Все настройки Telegram собраны здесь и идут в порядке подключения: сначала бот,
        затем привязка вашего аккаунта для кодов входа, затем канал для публикации новостей.
        Один бот справляется со всем — отдельный бот для публикаций нужен, только если
        вы намеренно хотите разделить доступы.
    </p>
</div>

<?php // ── Шаг 1. Бот ───────────────────────────────────────────────────── ?>
<div class="form-card" style="margin-top:16px;">
    <h2 style="margin-top:0;">1. Бот <?= $mark($botOk, $botToken !== '') ?></h2>
    <p class="form-hint">
        Создайте бота у <strong>@BotFather</strong> (команда <code>/newbot</code>) и вставьте токен.
        Готовый токен всегда можно посмотреть там же: <code>/mybots</code> → ваш бот → <em>API Token</em>.
    </p>
    <form method="post" action="/admin/telegram/bot" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="telegram_bot_token">Токен бота</label>
            <input type="text" id="telegram_bot_token" name="telegram_bot_token"
                   value="<?= htmlspecialchars($botToken, ENT_QUOTES) ?>"
                   placeholder="1234567890:AAH…" autocomplete="off" spellcheck="false">
            <span class="form-hint">
                Формат: цифры, двоеточие, ключ. Без слова «bot» в начале и без пробелов.
                Этот же токен используется и для кодов входа, и для публикации в канал.
            </span>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= \App\Core\AdminUi::icon('save') ?>Сохранить токен</button>
        </div>
    </form>
    <?php if ($botToken !== ''): ?>
        <form method="post" action="/admin/telegram/bot/check" style="margin-top:8px;">
            <?= Csrf::field() ?>
            <button type="submit" class="btn">Проверить бота</button>
            <?php if ($botOk && $botUsername !== ''): ?>
                <span class="form-hint" style="display:inline-block;margin-left:8px;">
                    Сейчас подключён: <strong>@<?= htmlspecialchars($botUsername, ENT_QUOTES) ?></strong>
                </span>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<?php // ── Шаг 2. Привязка администратора ───────────────────────────────── ?>
<div class="form-card" style="margin-top:16px;">
    <h2 style="margin-top:0;">2. Коды входа в панель <?= $mark($linked, $botToken !== '') ?></h2>
    <?php if ($botToken === ''): ?>
        <p class="form-hint">Сначала сохраните токен бота в шаге 1.</p>
    <?php elseif ($linked): ?>
        <p class="form-hint">
            Ваш аккаунт привязан, коды входа приходят от бота.
            Ваш <code>chat_id</code>: <strong><?= (int) $myChatId ?></strong> — он пригодится
            для уведомлений о заявках ниже. Отвязать можно в «Профиле» (потребуется пароль).
        </p>
    <?php else: ?>
        <ol class="form-hint" style="margin:0 0 12px 18px;padding:0;">
            <li>Откройте бота
                <?php if ($botUsername !== ''): ?>
                    <a href="https://t.me/<?= htmlspecialchars($botUsername, ENT_QUOTES) ?>" target="_blank" rel="noopener">@<?= htmlspecialchars($botUsername, ENT_QUOTES) ?></a>
                <?php else: ?>
                    в Telegram
                <?php endif; ?>
                и нажмите «Start».
            </li>
            <li>Отправьте ему код: <code style="font-size:1.1em;"><?= htmlspecialchars((string) $linkCode, ENT_QUOTES) ?></code></li>
            <li>Вернитесь сюда и нажмите «Проверить привязку».</li>
        </ol>
        <form method="post" action="/admin/telegram/link">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn--primary">Проверить привязку</button>
        </form>
    <?php endif; ?>
</div>

<?php // ── Шаг 3. Канал ─────────────────────────────────────────────────── ?>
<?php $channelReady = $channelEnabled && trim((string) ($channel['chat_id'] ?? '')) !== ''; ?>
<div class="form-card" style="margin-top:16px;">
    <h2 style="margin-top:0;">3. Публикация новостей в канал <?= $mark($channelReady, $channelEnabled) ?></h2>
    <p class="form-hint">
        Бот должен быть <strong>администратором канала</strong> с правом «Публикация сообщений» —
        для кодов входа это не требовалось, поэтому шаг легко пропустить.
        Посты в канале выглядят от имени канала, имя бота подписчики не видят.
    </p>
    <form method="post" action="/admin/telegram/channel" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="tg_enabled" name="enabled" value="1" <?= $channelEnabled ? 'checked' : '' ?>>
            <label for="tg_enabled">Публиковать новости в канал</label>
        </div>
        <div class="form-field">
            <label for="tg_chat_id">Канал</label>
            <input type="text" id="tg_chat_id" name="chat_id"
                   value="<?= htmlspecialchars((string) ($channel['chat_id'] ?? ''), ENT_QUOTES) ?>"
                   placeholder="@имя_канала" autocomplete="off" spellcheck="false">
            <span class="form-hint">
                Публичный канал — <code>@имя_канала</code>, приватный — числовой <code>-100…</code>.
            </span>
        </div>
        <div class="form-field">
            <label for="tg_signature">Подпись под постом (необязательно)</label>
            <textarea id="tg_signature" name="signature" rows="3" style="font-family:monospace;"><?= htmlspecialchars((string) ($channel['signature'] ?? ''), ENT_QUOTES) ?></textarea>
            <span class="form-hint">
                Допустима HTML-разметка Telegram: &lt;b&gt;, &lt;i&gt;, &lt;a href="https://…"&gt;текст&lt;/a&gt;.
            </span>
        </div>
        <details class="form-section">
            <summary>Отдельный бот для публикаций <span class="form-section__hint">не обязательно</span></summary>
            <div class="form-section__body">
                <div class="form-field">
                    <label for="tg_own_token">Токен бота-публикатора</label>
                    <input type="text" id="tg_own_token" name="own_token"
                           value="<?= htmlspecialchars($channelOwnToken, ENT_QUOTES) ?>"
                           placeholder="пусто — использовать бота из шага 1" autocomplete="off" spellcheck="false">
                    <span class="form-hint">
                        Пусто — публикация идёт основным ботом. Отдельный токен имеет смысл,
                        только если вы хотите развести доступы: утечка ключа публикации тогда
                        не даёт доступа к кодам входа.
                    </span>
                </div>
            </div>
        </details>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= \App\Core\AdminUi::icon('save') ?>Сохранить канал</button>
        </div>
    </form>
    <form method="post" action="/admin/telegram/channel/check" style="margin-top:8px;">
        <?= Csrf::field() ?>
        <button type="submit" class="btn">Проверить канал и права бота</button>
        <span class="form-hint" style="display:block;margin-top:6px;">
            Проверяет по шагам токен, канал и права бота в нём. Ничего не публикует —
            проверяются сохранённые значения, поэтому сначала сохраните.
        </span>
    </form>
</div>

<?php // ── Шаг 4. Дополнительно ─────────────────────────────────────────── ?>
<div class="form-card" style="margin-top:16px;">
    <h2 style="margin-top:0;">4. Дополнительно</h2>
    <form method="post" action="/admin/telegram/extras" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="telegram_notify_chat_ids">Уведомления о заявках форм: chat_id получателей</label>
            <input type="text" id="telegram_notify_chat_ids" name="telegram_notify_chat_ids"
                   value="<?= htmlspecialchars($notifyChatIds, ENT_QUOTES) ?>"
                   placeholder="123456789, -1001234567890" autocomplete="off" spellcheck="false">
            <span class="form-hint">
                Каждая заявка с форм сайта приходит сообщением от бота на эти chat_id (через запятую;
                отрицательный id — групповой чат, куда добавлен бот).
                <?php if ($linked): ?>Ваш собственный chat_id: <strong><?= (int) $myChatId ?></strong>.<?php endif; ?>
                Пусто — уведомления выключены.
            </span>
        </div>
        <div class="form-field">
            <label for="telegram_gateway_token">Токен Telegram Gateway API (платный, резервный)</label>
            <input type="text" id="telegram_gateway_token" name="telegram_gateway_token"
                   value="<?= htmlspecialchars($gatewayToken, ENT_QUOTES) ?>" autocomplete="off" spellcheck="false">
            <span class="form-hint">
                Это <strong>другой сервис</strong> (<code>gateway.telegram.org</code>), не Bot API:
                коды входа приходят на телефон от канала Verification Codes, если у администратора
                заполнен телефон. Нужен только как резерв к боту; для публикации не используется.
            </span>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary"><?= \App\Core\AdminUi::icon('save') ?>Сохранить</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
