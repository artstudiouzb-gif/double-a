<?php

use App\Core\AssetCollector;
use App\Core\Config;
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

$crumbs = [
    ['label' => 'Главная', 'url' => Locale::url('/')],
    ['label' => 'Новости', 'url' => Locale::url('news')],
    ['label' => (string) $news['title']],
];
require __DIR__ . '/_crumbs.php';

$date = (string) ($news['published_at'] ?? '');
$dateLong = $date !== '' ? DateFormatter::long($date, $lang) : '';
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

$base = rtrim((string) Config::get('app.url', ''), '/');
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
<article class="newsdetail">
    <div class="newsdetail-head">
        <div class="newsdetail-head__info">
            <?php if (!empty($news['badge'])): ?>
                <span class="newsdetail__badge"><?= htmlspecialchars((string) $news['badge'], ENT_QUOTES) ?></span>
            <?php endif; ?>
            <h1 class="newsdetail__title"><?= htmlspecialchars((string) $news['title'], ENT_QUOTES) ?></h1>
            <?php if (!empty($news['excerpt'])): ?>
                <p class="newsdetail__lead"><?= htmlspecialchars((string) $news['excerpt'], ENT_QUOTES) ?></p>
            <?php endif; ?>
            <div class="newsdetail__meta">
                <?php if ($dateLong !== ''): ?>
                    <span class="newsdetail__meta-item"><?= $eventIcons[0] ?><time datetime="<?= htmlspecialchars(substr($date, 0, 10), ENT_QUOTES) ?>"><?= htmlspecialchars($dateLong, ENT_QUOTES) ?></time></span>
                <?php endif; ?>
                <span class="newsdetail__meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg><?= $readMin ?> мин чтения</span>
                <?php if ($views > 0): ?>
                    <span class="newsdetail__meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="18" height="18"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg><?= number_format($views, 0, '', ' ') ?> просмотров</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($news['source_note'])): ?>
                <p class="newsdetail__source"><?= htmlspecialchars((string) $news['source_note'], ENT_QUOTES) ?></p>
            <?php endif; ?>
            <?php if ($videoUrl !== '' || $pressUrl !== ''): ?>
                <div class="newsdetail__actions">
                    <?php if ($videoUrl !== ''): ?>
                        <a class="newsdetail__btn newsdetail__btn--primary" href="<?= htmlspecialchars($videoUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M8 5.5v13l11-6.5z"/></svg>
                            Смотреть видео
                        </a>
                    <?php endif; ?>
                    <?php if ($pressUrl !== ''): ?>
                        <a class="newsdetail__btn newsdetail__btn--ghost" href="<?= htmlspecialchars($pressUrl, ENT_QUOTES) ?>" download>
                            Скачать пресс-релиз <?= $dlIcon ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($slides)): ?>
            <div class="newsdetail-gallery" data-ndgallery>
                <div class="newsdetail-gallery__main">
                    <?php foreach ($slides as $i => $s): ?>
                        <img class="newsdetail-gallery__slide<?= $i === 0 ? ' is-active' : '' ?>" src="<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($s['alt'], ENT_QUOTES) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                    <?php endforeach; ?>
                    <?php if (count($slides) > 1): ?>
                        <button type="button" class="newsdetail-gallery__nav newsdetail-gallery__nav--prev" data-ndg-prev aria-label="Предыдущее фото">‹</button>
                        <button type="button" class="newsdetail-gallery__nav newsdetail-gallery__nav--next" data-ndg-next aria-label="Следующее фото">›</button>
                        <span class="newsdetail-gallery__counter"><span data-ndg-current>1</span> из <?= count($slides) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (count($slides) > 1): ?>
                    <div class="newsdetail-gallery__thumbs">
                        <?php foreach ($slides as $i => $s): ?>
                            <button type="button" class="newsdetail-gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>" data-ndg-thumb="<?= $i ?>" aria-label="Фото <?= $i + 1 ?>" style="background-image:url('<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>')"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="newsdetail-body">
        <aside class="newsdetail-side">
            <?php if (!empty($keyPoints)): ?>
                <div class="newsdetail-card">
                    <h2 class="newsdetail-card__title">Ключевые тезисы</h2>
                    <ul class="newsdetail-points">
                        <?php foreach ($keyPoints as $point): ?>
                            <li class="newsdetail-points__item"><span class="newsdetail-points__icon"><?= $pointIcon ?></span><?= htmlspecialchars($point, ENT_QUOTES) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="newsdetail-share">
                <h2 class="newsdetail-share__title">Поделиться</h2>
                <div class="newsdetail-share__row">
                    <a class="newsdetail-share__btn" href="https://t.me/share/url?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>" target="_blank" rel="noopener" aria-label="Поделиться в Telegram"><svg viewBox="0 0 24 24" fill="currentColor" width="17" height="17"><path d="M21.9 4.6 19 19.3c-.2 1-.8 1.2-1.6.8l-4.5-3.3-2.2 2.1c-.2.2-.4.4-.9.4l.3-4.6 8.4-7.6c.4-.3-.1-.5-.6-.2L7.6 13.4l-4.5-1.4c-1-.3-1-1 .2-1.4l17.3-6.7c.8-.3 1.5.2 1.3 1.3z"/></svg></a>
                    <a class="newsdetail-share__btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Поделиться в Facebook"><svg viewBox="0 0 24 24" fill="currentColor" width="17" height="17"><path d="M14 8h3V5h-3c-2.2 0-4 1.8-4 4v2H7v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1z"/></svg></a>
                    <a class="newsdetail-share__btn" href="https://x.com/intent/post?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>" target="_blank" rel="noopener" aria-label="Поделиться в X"><svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M17.7 3H21l-7.1 8.2L22 21h-6.6l-5.1-6.1L4.5 21H1.2l7.6-8.7L1 3h6.8l4.6 5.6L17.7 3zm-1.2 16h1.8L6.9 4.9H5L16.5 19z"/></svg></a>
                    <a class="newsdetail-share__btn" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Поделиться в LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor" width="17" height="17"><path d="M6.5 8.8H3.6V21h2.9V8.8zM5 7.4a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4zM21 14.2c0-3.2-1.7-4.7-4-4.7-1.8 0-2.6 1-3.1 1.7V8.8H11V21h2.9v-6.5c0-1.7.8-2.7 2.2-2.7 1.3 0 2 .9 2 2.7V21H21v-6.8z"/></svg></a>
                    <button type="button" class="newsdetail-share__btn" data-copy-link="<?= htmlspecialchars($pageUrl, ENT_QUOTES) ?>" aria-label="Скопировать ссылку"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="17" height="17"><path d="M10 14a4.5 4.5 0 0 0 6.4 0l3.2-3.2a4.5 4.5 0 1 0-6.4-6.4L11.6 6"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3.2 3.2a4.5 4.5 0 1 0 6.4 6.4l1.6-1.6"/></svg></button>
                </div>
            </div>
        </aside>

        <div class="newsdetail-article">
            <div class="newsdetail-article__content"><?= $news['content'] ?></div>
        </div>

        <aside class="newsdetail-side newsdetail-side--right">
            <?php if (!empty($eventMeta)): ?>
                <div class="newsdetail-card">
                    <h2 class="newsdetail-card__title">О мероприятии</h2>
                    <ul class="newsdetail-event">
                        <?php foreach ($eventMeta as $i => $line): ?>
                            <li class="newsdetail-event__item"><span class="newsdetail-event__icon"><?= $eventIcons[$i % count($eventIcons)] ?></span><?= htmlspecialchars($line, ENT_QUOTES) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($docs)): ?>
                <div class="newsdetail-card">
                    <h2 class="newsdetail-card__title">Документы</h2>
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
            <div class="newsdetail-subscribe">
                <h2 class="newsdetail-subscribe__title">Подпишитесь на новости Агентства</h2>
                <p class="newsdetail-subscribe__text">Получайте самые важные новости и аналитические материалы на почту.</p>
                <form class="newsdetail-subscribe__form" method="post" action="<?= htmlspecialchars(Locale::url('subscribe'), ENT_QUOTES) ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="text" name="website" value="" style="position:absolute;left:-9999px" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="newsdetail-subscribe__row">
                        <label class="visually-hidden" for="nd-sub-email">Ваш e-mail</label>
                        <input id="nd-sub-email" type="email" name="email" required placeholder="Ваш e-mail" autocomplete="email">
                        <button type="submit" aria-label="Подписаться">→</button>
                    </div>
                    <?php if (Setting::get('form_consent_enabled', '0') === '1'): ?>
                        <label class="newsdetail-subscribe__consent">
                            <input type="checkbox" name="consent" value="1" required>
                            <span><?= htmlspecialchars((string) Setting::get('form_consent_text', 'Я даю согласие на обработку персональных данных'), ENT_QUOTES) ?></span>
                        </label>
                    <?php endif; ?>
                </form>
            </div>
        </aside>
    </div>

    <?php if (count($slides) > 1): ?>
        <section class="newsdetail-photos">
            <div class="section-head">
                <h2 class="section-head__title">Фотогалерея</h2>
                <a class="newsdetail__btn newsdetail__btn--ghost" href="<?= htmlspecialchars(Locale::url('news/' . $news['slug'] . '/photos.zip', $lang), ENT_QUOTES) ?>">Скачать все фото <?= $dlIcon ?></a>
            </div>
            <div class="newsdetail-photos__grid">
                <?php foreach (array_slice($slides, 0, 8) as $s): ?>
                    <a class="newsdetail-photos__item" href="<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>" target="_blank" rel="noopener" style="background-image:url('<?= htmlspecialchars($s['path'], ENT_QUOTES) ?>')" aria-label="<?= htmlspecialchars($s['alt'] !== '' ? $s['alt'] : 'Фото', ENT_QUOTES) ?>"></a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($related)): ?>
        <section class="newsdetail-related">
            <div class="section-head">
                <h2 class="section-head__title">Другие новости по теме</h2>
                <a class="section-head__all" href="<?= htmlspecialchars(Locale::url('news'), ENT_QUOTES) ?>">Все новости →</a>
            </div>
            <div class="newsdetail-related__grid">
                <?php foreach ($related as $item): ?>
                    <?php $rc = News::getCoverImage($item); ?>
                    <a class="relnews-card" href="<?= htmlspecialchars(Locale::url('news/' . $item['slug'], $lang), ENT_QUOTES) ?>">
                        <span class="relnews-card__media<?= $rc === null ? ' relnews-card__media--empty' : '' ?>"<?= $rc !== null ? ' style="background-image:url(\'' . htmlspecialchars($rc, ENT_QUOTES) . '\')"' : '' ?>></span>
                        <?php if (!empty($item['published_at'])): ?><time class="relnews-card__date"><?= htmlspecialchars(DateFormatter::long((string) $item['published_at'], $lang), ENT_QUOTES) ?></time><?php endif; ?>
                        <span class="relnews-card__title"><?= htmlspecialchars((string) $item['title'], ENT_QUOTES) ?></span>
                        <span class="relnews-card__arrow">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($prevNews !== null || $nextNews !== null): ?>
        <nav class="newsdetail-adjacent" aria-label="Соседние новости">
            <?php if ($prevNews !== null): ?>
                <?php $pc = News::getCoverImage($prevNews); ?>
                <a class="adjnews adjnews--prev" href="<?= htmlspecialchars(Locale::url('news/' . $prevNews['slug'], $lang), ENT_QUOTES) ?>">
                    <span class="adjnews__arrow">←</span>
                    <span class="adjnews__media<?= $pc === null ? ' adjnews__media--empty' : '' ?>"<?= $pc !== null ? ' style="background-image:url(\'' . htmlspecialchars($pc, ENT_QUOTES) . '\')"' : '' ?>></span>
                    <span class="adjnews__body">
                        <span class="adjnews__label">Предыдущая новость</span>
                        <?php if (!empty($prevNews['published_at'])): ?><time class="adjnews__date"><?= htmlspecialchars(DateFormatter::long((string) $prevNews['published_at'], $lang), ENT_QUOTES) ?></time><?php endif; ?>
                        <span class="adjnews__title"><?= htmlspecialchars((string) $prevNews['title'], ENT_QUOTES) ?></span>
                    </span>
                </a>
            <?php else: ?><span class="adjnews adjnews--empty"></span><?php endif; ?>
            <?php if ($nextNews !== null): ?>
                <?php $nc = News::getCoverImage($nextNews); ?>
                <a class="adjnews adjnews--next" href="<?= htmlspecialchars(Locale::url('news/' . $nextNews['slug'], $lang), ENT_QUOTES) ?>">
                    <span class="adjnews__media<?= $nc === null ? ' adjnews__media--empty' : '' ?>"<?= $nc !== null ? ' style="background-image:url(\'' . htmlspecialchars($nc, ENT_QUOTES) . '\')"' : '' ?>></span>
                    <span class="adjnews__body">
                        <span class="adjnews__label">Следующая новость</span>
                        <?php if (!empty($nextNews['published_at'])): ?><time class="adjnews__date"><?= htmlspecialchars(DateFormatter::long((string) $nextNews['published_at'], $lang), ENT_QUOTES) ?></time><?php endif; ?>
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
