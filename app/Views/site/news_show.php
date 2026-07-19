<?php

use App\Core\AssetCollector;
use App\Core\DateFormatter;
use App\Core\Locale;
use App\Models\News;
use App\Models\Setting;

/** @var array $news */
/** @var array $gallery */
/** @var array $related */
/** @var ?array $prevNews */
/** @var ?array $nextNews */
$gallery = $gallery ?? [];
$related = $related ?? [];
$prevNews = $prevNews ?? null;
$nextNews = $nextNews ?? null;
$lang = Locale::current();

$metaTitle = $news['meta_title'] ?: $news['title'];
$metaDescription = $news['meta_description'] ?: ($news['excerpt'] ?? '');
$ogType = 'article';
$ogImage = News::getCoverImage($news) ?? '';

AssetCollector::requireJs('news');

require __DIR__ . '/_header.php';

// Крошки: у премиум-макета рендерятся внутри hero (см. ниже), у остальных —
// обычной полосой перед статьёй.
$crumbs = [
    ['label' => t('Главная'), 'url' => Locale::url('/')],
    ['label' => t('Новости'), 'url' => Locale::url('news')],
    ['label' => (string) $news['title']],
];

$date = (string) ($news['published_at'] ?? '');
// Дата — единым числовым форматом на всех языках: 19.07.2026.
$dateLong = $date !== '' ? DateFormatter::short($date) : '';
// Время чтения: ~180 слов в минуту по тексту статьи (юникод-подсчёт слов).
preg_match_all('/[\p{L}\p{N}]+/u', strip_tags((string) ($news['content'] ?? '')), $m);
$readMin = max(1, (int) ceil(count($m[0]) / 180));
$views = (int) ($news['views'] ?? 0);

// Слайды: обложка + галерея (уникальные пути).
$slides = [];
$cover = trim((string) ($news['image'] ?? ''));
if ($cover !== '') {
    $slides[] = ['path' => $cover, 'alt' => (string) $news['title']];
}
foreach ($gallery as $img) {
    $p = trim((string) $img['path']);
    if ($p !== '' && $p !== $cover) {
        $slides[] = ['path' => $p, 'alt' => (string) ($img['alt_text'] ?? '')];
    }
}

$keyPoints = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($news['key_points'] ?? '')) ?: [])));
$eventMeta = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($news['event_meta'] ?? '')) ?: [])));
$docs = json_decode((string) ($news['docs'] ?? '[]'), true);
$docs = is_array($docs) ? $docs : [];
$videoUrl = trim((string) ($news['video_url'] ?? ''));
$pressUrl = trim((string) ($news['press_release_url'] ?? ''));

$base = \App\Core\AppUrl::base();
$pageUrl = $base . Locale::url('news/' . $news['slug'], $lang);
$shareTitle = rawurlencode((string) $news['title']);
$shareUrl = rawurlencode($pageUrl);

// Общие мини-иконки (событие: календарь, место, участники, теги — по кругу).
$eventIcons = [
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/></svg>',
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><path d="M12 21s-6-5.2-6-10a6 6 0 1 1 12 0c0 4.8-6 10-6 10z"/><circle cx="12" cy="11" r="2.2"/></svg>',
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><circle cx="9" cy="8" r="3"/><path d="M3 19c0-3 2.7-4.5 6-4.5s6 1.5 6 4.5"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.7c2.6.3 5 1.7 5 4.3"/></svg>',
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><path d="M4 11V5a1 1 0 0 1 1-1h6l9 9-7 7-9-9z"/><circle cx="8.5" cy="8.5" r="1.3"/></svg>',
];
$pointIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="22" height="22"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.3 2.3 4.7-4.8"/></svg>';
$docIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/></svg>';
$dlIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="17" height="17"><path d="M12 4v11m0 0 4-4m-4 4-4-4"/><path d="M5 19h14"/></svg>';
?>
<?php
// Тип отображения (выбирается в админке): standard — только обложка,
// gallery — слайдер с миниатюрами, video — модуль YouTube с play,
// side_image — компактное фото сбоку от заголовка.
$layout = News::normalizeLayout($news['layout_type'] ?? 'standard');
$videoId = \App\Core\Video::youtubeId($news['video_url'] ?? null);
if ($layout === 'video' && $videoId === null) {
    $layout = 'standard'; // без валидного YouTube-URL показываем обложку
}
// Адаптация макета к наполнению: без медиа hero занимает всю ширину,
// без тезисов левая колонка убирается, «Поделиться» уходит под статью.
$isPremium = $layout === 'premium';
$heroSlides = $layout === 'gallery' ? $slides : array_slice($slides, 0, 1);
$hasMedia = !$isPremium && ($layout === 'video' || !empty($heroSlides));
$hasLeft = !empty($keyPoints);

