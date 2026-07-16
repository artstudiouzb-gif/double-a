<?php

use App\Core\Csrf;
use App\Core\Format;

/** @var array $files */
/** @var list<array{id:int, parent_id:?int, label:string, files_count:int}> $categories */
/** @var string $query */
/** @var int $category выбранная категория (0 — все) */
/** @var array|null $repoUser */
/** @var int $totalCount */
/** @var array $popular */
/** @var array $latest */
$totalCount = $totalCount ?? count($files);
$popular = $popular ?? [];
$latest = $latest ?? [];

$pageTitle = 'Репозиторий документов';
require __DIR__ . '/layout/top.php';

$docIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="26" height="26"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/></svg>';
$extBadge = static function (array $f): string {
    $ext = strtoupper((string) pathinfo((string) $f['original_name'], PATHINFO_EXTENSION));
    return $ext !== '' ? $ext : 'FILE';
};
?>
<div class="rd">
    <header class="rd-hero">
        <div class="rd-hero__info">
            <h1 class="rd-hero__title">Репозиторий документов</h1>
            <p class="rd-hero__lead">Единая база официальных документов, стратегий, отчётов, исследований и аналитических материалов Агентства.</p>
            <form method="get" action="/repo" class="rd-search" role="search">
                <input type="search" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>" placeholder="Поиск по названию, теме или ключевому слову…" aria-label="Поиск по документам">
                <?php if ($category > 0): ?><input type="hidden" name="category" value="<?= (int) $category ?>"><?php endif; ?>
                <button type="submit" aria-label="Найти"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></button>
            </form>
        </div>
        <div class="rd-hero__art" aria-hidden="true"><?= $docIcon ?></div>
    </header>

    <div class="rd-stats">
        <div class="rd-stat"><span class="rd-stat__num"><?= $totalCount ?></span><span class="rd-stat__label">документов в базе</span></div>
        <div class="rd-stat"><span class="rd-stat__num"><?= count($categories) ?></span><span class="rd-stat__label">категорий</span></div>
        <div class="rd-stat"><span class="rd-stat__num"><?= array_sum(array_map(static fn ($f) => (int) $f['download_count'], $popular)) ?></span><span class="rd-stat__label">скачиваний популярных</span></div>
        <div class="rd-stat"><span class="rd-stat__num"><?= count($latest) ?></span><span class="rd-stat__label">новых публикаций</span></div>
    </div>

    <details class="rd-upload">
        <summary class="rd-btn rd-btn--primary">+ Предложить документ</summary>
        <form method="post" action="/repo/upload" enctype="multipart/form-data" class="rd-upload__form">
            <?= Csrf::field() ?>
            <label>Название<br><input type="text" name="title" required maxlength="255"></label>
            <label>Категория<br>
                <select name="category_id">
                    <option value="0">— Без категории —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars((string) $cat['label'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Описание (необязательно)<br><textarea name="description" rows="2"></textarea></label>
            <label>Файл (PDF, Office, изображения, ZIP — до 100 МБ)<br><input type="file" name="file" required></label>
            <p class="rd-upload__hint">Документ появится на портале после проверки и одобрения администратором.</p>
            <button type="submit" class="rd-btn rd-btn--primary">Отправить на модерацию</button>
        </form>
    </details>

    <?php if (!empty($categories)): ?>
        <section class="rd-cats">
            <div class="rd-section-head"><h2>Категории документов</h2></div>
            <div class="rd-cats__grid">
                <a class="rd-cat<?= $category === 0 ? ' is-active' : '' ?>" href="/repo<?= $query !== '' ? '?q=' . rawurlencode($query) : '' ?>">
                    <span class="rd-cat__icon"><?= $docIcon ?></span>
                    <span class="rd-cat__name">Все документы</span>
                    <span class="rd-cat__count"><?= $totalCount ?></span>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a class="rd-cat<?= (int) $cat['id'] === $category ? ' is-active' : '' ?>" href="/repo?category=<?= (int) $cat['id'] ?><?= $query !== '' ? '&q=' . rawurlencode($query) : '' ?>">
                        <span class="rd-cat__icon"><?= $docIcon ?></span>
                        <span class="rd-cat__name"><?= htmlspecialchars((string) $cat['label'], ENT_QUOTES) ?></span>
                        <span class="rd-cat__arrow">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="rd-list">
        <div class="rd-section-head">
            <h2><?= ($query !== '' || $category > 0) ? 'Найдено: ' . count($files) : 'Все документы' ?></h2>
            <?php if ($query !== '' || $category > 0): ?><a class="rd-reset" href="/repo">Сбросить фильтры ↺</a><?php endif; ?>
        </div>
        <?php if (empty($files)): ?>
            <div class="rd-empty"><?= ($query !== '' || $category > 0) ? 'По вашему запросу ничего не найдено.' : 'В хранилище пока нет файлов.' ?></div>
        <?php else: ?>
            <div class="rd-grid">
                <?php foreach ($files as $f): ?>
                    <article class="rd-doc">
                        <div class="rd-doc__head">
                            <span class="rd-doc__ext"><?= htmlspecialchars($extBadge($f), ENT_QUOTES) ?></span>
                            <?php if (!empty($f['category'])): ?><span class="rd-doc__cat"><?= htmlspecialchars((string) $f['category'], ENT_QUOTES) ?></span><?php endif; ?>
                        </div>
                        <time class="rd-doc__date"><?= htmlspecialchars(date('d.m.Y', strtotime((string) $f['created_at'])), ENT_QUOTES) ?></time>
                        <h3 class="rd-doc__title"><?= htmlspecialchars((string) $f['title'], ENT_QUOTES) ?></h3>
                        <div class="rd-doc__meta"><?= htmlspecialchars($extBadge($f) . ' · ' . Format::fileSize((int) $f['size']), ENT_QUOTES) ?><?= (int) $f['download_count'] > 0 ? ' · скачано ' . (int) $f['download_count'] : '' ?></div>
                        <?php if (!empty($f['description'])): ?>
                            <p class="rd-doc__desc"><?= htmlspecialchars(mb_substr((string) $f['description'], 0, 120), ENT_QUOTES) ?></p>
                        <?php endif; ?>
                        <div class="rd-doc__actions">
                            <a class="rd-btn rd-btn--primary" href="/repo/download/<?= (int) $f['id'] ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15"><path d="M12 4v11m0 0 4-4m-4 4-4-4"/><path d="M5 19h14"/></svg>
                                Скачать
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($popular) || !empty($latest)): ?>
        <div class="rd-columns">
            <?php if (!empty($popular)): ?>
                <section class="rd-col">
                    <h2 class="rd-col__title">Популярные документы</h2>
                    <ol class="rd-col__list rd-col__list--num">
                        <?php foreach ($popular as $i => $f): ?>
                            <li>
                                <span class="rd-col__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <span class="rd-col__body">
                                    <a href="/repo/download/<?= (int) $f['id'] ?>"><?= htmlspecialchars((string) $f['title'], ENT_QUOTES) ?></a>
                                    <span class="rd-col__meta">Скачано <?= (int) $f['download_count'] ?> раз</span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>
            <?php if (!empty($latest)): ?>
                <section class="rd-col">
                    <h2 class="rd-col__title">Последние публикации</h2>
                    <ol class="rd-col__list">
                        <?php foreach ($latest as $f): ?>
                            <li>
                                <span class="rd-col__date"><span><?= htmlspecialchars(date('d', strtotime((string) $f['created_at'])), ENT_QUOTES) ?></span><?= htmlspecialchars(mb_strtoupper(['ЯНВ','ФЕВ','МАР','АПР','МАЙ','ИЮН','ИЮЛ','АВГ','СЕН','ОКТ','НОЯ','ДЕК'][(int) date('n', strtotime((string) $f['created_at'])) - 1]), ENT_QUOTES) ?></span>
                                <span class="rd-col__body">
                                    <a href="/repo/download/<?= (int) $f['id'] ?>"><?= htmlspecialchars((string) $f['title'], ENT_QUOTES) ?></a>
                                    <span class="rd-col__meta"><?= htmlspecialchars($extBadge($f) . ' · ' . Format::fileSize((int) $f['size']), ENT_QUOTES) ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/layout/bottom.php'; ?>
