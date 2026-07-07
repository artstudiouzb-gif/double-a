<?php

use App\Core\AssetCollector;
use App\Core\Locale;
use App\Models\News;

/** @var array $news */
/** @var array $gallery */
$gallery = $gallery ?? [];

$metaTitle = $news['meta_title'] ?: $news['title'];
$metaDescription = $news['meta_description'] ?: ($news['excerpt'] ?? '');
$ogType = 'article';
// og:image — по централизованному приоритету обложки.
$ogImage = News::getCoverImage($news) ?? '';

// Слайдер/видео новостей используют один общий скрипт — подключаем один раз.
AssetCollector::requireJs('news');

require __DIR__ . '/_header.php';

$crumbs = [
    ['label' => 'Главная', 'url' => Locale::url('/')],
    ['label' => 'Новости', 'url' => Locale::url('news')],
    ['label' => (string) $news['title']],
];
require __DIR__ . '/_crumbs.php';

// Динамический выбор шаблона по типу отображения (задача 67).
$layout = News::normalizeLayout($news['layout_type'] ?? 'standard');
$typeTemplate = __DIR__ . '/news/_type_' . $layout . '.php';
if (!is_file($typeTemplate)) {
    $typeTemplate = __DIR__ . '/news/_type_standard.php';
}
require $typeTemplate;
?>
<p class="news-single__back"><a href="<?= htmlspecialchars(Locale::url('news'), ENT_QUOTES) ?>">&larr; Все новости</a></p>
<?php require __DIR__ . '/_footer.php'; ?>