// Оглавление статьи (премиум): собираем из <h2>/<h3> контента и проставляем id.
$toc = [];
$contentHtml = (string) $news['content'];
if ($isPremium) {
    $n = 0;
    $contentHtml = (string) preg_replace_callback(
        '/<h([23])([^>]*)>(.*?)<\/h\1>/su',
        static function (array $m) use (&$toc, &$n): string {
            $n++;
            $id = 'sec-' . $n;
            $toc[] = ['id' => $id, 'label' => trim(strip_tags($m[3]))];
            return '<h' . $m[1] . $m[2] . ' id="' . $id . '">' . $m[3] . '</h' . $m[1] . '>';
        },
        $contentHtml
    ) ?: $contentHtml;
}

$shareBlock = static function (string $extraClass) use ($shareUrl, $shareTitle, $pageUrl): void { ?>
            <div class="newsdetail-share no-print<?= $extraClass ?>">
                <h2 class="newsdetail-share__title"><?= htmlspecialchars(t('Поделиться'), ENT_QUOTES) ?></h2>
                <div class="newsdetail-share__row">
                    <a class="newsdetail-share__btn" href="https://t.me/share/url?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars(t('Поделиться в Telegram'), ENT_QUOTES) ?>"><svg viewBox="0 0 24 24" fill="currentColor" width="17" height="17"><path d="M21.9 4.6 19 19.3c-.2 1-.8 1.2-1.6.8l-4.5-3.3-2.2 2.1c-.2.2-.4.4-.9.4l.3-4.6 8.4-7.6c.4-.3-.1-.5-.6-.2L7.6 13.4l-4.5-1.4c-1-.3-1-1 .2-1.4l17.3-6.7c.8-.3 1.5.2 1.3 1.3z"/></svg></a>
                    <a class="newsdetail-share__btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars(t('Поделиться в Facebook'), ENT_QUOTES) ?>"><svg viewBox="0 0 24 24" fill="currentColor" width="17" height="17"><path d="M14 8h3V5h-3c-2.2 0-4 1.8-4 4v2H7v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1z"/></svg></a>
                    <a class="newsdetail-share__btn" href="https://x.com/intent/post?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars(t('Поделиться в X'), ENT_QUOTES) ?>"><svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M17.7 3H21l-7.1 8.2L22 21h-6.6l-5.1-6.1L4.5 21H1.2l7.6-8.7L1 3h6.8l4.6 5.6L17.7 3zm-1.2 16h1.8L6.9 4.9H5L16.5 19z"/></svg></a>
                    <a class="newsdetail-share__btn" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars(t('Поделиться в LinkedIn'), ENT_QUOTES) ?>"><svg viewBox="0 0 24 24" fill="currentColor" width="17" height="17"><path d="M6.5 8.8H3.6V21h2.9V8.8zM5 7.4a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4zM21 14.2c0-3.2-1.7-4.7-4-4.7-1.8 0-2.6 1-3.1 1.7V8.8H11V21h2.9v-6.5c0-1.7.8-2.7 2.2-2.7 1.3 0 2 .9 2 2.7V21H21v-6.8z"/></svg></a>
                    <button type="button" class="newsdetail-share__btn" data-copy-link="<?= htmlspecialchars($pageUrl, ENT_QUOTES) ?>" aria-label="<?= htmlspecialchars(t('Скопировать ссылку'), ENT_QUOTES) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="17" height="17"><path d="M10 14a4.5 4.5 0 0 0 6.4 0l3.2-3.2a4.5 4.5 0 1 0-6.4-6.4L11.6 6"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3.2 3.2a4.5 4.5 0 1 0 6.4 6.4l1.6-1.6"/></svg></button>
                    <button type="button" class="newsdetail-share__btn" data-print-page aria-label="<?= htmlspecialchars(t('Распечатать'), ENT_QUOTES) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="17" height="17"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2m-12 0v4h8v-4m-8 0h8"/></svg></button>
                </div>
            </div>
<?php };

