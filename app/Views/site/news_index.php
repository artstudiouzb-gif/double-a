<?php

/** @var array $items */

$metaTitle = 'Новости';
$metaDescription = '';
require __DIR__ . '/_header.php';
?>
<div class="news-list">
    <h1>Новости</h1>
    <?php if (empty($items)): ?>
        <p>Пока нет опубликованных новостей.</p>
    <?php endif; ?>
    <?php foreach ($items as $item): ?>
        <article class="news-list__item">
            <?php if (!empty($item['image'])): ?>
                <a href="/news/<?= htmlspecialchars($item['slug'], ENT_QUOTES) ?>">
                    <img src="<?= htmlspecialchars($item['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES) ?>" loading="lazy">
                </a>
            <?php endif; ?>
            <div class="news-list__body">
                <time><?= htmlspecialchars(substr((string) $item['published_at'], 0, 10), ENT_QUOTES) ?></time>
                <h2><a href="/news/<?= htmlspecialchars($item['slug'], ENT_QUOTES) ?>"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></a></h2>
                <?php if (!empty($item['excerpt'])): ?><p><?= htmlspecialchars($item['excerpt'], ENT_QUOTES) ?></p><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
