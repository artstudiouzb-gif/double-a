<?php

use App\Core\Locale;

/** @var array $items */
$metaTitle = 'Проекты';
$metaDescription = 'Проекты и инициативы Агентства.';
require __DIR__ . '/_header.php';

$crumbs = [
    ['label' => t('Главная'), 'url' => Locale::url('/')],
    ['label' => t('Проекты')],
];
require __DIR__ . '/_crumbs.php';
?>
<div class="listing">
    <div class="listing__head">
        <h1 class="listing__title"><?= htmlspecialchars(t('Проекты и инициативы'), ENT_QUOTES) ?></h1>
        <p class="listing__lead"><?= htmlspecialchars(t('Стратегические проекты, которые Агентство реализует для устойчивого развития страны.'), ENT_QUOTES) ?></p>
    </div>
    <?php if (empty($items)): ?>
        <p class="listing__empty"><?= htmlspecialchars(t('Проекты ещё не опубликованы.'), ENT_QUOTES) ?></p>
    <?php else: ?>
        <div class="projects-grid">
            <?php foreach ($items as $item): ?>
                <?php $cover = trim((string) ($item['cover_image'] ?? '')); ?>
                <a class="imgcard imgcard--project" href="<?= htmlspecialchars(Locale::url('projects/' . $item['slug']), ENT_QUOTES) ?>">
                    <span class="imgcard__media"<?= $cover !== '' ? ' style="background-image:url(\'' . htmlspecialchars($cover, ENT_QUOTES) . '\')"' : '' ?>></span>
                    <span class="imgcard__overlay"></span>
                    <span class="imgcard__body">
                        <span class="imgcard__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                        <?php if (!empty($item['description'])): ?>
                            <span class="imgcard__desc"><?= htmlspecialchars(mb_substr(strip_tags((string) $item['description']), 0, 120), ENT_QUOTES) ?></span>
                        <?php endif; ?>
                        <span class="imgcard__more"><?= htmlspecialchars(t('Подробнее'), ENT_QUOTES) ?> →</span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
