<?php

use App\Core\AssetCollector;
use App\Core\DateFormatter;
use App\Core\Locale;
use App\Models\News;

/** @var array $items */
/** @var int $page */
/** @var int $pages */
$page = $page ?? 1;
$pages = $pages ?? 1;

$metaTitle = 'Новости';
$metaDescription = 'Официальные новости и аналитические материалы Агентства.';
AssetCollector::requireJs('news');
require __DIR__ . '/_header.php';

$crumbs = [
    ['label' => 'Главная', 'url' => Locale::url('/')],
    ['label' => 'Новости'],
];
require __DIR__ . '/_crumbs.php';

$lang = Locale::current();
$fmt = static fn (string $d): string => DateFormatter::long($d, $lang);
// На первой странице первая новость — крупная.
$featured = ($page === 1 && !empty($items)) ? $items[0] : null;
$grid = $featured !== null ? array_slice($items, 1) : $items;
?>
<div class="listing">
    <div class="listing__head">
        <h1 class="listing__title">Новости и аналитика</h1>
        <p class="listing__lead">Официальные сообщения, события и аналитические материалы Агентства.</p>
    </div>

    <?php if (empty($items)): ?>
        <p class="listing__empty">Пока нет опубликованных новостей.</p>
    <?php else: ?>
        <?php if ($featured !== null): ?>
            <?php $fc = News::getCoverImage($featured); ?>
            <a class="newslist-lead" href="<?= htmlspecialchars(Locale::url('news/' . $featured['slug']), ENT_QUOTES) ?>">
                <span class="newslist-lead__media<?= $fc === null ? ' newslist-lead__media--empty' : '' ?>"<?= $fc !== null ? ' style="background-image:url(\'' . htmlspecialchars($fc, ENT_QUOTES) . '\')"' : '' ?>></span>
                <span class="newslist-lead__body">
                    <?php if (!empty($featured['badge'])): ?><span class="newsdetail__badge"><?= htmlspecialchars((string) $featured['badge'], ENT_QUOTES) ?></span><?php endif; ?>
                    <?php if (!empty($featured['published_at'])): ?><time class="newslist__date"><?= htmlspecialchars($fmt((string) $featured['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                    <span class="newslist-lead__title"><?= htmlspecialchars((string) $featured['title'], ENT_QUOTES) ?></span>
                    <?php if (!empty($featured['excerpt'])): ?><span class="newslist-lead__excerpt"><?= htmlspecialchars(mb_substr(strip_tags((string) $featured['excerpt']), 0, 200), ENT_QUOTES) ?></span><?php endif; ?>
                    <span class="newsfeat__more">Читать далее →</span>
                </span>
            </a>
        <?php endif; ?>

        <div class="newslist-grid">
            <?php foreach ($grid as $item): ?>
                <?php $c = News::getCoverImage($item); ?>
                <a class="relnews-card" href="<?= htmlspecialchars(Locale::url('news/' . $item['slug']), ENT_QUOTES) ?>">
                    <span class="relnews-card__media<?= $c === null ? ' relnews-card__media--empty' : '' ?>"<?= $c !== null ? ' style="background-image:url(\'' . htmlspecialchars($c, ENT_QUOTES) . '\')"' : '' ?>></span>
                    <?php if (!empty($item['published_at'])): ?><time class="relnews-card__date"><?= htmlspecialchars($fmt((string) $item['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                    <span class="relnews-card__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                    <?php if (!empty($item['excerpt'])): ?><span class="relnews-card__excerpt"><?= htmlspecialchars(mb_substr(strip_tags((string) $item['excerpt']), 0, 110), ENT_QUOTES) ?></span><?php endif; ?>
                    <span class="relnews-card__arrow">→</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="listing-pager" aria-label="Страницы">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="listing-pager__item is-active" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a class="listing-pager__item" href="<?= htmlspecialchars(Locale::url('news') . ($i > 1 ? '?page=' . $i : ''), ENT_QUOTES) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
