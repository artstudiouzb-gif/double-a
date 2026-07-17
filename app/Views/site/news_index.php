<?php

use App\Core\AssetCollector;
use App\Core\DateFormatter;
use App\Core\Locale;
use App\Models\News;

/** @var array $items */
/** @var int $page */
/** @var int $pages */
/** @var list<string> $badges */
/** @var string $badge */
$page = $page ?? 1;
$pages = $pages ?? 1;
$badges = $badges ?? [];
$badge = $badge ?? '';

$metaTitle = 'Новости';
$metaDescription = 'Официальные новости и аналитические материалы Агентства.';
AssetCollector::requireJs('news');
require __DIR__ . '/_header.php';

$crumbs = [
    ['label' => t('Главная'), 'url' => Locale::url('/')],
    ['label' => t('Новости')],
];
require __DIR__ . '/_crumbs.php';

$lang = Locale::current();
$fmt = static fn (string $d): string => DateFormatter::long($d, $lang);
// Крупная первая новость — только на первой странице общего списка.
$featured = ($page === 1 && $badge === '' && !empty($items)) ? $items[0] : null;
$grid = $featured !== null ? array_slice($items, 1) : $items;
$pageUrl = static fn (int $p): string => Locale::url('news')
    . (($p > 1 || $badge !== '') ? '?' . http_build_query(array_filter(['badge' => $badge, 'page' => $p > 1 ? $p : null])) : '');
?>
<div class="listing">
    <div class="listing__head">
        <h1 class="listing__title"><?= htmlspecialchars(t('Новости и аналитика'), ENT_QUOTES) ?></h1>
        <p class="listing__lead"><?= htmlspecialchars(t('Официальные сообщения, события и аналитические материалы Агентства.'), ENT_QUOTES) ?></p>
    </div>

    <?php if ($badges !== []): ?>
        <nav class="listing-filter" aria-label="<?= htmlspecialchars(t('Рубрики'), ENT_QUOTES) ?>">
            <a class="listing-filter__item<?= $badge === '' ? ' is-active' : '' ?>" href="<?= htmlspecialchars(Locale::url('news'), ENT_QUOTES) ?>"><?= htmlspecialchars(t('Все материалы'), ENT_QUOTES) ?></a>
            <?php foreach ($badges as $b): ?>
                <a class="listing-filter__item<?= $b === $badge ? ' is-active' : '' ?>" href="<?= htmlspecialchars(Locale::url('news') . '?badge=' . rawurlencode($b), ENT_QUOTES) ?>"><?= htmlspecialchars($b, ENT_QUOTES) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <p class="listing__empty"><?= htmlspecialchars(t('Пока нет опубликованных новостей.'), ENT_QUOTES) ?></p>
    <?php else: ?>
        <?php if ($featured !== null): ?>
            <?php $fc = News::getCoverImage($featured); ?>
            <a class="newslist-lead" href="<?= htmlspecialchars(Locale::url('news/' . $featured['slug']), ENT_QUOTES) ?>">
                <?php if ($fc !== null): ?>
                    <img class="newslist-lead__media" src="<?= htmlspecialchars($fc, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $featured['title'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                    <span class="newslist-lead__media newslist-lead__media--empty" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="newslist-lead__body">
                    <?php if (!empty($featured['badge'])): ?><span class="newsdetail__badge"><?= htmlspecialchars((string) $featured['badge'], ENT_QUOTES) ?></span><?php endif; ?>
                    <?php if (!empty($featured['published_at'])): ?><time class="newslist__date"><?= htmlspecialchars($fmt((string) $featured['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                    <span class="newslist-lead__title"><?= htmlspecialchars((string) $featured['title'], ENT_QUOTES) ?></span>
                    <?php if (!empty($featured['excerpt'])): ?><span class="newslist-lead__excerpt"><?= htmlspecialchars(mb_substr(strip_tags((string) $featured['excerpt']), 0, 200), ENT_QUOTES) ?></span><?php endif; ?>
                </span>
            </a>
        <?php endif; ?>

        <div class="newslist-grid">
            <?php foreach ($grid as $item): ?>
                <?php $c = News::getCoverImage($item); ?>
                <a class="relnews-card" href="<?= htmlspecialchars(Locale::url('news/' . $item['slug']), ENT_QUOTES) ?>">
                    <?php if ($c !== null): ?>
                        <img class="relnews-card__media" src="<?= htmlspecialchars($c, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="relnews-card__media relnews-card__media--empty" aria-hidden="true"></span>
                    <?php endif; ?>
                    <?php if (!empty($item['published_at'])): ?><time class="relnews-card__date"><?= htmlspecialchars($fmt((string) $item['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                    <span class="relnews-card__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                    <?php if (!empty($item['excerpt'])): ?><span class="relnews-card__excerpt"><?= htmlspecialchars(mb_substr(strip_tags((string) $item['excerpt']), 0, 110), ENT_QUOTES) ?></span><?php endif; ?>
                    <span class="relnews-card__arrow">→</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="listing-pager" aria-label="<?= htmlspecialchars(t('Страницы'), ENT_QUOTES) ?>">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="listing-pager__item is-active" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a class="listing-pager__item" href="<?= htmlspecialchars($pageUrl($i), ENT_QUOTES) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
