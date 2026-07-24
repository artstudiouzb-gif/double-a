<?php

use App\Core\Locale;

/** @var array $items */
$metaTitle = t('Кейсы');
$metaDescription = t('Реализованные кейсы и проекты Double A Solutions.');
require __DIR__ . '/_header.php';

$crumbs = [
    ['label' => t('Главная'), 'url' => Locale::url('/')],
    ['label' => t('Кейсы')],
];
require __DIR__ . '/_crumbs.php';
?>
<div class="listing">
    <div class="listing__head">
        <h1 class="listing__title"><?= htmlspecialchars(t('Кейсы и проекты'), ENT_QUOTES) ?></h1>
        <p class="listing__lead"><?= htmlspecialchars(t('Практические результаты нашей экспертизы: успешные кейсы по импорту, экспорту, локализации производств и сертификации в Узбекистане.'), ENT_QUOTES) ?></p>
    </div>
    <?php if (empty($items)): ?>
        <p class="listing__empty"><?= htmlspecialchars(t('Проекты ещё не опубликованы.'), ENT_QUOTES) ?></p>
    <?php else: ?>
        <div class="projects-grid">
            <?php foreach ($items as $item): ?>
                <?php $cover = trim((string) ($item['cover_image'] ?? '')); ?>
                <a class="imgcard imgcard--project" href="<?= htmlspecialchars(Locale::url('projects/' . $item['slug']), ENT_QUOTES) ?>">
                    <?php if ($cover !== ''): ?>
                        <?= \App\Core\Media::picture($cover, (string) $item['title'], null, null, 'imgcard__media', true, '(max-width: 700px) 100vw, 50vw') ?>
                    <?php else: ?>
                        <span class="imgcard__media" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="imgcard__overlay"></span>
                    <span class="imgcard__body">
                        <?php if (!empty($item['result_metric'])): ?>
                            <span class="imgcard__metric">
                                <b><?= htmlspecialchars((string) $item['result_metric'], ENT_QUOTES) ?></b>
                                <?php if (!empty($item['result_label'])): ?><span><?= htmlspecialchars((string) $item['result_label'], ENT_QUOTES) ?></span><?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <span class="imgcard__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                        <?php if (!empty($item['description'])): ?>
                            <span class="imgcard__desc"><?= htmlspecialchars(excerpt((string) $item['description'], 120), ENT_QUOTES) ?></span>
                        <?php endif; ?>
                        <span class="imgcard__more"><?= htmlspecialchars(t('Подробнее'), ENT_QUOTES) ?> →</span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
