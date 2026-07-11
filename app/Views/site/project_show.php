<?php

use App\Core\Locale;
use App\Models\Project;

/** @var array $project */
$metaTitle = (string) $project['title'];
$metaDescription = mb_substr(strip_tags((string) ($project['description'] ?? '')), 0, 200);
$ogImage = trim((string) ($project['cover_image'] ?? ''));
require __DIR__ . '/_header.php';

$crumbs = [
    ['label' => 'Главная', 'url' => Locale::url('/')],
    ['label' => 'Проекты', 'url' => Locale::url('projects')],
    ['label' => (string) $project['title']],
];
require __DIR__ . '/_crumbs.php';

$cover = trim((string) ($project['cover_image'] ?? ''));
$others = array_values(array_filter(Project::published(), fn (array $p) => (int) $p['id'] !== (int) $project['id']));
?>
<article class="projdetail">
    <div class="projdetail-head<?= $cover === '' ? ' projdetail-head--no-media' : '' ?>">
        <div class="projdetail-head__info">
            <span class="newsdetail__badge">Проект</span>
            <h1 class="projdetail__title"><?= htmlspecialchars((string) $project['title'], ENT_QUOTES) ?></h1>
        </div>
        <?php if ($cover !== ''): ?>
            <span class="projdetail__media" style="background-image:url('<?= htmlspecialchars($cover, ENT_QUOTES) ?>')"></span>
        <?php endif; ?>
    </div>
    <div class="projdetail__content newsdetail-article__content"><?= $project['description'] ?></div>

    <?php if (!empty($others)): ?>
        <section class="projdetail-related">
            <div class="section-head">
                <h2 class="section-head__title">Другие проекты</h2>
                <a class="section-head__all" href="<?= htmlspecialchars(Locale::url('projects'), ENT_QUOTES) ?>">Все проекты →</a>
            </div>
            <div class="projects-grid projects-grid--compact">
                <?php foreach (array_slice($others, 0, 4) as $item): ?>
                    <?php $c = trim((string) ($item['cover_image'] ?? '')); ?>
                    <a class="imgcard" href="<?= htmlspecialchars(Locale::url('projects/' . $item['slug']), ENT_QUOTES) ?>">
                        <span class="imgcard__media"<?= $c !== '' ? ' style="background-image:url(\'' . htmlspecialchars($c, ENT_QUOTES) . '\')"' : '' ?>></span>
                        <span class="imgcard__overlay"></span>
                        <span class="imgcard__body">
                            <span class="imgcard__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                            <span class="imgcard__more"><?= htmlspecialchars(t('Подробнее'), ENT_QUOTES) ?> →</span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
