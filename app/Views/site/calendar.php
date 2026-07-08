<?php

use App\Core\CalendarGrid;
use App\Core\Locale;

/** @var array $type */
/** @var array $weeks */
/** @var array $byDate */
/** @var string $label */
/** @var string $prevMonth */
/** @var string $nextMonth */
/** @var string $today */

$metaTitle = 'Календарь мероприятий';
$metaDescription = (string) ($type['description'] ?? '');
require __DIR__ . '/_header.php';

$calUrl = Locale::url('calendar');
$entryUrl = static fn (array $e): string => Locale::url('catalog/' . $type['slug'] . '/' . $e['slug']);
?>
<div class="content-list">
    <nav class="content-crumbs" aria-label="Хлебные крошки">
        <a href="<?= htmlspecialchars(Locale::url('/'), ENT_QUOTES) ?>">Главная</a>
        <span>/</span>
        <span>Календарь мероприятий</span>
    </nav>

    <header class="content-list__head">
        <h1>Календарь мероприятий</h1>
        <?php if (!empty($type['description'])): ?>
            <p class="content-list__lead"><?= htmlspecialchars((string) $type['description'], ENT_QUOTES) ?></p>
        <?php endif; ?>
    </header>

    <div class="calendar__nav">
        <a class="btn btn--small" href="<?= htmlspecialchars($calUrl . '?m=' . $prevMonth, ENT_QUOTES) ?>">← Предыдущий</a>
        <strong class="calendar__label"><?= htmlspecialchars($label, ENT_QUOTES) ?></strong>
        <a class="btn btn--small" href="<?= htmlspecialchars($calUrl . '?m=' . $nextMonth, ENT_QUOTES) ?>">Следующий →</a>
    </div>

    <div class="calendar__scroll">
        <table class="calendar" aria-label="Календарь на <?= htmlspecialchars($label, ENT_QUOTES) ?>">
            <thead>
                <tr>
                    <?php foreach (CalendarGrid::WEEKDAYS as $wd): ?>
                        <th scope="col"><?= $wd ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($weeks as $week): ?>
                    <tr>
                        <?php foreach ($week as $cell): ?>
                            <?php if ($cell['day'] === null): ?>
                                <td class="calendar__cell calendar__cell--empty"></td>
                            <?php else: ?>
                                <?php $events = $byDate[$cell['date']] ?? []; ?>
                                <td class="calendar__cell<?= $cell['date'] === $today ? ' calendar__cell--today' : '' ?><?= $events !== [] ? ' calendar__cell--has-events' : '' ?>">
                                    <span class="calendar__day"><?= (int) $cell['day'] ?></span>
                                    <?php foreach ($events as $event): ?>
                                        <a class="calendar__event" href="<?= htmlspecialchars($entryUrl($event), ENT_QUOTES) ?>" title="<?= htmlspecialchars((string) $event['title'], ENT_QUOTES) ?>">
                                            <?= htmlspecialchars(mb_strimwidth((string) $event['title'], 0, 40, '…'), ENT_QUOTES) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($byDate !== []): ?>
        <section class="calendar__list">
            <h2>Мероприятия месяца</h2>
            <ul>
                <?php foreach ($byDate as $date => $events): ?>
                    <?php foreach ($events as $event): ?>
                        <li>
                            <time datetime="<?= htmlspecialchars((string) $date, ENT_QUOTES) ?>"><?= htmlspecialchars(date('d.m.Y', (int) strtotime((string) $date)), ENT_QUOTES) ?></time>
                            <?php if (!empty($event['data']['event_time'])): ?>
                                <span class="calendar__time"><?= htmlspecialchars((string) $event['data']['event_time'], ENT_QUOTES) ?></span>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($entryUrl($event), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $event['title'], ENT_QUOTES) ?></a>
                            <?php if (!empty($event['data']['location'])): ?>
                                <span class="calendar__location">— <?= htmlspecialchars((string) $event['data']['location'], ENT_QUOTES) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php else: ?>
        <p class="content-list__empty">В этом месяце мероприятий нет.</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