if (!$isPremium) {
    require __DIR__ . '/_crumbs.php';
}
?>
<article class="newsdetail<?= $isPremium ? ' newsdetail--premium' : '' ?>">
    <?php if ($isPremium): ?>
    <div class="newsdetail-phero"<?= $cover !== '' ? ' style="background-image:url(\'' . htmlspecialchars($cover, ENT_QUOTES) . '\')"' : '' ?>>
        <span class="newsdetail-phero__overlay"></span>
        <div class="newsdetail-phero__body">
            <?php require __DIR__ . '/_crumbs.php'; ?>
            <?php if (!empty($news['badge'])): ?>
                <span class="newsdetail__badge newsdetail__badge--onDark"><?= htmlspecialchars((string) $news['badge'], ENT_QUOTES) ?></span>
            <?php endif; ?>
            <div class="newsdetail__meta newsdetail__meta--onDark">
                <?php if ($dateLong !== ''): ?>
                    <span class="newsdetail__meta-item"><?= $eventIcons[0] ?><time datetime="<?= htmlspecialchars(substr($date, 0, 10), ENT_QUOTES) ?>"><?= htmlspecialchars($dateLong, ENT_QUOTES) ?></time></span>
                <?php endif; ?>
                <span class="newsdetail__meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg><?= $readMin ?> <?= htmlspecialchars(t('мин чтения'), ENT_QUOTES) ?></span>
                <?php if ($views > 0): ?>
                    <span class="newsdetail__meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg><?= number_format($views, 0, '', ' ') ?> <?= htmlspecialchars(t('просмотров'), ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>
            <h1 class="newsdetail-phero__title"><?= htmlspecialchars((string) $news['title'], ENT_QUOTES) ?></h1>
            <?php if (!empty($news['excerpt'])): ?>
                <p class="newsdetail-phero__lead"><?= htmlspecialchars((string) $news['excerpt'], ENT_QUOTES) ?></p>
            <?php endif; ?>
            <?php if (!empty($news['source_note'])): ?>
                <p class="newsdetail__source newsdetail__source--onDark"><?= htmlspecialchars((string) $news['source_note'], ENT_QUOTES) ?></p>
            <?php endif; ?>
            <?php if ($videoUrl !== ''): ?>
                <a class="newsdetail-phero__video" href="<?= htmlspecialchars($videoUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                    <span class="newsdetail-phero__play"><svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M8 5.5v13l11-6.5z"/></svg></span>
                    <?= htmlspecialchars(t('Смотреть видео'), ENT_QUOTES) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="newsdetail-head<?= $hasMedia ? '' : ' newsdetail-head--full' ?><?= $hasMedia && $layout === 'side_image' ? ' newsdetail-head--side' : '' ?>">
        <div class="newsdetail-head__info">
            <?php if (!empty($news['badge'])): ?>
                <span class="newsdetail__badge"><?= htmlspecialchars((string) $news['badge'], ENT_QUOTES) ?></span>
            <?php endif; ?>
            <div class="newsdetail__meta">
                <?php if ($dateLong !== ''): ?>
                    <span class="newsdetail__meta-item"><?= $eventIcons[0] ?><time datetime="<?= htmlspecialchars(substr($date, 0, 10), ENT_QUOTES) ?>"><?= htmlspecialchars($dateLong, ENT_QUOTES) ?></time></span>
                <?php endif; ?>
                <span class="newsdetail__meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg><?= $readMin ?> <?= htmlspecialchars(t('мин чтения'), ENT_QUOTES) ?></span>
                <?php if ($views > 0): ?>
                    <span class="newsdetail__meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg><?= number_format($views, 0, '', ' ') ?> <?= htmlspecialchars(t('просмотров'), ENT_QUOTES) ?></span>
                <?php endif; ?>
            </div>
            <h1 class="newsdetail__title"><?= htmlspecialchars((string) $news['title'], ENT_QUOTES) ?></h1>
            <?php if (!empty($news['excerpt'])): ?>
                <p class="newsdetail__lead"><?= htmlspecialchars((string) $news['excerpt'], ENT_QUOTES) ?></p>
            <?php endif; ?>
            <?php if (!empty($news['source_note'])): ?>
                <p class="newsdetail__source"><?= htmlspecialchars((string) $news['source_note'], ENT_QUOTES) ?></p>
            <?php endif; ?>
            <?php if ($videoUrl !== '' || $pressUrl !== ''): ?>
                <div class="newsdetail__actions">
                    <?php if ($videoUrl !== ''): ?>
                        <a class="newsdetail__btn newsdetail__btn--primary" href="<?= htmlspecialchars($videoUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M8 5.5v13l11-6.5z"/></svg>
                            <?= htmlspecialchars(t('Смотреть видео'), ENT_QUOTES) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($pressUrl !== ''): ?>
                        <a class="newsdetail__btn newsdetail__btn--ghost" href="<?= htmlspecialchars($pressUrl, ENT_QUOTES) ?>" download>
                            <?= htmlspecialchars(t('Скачать пресс-релиз'), ENT_QUOTES) ?> <?= $dlIcon ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($layout === 'video'): ?>
            <?php
            // Модуль видео: обложка YouTube, плеер грузится только по клику (news.js).
            $thumb = \App\Core\Video::youtubeThumbnail($videoId);
            $fallback = 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';
            $embed = \App\Core\Video::youtubeEmbed($videoId) . '&autoplay=1';
            ?>
            <div class="newsdetail-media">
                <div class="news-video newsdetail-video skeleton" data-youtube="<?= htmlspecialchars($videoId, ENT_QUOTES) ?>" data-embed="<?= htmlspecialchars($embed, ENT_QUOTES) ?>">
                    <img class="news-video__thumb" src="<?= htmlspecialchars($cover !== '' ? $cover : $thumb, ENT_QUOTES) ?>" data-fallback="<?= htmlspecialchars($fallback, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $news['title'], ENT_QUOTES) ?>" loading="eager" decoding="async">
                    <button type="button" class="news-video__play" aria-label="<?= htmlspecialchars(t('Смотреть видео'), ENT_QUOTES) ?>"></button>
                </div>
            </div>
        <?php elseif (!empty($heroSlides)): ?>
            <div class="newsdetail-gallery" data-ndgallery>
                <div class="newsdetail-gallery__main">
                    <?php foreach ($heroSlides as $i => $s): ?>
                        <img class="newsdetail-gallery__slide<?= $i === 0 ? ' is-active' : '' ?>" src="<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($s['alt'], ENT_QUOTES) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                    <?php endforeach; ?>
                    <?php if (count($heroSlides) > 1): ?>
                        <button type="button" class="newsdetail-gallery__nav newsdetail-gallery__nav--prev" data-ndg-prev aria-label="<?= htmlspecialchars(t('Предыдущее фото'), ENT_QUOTES) ?>">‹</button>
                        <button type="button" class="newsdetail-gallery__nav newsdetail-gallery__nav--next" data-ndg-next aria-label="<?= htmlspecialchars(t('Следующее фото'), ENT_QUOTES) ?>">›</button>
                        <span class="newsdetail-gallery__counter"><span data-ndg-current>1</span> <?= htmlspecialchars(t('из'), ENT_QUOTES) ?> <?= count($heroSlides) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (count($heroSlides) > 1): ?>
                    <div class="newsdetail-gallery__thumbs">
                        <?php foreach ($heroSlides as $i => $s): ?>
                            <button type="button" class="newsdetail-gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>" data-ndg-thumb="<?= $i ?>" aria-label="<?= htmlspecialchars(t('Фото'), ENT_QUOTES) ?> <?= $i + 1 ?>" style="background-image:url('<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>')"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="newsdetail-body<?= $hasLeft ? '' : ' newsdetail-body--no-left' ?>">
        <?php if ($hasLeft): ?>
        <aside class="newsdetail-side">
            <div class="newsdetail-card">
                <h2 class="newsdetail-card__title"><?= htmlspecialchars(t('Ключевые тезисы'), ENT_QUOTES) ?></h2>
                <ul class="newsdetail-points">
                    <?php foreach ($keyPoints as $point): ?>
                        <li class="newsdetail-points__item"><span class="newsdetail-points__icon"><?= $pointIcon ?></span><?= htmlspecialchars($point, ENT_QUOTES) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php $shareBlock(''); ?>
        </aside>
        <?php endif; ?>

        <div class="newsdetail-article">
            <div class="newsdetail-article__content rich-content"><?= $contentHtml ?></div>
            <?php if (!$hasLeft): ?>
                <?php $shareBlock(' newsdetail-share--inline'); ?>
            <?php endif; ?>
        </div>

        <aside class="newsdetail-side newsdetail-side--right">
            <?php if ($isPremium && !empty($slides)): ?>
                <div class="newsdetail-card">
                    <h2 class="newsdetail-card__title"><?= htmlspecialchars(t('Галерея'), ENT_QUOTES) ?></h2>
                    <div class="newsdetail-sidegallery">
                        <?php foreach (array_slice($slides, 0, 4) as $i => $s): ?>
                            <a class="newsdetail-sidegallery__item<?= $i === 0 ? ' newsdetail-sidegallery__item--wide' : '' ?>" href="<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars($s['alt'] !== '' ? $s['alt'] : 'Фото', ENT_QUOTES) ?>"><img src="<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($s['alt'], ENT_QUOTES) ?>" loading="lazy" decoding="async"></a>
                        <?php endforeach; ?>
                    </div>
                    <a class="newsdetail__btn newsdetail__btn--ghost newsdetail-sidegallery__all" href="<?= htmlspecialchars(Locale::url('news/' . $news['slug'] . '/photos.zip', $lang), ENT_QUOTES) ?>"><?= htmlspecialchars(t('Скачать все фото'), ENT_QUOTES) ?> <?= $dlIcon ?></a>
                </div>
            <?php endif; ?>
            <?php if (!empty($eventMeta)): ?>
                <div class="newsdetail-card">
                    <h2 class="newsdetail-card__title"><?= htmlspecialchars(t('О мероприятии'), ENT_QUOTES) ?></h2>
                    <ul class="newsdetail-event">
                        <?php foreach ($eventMeta as $i => $line): ?>
                            <li class="newsdetail-event__item"><span class="newsdetail-event__icon"><?= $eventIcons[$i % count($eventIcons)] ?></span><?= htmlspecialchars($line, ENT_QUOTES) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($docs)): ?>
                <div class="newsdetail-card">
                    <h2 class="newsdetail-card__title"><?= htmlspecialchars(t('Документы'), ENT_QUOTES) ?></h2>
                    <div class="newsdetail-docs">
                        <?php foreach ($docs as $doc): ?>
                            <?php $du = trim((string) ($doc['url'] ?? '')); ?>
                            <a class="newsdetail-doc" href="<?= htmlspecialchars($du, ENT_QUOTES) ?>" <?= $du !== '' ? 'download' : '' ?>>
                                <span class="newsdetail-doc__icon"><?= $docIcon ?></span>
                                <span class="newsdetail-doc__body">
                                    <span class="newsdetail-doc__title"><?= htmlspecialchars((string) ($doc['title'] ?? ''), ENT_QUOTES) ?></span>
                                    <?php if (!empty($doc['meta'])): ?><span class="newsdetail-doc__meta"><?= htmlspecialchars((string) $doc['meta'], ENT_QUOTES) ?></span><?php endif; ?>
                                </span>
                                <span class="newsdetail-doc__dl"><?= $dlIcon ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="newsdetail-subscribe no-print">
                <h2 class="newsdetail-subscribe__title"><?= htmlspecialchars(t('Подпишитесь на новости Агентства'), ENT_QUOTES) ?></h2>
                <p class="newsdetail-subscribe__text"><?= htmlspecialchars(t('Получайте самые важные новости и аналитические материалы на почту.'), ENT_QUOTES) ?></p>
                <form class="newsdetail-subscribe__form" method="post" action="<?= htmlspecialchars(Locale::url('subscribe'), ENT_QUOTES) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="text" name="website" value="" style="position:absolute;left:-9999px" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="newsdetail-subscribe__row">
                        <label class="visually-hidden" for="nd-sub-email"><?= htmlspecialchars(t('Ваш e-mail'), ENT_QUOTES) ?></label>
                        <input id="nd-sub-email" type="email" name="email" required placeholder="<?= htmlspecialchars(t('Ваш e-mail'), ENT_QUOTES) ?>" autocomplete="email">
                        <button type="submit" aria-label="<?= htmlspecialchars(t('Подписаться'), ENT_QUOTES) ?>">→</button>
                    </div>
                    <?php if (Setting::get('form_consent_enabled', '0') === '1'): ?>
                        <label class="newsdetail-subscribe__consent">
                            <input type="checkbox" name="consent" value="1" required>
                            <span><?= htmlspecialchars((string) Setting::get('form_consent_text', t('Я даю согласие на обработку персональных данных')), ENT_QUOTES) ?></span>
                        </label>
                    <?php endif; ?>
                </form>
            </div>
        </aside>
    </div>

    <?php
    // Фотогалерея-лента: для видео — все фото (герой занят плеером),
    // для остальных типов — когда фотографий больше одной.
    $showPhotoStrip = $isPremium ? false : ($layout === 'video' ? !empty($slides) : count($slides) > 1);
    ?>
    <?php if ($showPhotoStrip): ?>
        <section class="newsdetail-photos">
            <div class="section-head">
                <h2 class="section-head__title"><?= htmlspecialchars(t('Фотогалерея'), ENT_QUOTES) ?></h2>
                <a class="newsdetail__btn newsdetail__btn--ghost" href="<?= htmlspecialchars(Locale::url('news/' . $news['slug'] . '/photos.zip', $lang), ENT_QUOTES) ?>"><?= htmlspecialchars(t('Скачать все фото'), ENT_QUOTES) ?> <?= $dlIcon ?></a>
            </div>
            <div class="newsdetail-photos__grid">
                <?php foreach (array_slice($slides, 0, 8) as $s): ?>
                    <a class="newsdetail-photos__item" href="<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars($s['alt'] !== '' ? $s['alt'] : 'Фото', ENT_QUOTES) ?>"><img src="<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($s['alt'], ENT_QUOTES) ?>" loading="lazy" decoding="async"></a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($related)): ?>
        <section class="newsdetail-related no-print">
            <div class="section-head">
                <h2 class="section-head__title"><?= htmlspecialchars(t('Другие новости по теме'), ENT_QUOTES) ?></h2>
                <a class="section-head__all" href="<?= htmlspecialchars(Locale::url('news'), ENT_QUOTES) ?>"><?= htmlspecialchars(t('Все новости'), ENT_QUOTES) ?> →</a>
            </div>
            <div class="newsdetail-related__grid">
                <?php foreach ($related as $item): ?>
                    <?php $rc = News::getCoverImage($item); ?>
                    <a class="relnews-card" href="<?= htmlspecialchars(Locale::url('news/' . $item['slug'], $lang), ENT_QUOTES) ?>">
                        <?php if ($rc !== null): ?>
                            <img class="relnews-card__media" src="<?= htmlspecialchars($rc, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="relnews-card__media relnews-card__media--empty" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?php if (!empty($item['published_at'])): ?><time class="relnews-card__date"><?= htmlspecialchars(DateFormatter::short((string) $item['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                        <span class="relnews-card__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                        <span class="relnews-card__arrow">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($prevNews !== null || $nextNews !== null || ($isPremium && !empty($toc))): ?>
        <nav class="newsdetail-adjacent no-print<?= $isPremium && !empty($toc) ? ' newsdetail-adjacent--with-toc' : '' ?>" aria-label="<?= htmlspecialchars(t('Соседние новости'), ENT_QUOTES) ?>">
            <?php if ($isPremium && !empty($toc)): ?>
                <div class="newsdetail-toc">
                    <span class="newsdetail-toc__title"><?= htmlspecialchars(t('Навигация по статье'), ENT_QUOTES) ?></span>
                    <ol class="newsdetail-toc__list">
                        <?php foreach ($toc as $i => $item): ?>
                            <li><a href="#<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>"><span class="newsdetail-toc__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span><?= htmlspecialchars($item['label'], ENT_QUOTES) ?></a></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>
            <?php if ($prevNews !== null): ?>
                <?php $pc = News::getCoverImage($prevNews); ?>
                <a class="adjnews adjnews--prev" href="<?= htmlspecialchars(Locale::url('news/' . $prevNews['slug'], $lang), ENT_QUOTES) ?>">
                    <span class="adjnews__arrow">←</span>
                    <?php if ($pc !== null): ?>
                        <img class="adjnews__media" src="<?= htmlspecialchars($pc, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $prevNews['title'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="adjnews__media adjnews__media--empty" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="adjnews__body">
                        <span class="adjnews__label"><?= htmlspecialchars(t('Предыдущая новость'), ENT_QUOTES) ?></span>
                        <?php if (!empty($prevNews['published_at'])): ?><time class="adjnews__date"><?= htmlspecialchars(DateFormatter::short((string) $prevNews['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                        <span class="adjnews__title"><?= htmlspecialchars((string) $prevNews['title'], ENT_QUOTES) ?></span>
                    </span>
                </a>
            <?php else: ?><span class="adjnews adjnews--empty"></span><?php endif; ?>
            <?php if ($nextNews !== null): ?>
                <?php $nc = News::getCoverImage($nextNews); ?>
                <a class="adjnews adjnews--next" href="<?= htmlspecialchars(Locale::url('news/' . $nextNews['slug'], $lang), ENT_QUOTES) ?>">
                    <?php if ($nc !== null): ?>
                        <img class="adjnews__media" src="<?= htmlspecialchars($nc, ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $nextNews['title'], ENT_QUOTES) ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="adjnews__media adjnews__media--empty" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="adjnews__body">
                        <span class="adjnews__label"><?= htmlspecialchars(t('Следующая новость'), ENT_QUOTES) ?></span>
                        <?php if (!empty($nextNews['published_at'])): ?><time class="adjnews__date"><?= htmlspecialchars(DateFormatter::short((string) $nextNews['published_at']), ENT_QUOTES) ?></time><?php endif; ?>
                        <span class="adjnews__title"><?= htmlspecialchars((string) $nextNews['title'], ENT_QUOTES) ?></span>
                    </span>
                    <span class="adjnews__arrow">→</span>
                </a>
            <?php else: ?><span class="adjnews adjnews--empty"></span><?php endif; ?>
        </nav>
    <?php endif; ?>
</article>
<?php // Schema.org: карточка новости для поисковиков. ?>
<?= \App\Core\SchemaOrg::render(\App\Core\SchemaOrg::newsArticle(
    (string) $news['title'],
    $pageUrl,
    (string) ($news['published_at'] ?? ''),
    (string) ($news['excerpt'] ?? ''),
    $ogImage !== '' ? $base . $ogImage : '',
    \App\Models\Setting::get('site_name', '')
)) . "\n" ?>
<?php require __DIR__ . '/_footer.php'; ?>
