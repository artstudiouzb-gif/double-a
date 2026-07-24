<?php
use App\Core\Locale;
use App\Models\Project;

/** @var array $project */
$metaTitle = (string) $project['title'];
$metaDescription = mb_substr(strip_tags((string) ($project['description'] ?? '')), 0, 200);
$ogImage = trim((string) ($project['cover_image'] ?? ''));
require __DIR__ . '/_header.php';

$crumbs = [
    ['label' => t('Главная'), 'url' => Locale::url('/')],
    ['label' => t('Кейсы'), 'url' => Locale::url('projects')],
    ['label' => (string) $project['title']],
];
$cover = trim((string) ($project['cover_image'] ?? ''));
$others = array_values(array_filter(Project::published(), fn (array $p) => (int) $p['id'] !== (int) $project['id']));
?>
<div class="corp-case-header">
    <div class="corp-wrap">
        <?php require __DIR__ . '/_crumbs.php'; ?>
        <h1><?= htmlspecialchars((string) $project['title'], ENT_QUOTES) ?></h1>

        <?php if (!empty($project['result_metric'])): ?>
            <div class="corp-case-result">
                <b><?= htmlspecialchars((string) $project['result_metric'], ENT_QUOTES) ?></b>
                <?php if (!empty($project['result_label'])): ?><span><?= htmlspecialchars((string) $project['result_label'], ENT_QUOTES) ?></span><?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="corp-case-grid">
            <div class="corp-case-stat">
                <b>Отрасль</b>
                <span>Консалтинг</span>
            </div>
            <div class="corp-case-stat">
                <b>Локация</b>
                <span>Узбекистан / СНГ</span>
            </div>
            <div class="corp-case-stat">
                <b>Услуга</b>
                <span>Регистрация</span>
            </div>
            <div class="corp-case-stat">
                <b>Срок реализации</b>
                <span>6 месяцев</span>
            </div>
        </div>
    </div>
</div>

<div class="corp-wrap" style="padding-top: 100px; padding-bottom: 100px;">
    <?php if ($cover !== ''): ?>
        <img src="<?= htmlspecialchars($cover, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $project['title'], ENT_QUOTES) ?>" style="width:100%; height:auto; max-height:600px; object-fit:cover; margin-bottom:60px;">
    <?php endif; ?>

    <div class="corp-article-body rich-content" style="margin: 0 auto; max-width: 800px;">
        <?= $project['description'] ?>
    </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>