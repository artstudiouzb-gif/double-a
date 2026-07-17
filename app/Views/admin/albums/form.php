<?php

use App\Core\Csrf;
use App\Models\Language;

/** @var array $album */
/** @var array $images */
/** @var array $translations */

$pageTitle = 'Альбом: ' . $album['title'];
$activeNav = 'albums';
require __DIR__ . '/../layout/header.php';

$defaultCode = Language::defaultCode();
$translationLangs = array_values(array_filter(
    Language::active(),
    static fn (array $l): bool => (string) $l['code'] !== $defaultCode
));
?>
<p><a href="/admin/albums" class="btn btn--small">← Все альбомы</a>
   <a href="/albums/<?= htmlspecialchars((string) $album['slug'], ENT_QUOTES) ?>" class="btn btn--small" target="_blank" rel="noopener">Открыть на сайте</a></p>

<div class="form-card" style="margin-bottom:20px;">
    <h2 style="margin-top:0;">Свойства альбома</h2>
    <form method="post" action="/admin/albums/<?= (int) $album['id'] ?>/update" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="title">Название</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars((string) $album['title'], ENT_QUOTES) ?>" required>
        </div>
        <div class="form-field">
            <label for="description">Описание (необязательно)<?php if ($translationLangs): ?> <span class="form-hint" style="font-weight:400;">(основной язык)</span><?php endif; ?></label>
            <textarea id="description" name="description" rows="3"><?= htmlspecialchars((string) ($album['description'] ?? ''), ENT_QUOTES) ?></textarea>
        </div>
        <?php if ($translationLangs): ?>
            <div data-lang-tabs style="border:1px solid var(--admin-border,#e3e6ea);border-radius:8px;padding:12px;">
                <div class="lang-tabs">
                    <?php foreach ($translationLangs as $i => $lang): ?>
                        <button type="button" class="lang-tab-btn <?= $i === 0 ? 'is-active' : '' ?>" data-lang-target="<?= htmlspecialchars($lang['code'], ENT_QUOTES) ?>">
                            <?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($translationLangs as $i => $lang): ?>
                    <?php $code = (string) $lang['code']; $t = $translations[$code] ?? []; ?>
                    <div class="lang-tab-panel <?= $i === 0 ? 'is-active' : '' ?>" data-lang-panel="<?= htmlspecialchars($code, ENT_QUOTES) ?>">
                        <p class="form-hint">Перевод для языка «<?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>». Пустые поля на сайте заменяются версией основного языка.</p>
                        <div class="form-field">
                            <label>Название</label>
                            <input type="text" name="translations[<?= $code ?>][title]" value="<?= htmlspecialchars((string) ($t['title'] ?? ''), ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>Описание</label>
                            <textarea name="translations[<?= $code ?>][description]" rows="3"><?= htmlspecialchars((string) ($t['description'] ?? ''), ENT_QUOTES) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="form-field">
            <label for="cover_url">Обложка (URL)</label>
            <input type="text" id="cover_url" name="cover_url" value="<?= htmlspecialchars((string) $album['cover_url'], ENT_QUOTES) ?>">
            <button type="button" class="btn btn--small" data-media-pick data-media-target="[name='cover_url']">Из медиатеки</button>
            <span class="form-hint">Пусто — обложкой станет первое фото альбома.</span>
        </div>
        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="is_published" name="is_published" value="1" <?= (int) $album['is_published'] === 1 ? 'checked' : '' ?>>
            <label for="is_published">Опубликован (виден на сайте)</label>
        </div>
        <div class="form-field form-field--checkbox">
            <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= (int) ($album['is_featured'] ?? 0) === 1 ? 'checked' : '' ?>>
            <label for="is_featured">Показать на главной (блок «Медиа»)</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
        </div>
    </form>
</div>

<div class="form-card" style="margin-bottom:20px;">
    <h2 style="margin-top:0;">Добавить фото</h2>
    <form method="post" action="/admin/albums/<?= (int) $album['id'] ?>/images/add" class="form-grid">
        <?= Csrf::field() ?>
        <div class="form-field">
            <label for="image_url">Изображение (URL)</label>
            <input type="text" id="image_url" name="image_url" required>
            <button type="button" class="btn btn--small" data-media-pick data-media-target="[name='image_url']">Из медиатеки</button>
        </div>
        <div class="form-field">
            <label for="caption">Подпись (необязательно)</label>
            <input type="text" id="caption" name="caption" maxlength="255">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Добавить фото</button>
        </div>
    </form>
</div>

<h2>Фотографии (<?= count($images) ?>)</h2>
<?php if (empty($images)): ?>
    <p class="form-hint">В альбоме пока нет фотографий.</p>
<?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;">
        <?php foreach ($images as $img): ?>
            <div class="form-card" style="padding:10px;">
                <img src="<?= htmlspecialchars((string) $img['image_url'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) $img['caption'], ENT_QUOTES) ?>" style="width:100%;height:120px;object-fit:cover;border-radius:8px;" loading="lazy">
                <?php if ($img['caption'] !== ''): ?>
                    <p class="form-hint" style="margin:8px 0 0;"><?= htmlspecialchars((string) $img['caption'], ENT_QUOTES) ?></p>
                <?php endif; ?>
                <form method="post" action="/admin/albums/<?= (int) $album['id'] ?>/images/<?= (int) $img['id'] ?>/delete" style="margin-top:8px;">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn--small btn--danger">Убрать</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
