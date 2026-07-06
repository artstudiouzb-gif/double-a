<?php

use App\Core\Csrf;
use App\Models\Language;

$isEdit = !empty($news['id']);
$pageTitle = $isEdit ? 'Редактирование новости' : 'Новая новость';
$activeNav = 'news';
require __DIR__ . '/../layout/header.php';

/** @var array|null $news */
/** @var array $translations */
/** @var string|null $error */
/** @var array $gallery */
$gallery = $gallery ?? [];
$layout = $news['layout_type'] ?? 'standard';
$layoutLabels = ['standard' => 'Стандартный', 'gallery' => 'Галерея', 'video' => 'Видео', 'side_image' => 'Изображение сбоку'];

$action = $isEdit ? '/admin/news/' . (int) $news['id'] . '/edit' : '/admin/news/create';
$publishedAtValue = '';
if (!empty($news['published_at'])) {
    $publishedAtValue = str_replace(' ', 'T', substr((string) $news['published_at'], 0, 16));
}
$defaultCode = Language::defaultCode();
$languages = Language::active();
?>
<?php if ($isEdit): ?>
    <?php
    $socialPosts = \App\Models\SocialPost::forNews((int) $news['id']);
    $readyNetworks = \App\Core\SocialSettings::readyNetworks();
    $netLabels = ['facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram'];
    ?>
    <div class="form-card" style="margin-bottom:20px;">
        <h2 style="margin-top:0;">Соцсети</h2>
        <?php if (empty($readyNetworks)): ?>
            <p class="form-hint">Ни одна сеть не настроена. Включите их в разделе
                <a href="/admin/social">«Соцсети»</a>.</p>
        <?php else: ?>
            <?php if (!empty($socialPosts)): ?>
                <table class="data-table" style="margin-bottom:12px;">
                    <thead><tr><th>Сеть</th><th>Статус</th><th>Попыток</th><th>Инфо</th></tr></thead>
                    <tbody>
                        <?php foreach ($socialPosts as $sp): ?>
                            <tr>
                                <td><?= htmlspecialchars($netLabels[$sp['network']] ?? $sp['network'], ENT_QUOTES) ?></td>
                                <td><span class="badge badge--<?= $sp['status'] === 'sent' ? 'published' : ($sp['status'] === 'failed' ? 'draft' : 'draft') ?>"><?= htmlspecialchars((string) $sp['status'], ENT_QUOTES) ?></span></td>
                                <td><?= (int) $sp['attempts'] ?></td>
                                <td><?= htmlspecialchars((string) ($sp['remote_id'] ?: ($sp['last_error'] ?? '')), ENT_QUOTES) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <form method="post" action="/admin/news/<?= (int) $news['id'] ?>/social">
                <?= Csrf::field() ?>
                <button type="submit" class="btn">Опубликовать в соцсетях сейчас</button>
                <span class="form-hint">Ставит в очередь; отправку выполняет воркер по Cron.</span>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
<div class="form-card">
    <?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
    <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="form-grid">
        <?= Csrf::field() ?>

        <div data-lang-tabs>
            <div class="lang-tabs">
                <?php foreach ($languages as $i => $lang): ?>
                    <button type="button" class="lang-tab-btn <?= $i === 0 ? 'is-active' : '' ?>" data-lang-target="<?= htmlspecialchars($lang['code'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>
                        <?php if ($lang['code'] === $defaultCode): ?><span class="lang-tab-btn__badge">(основной)</span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($languages as $i => $lang): ?>
                <?php $code = (string) $lang['code']; $isDefault = $code === $defaultCode; ?>
                <div class="lang-tab-panel <?= $i === 0 ? 'is-active' : '' ?>" data-lang-panel="<?= htmlspecialchars($code, ENT_QUOTES) ?>">
                    <?php if ($isDefault): ?>
                        <div class="form-field">
                            <label>Заголовок</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($news['title'] ?? '', ENT_QUOTES) ?>" required>
                        </div>
                        <div class="form-field">
                            <label>Краткое описание</label>
                            <textarea name="excerpt"><?= htmlspecialchars($news['excerpt'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                        <div class="form-field">
                            <label>Текст новости</label>
                            <textarea name="content" data-wysiwyg style="min-height:220px;"><?= htmlspecialchars($news['content'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                        <div class="form-field">
                            <label>SEO: meta title</label>
                            <input type="text" name="meta_title" value="<?= htmlspecialchars($news['meta_title'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>SEO: meta description</label>
                            <input type="text" name="meta_description" value="<?= htmlspecialchars($news['meta_description'] ?? '', ENT_QUOTES) ?>">
                        </div>
                    <?php else: ?>
                        <?php $t = $translations[$code] ?? []; ?>
                        <p class="form-hint">Перевод для языка «<?= htmlspecialchars($lang['name'], ENT_QUOTES) ?>». Пустые поля на сайте заменяются версией основного языка.</p>
                        <div class="form-field">
                            <label>Заголовок</label>
                            <input type="text" name="translations[<?= $code ?>][title]" value="<?= htmlspecialchars($t['title'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>Краткое описание</label>
                            <textarea name="translations[<?= $code ?>][excerpt]"><?= htmlspecialchars($t['excerpt'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                        <div class="form-field">
                            <label>Текст новости</label>
                            <textarea name="translations[<?= $code ?>][content]" data-wysiwyg style="min-height:220px;"><?= htmlspecialchars($t['content'] ?? '', ENT_QUOTES) ?></textarea>
                        </div>
                        <div class="form-field">
                            <label>SEO: meta title</label>
                            <input type="text" name="translations[<?= $code ?>][meta_title]" value="<?= htmlspecialchars($t['meta_title'] ?? '', ENT_QUOTES) ?>">
                        </div>
                        <div class="form-field">
                            <label>SEO: meta description</label>
                            <input type="text" name="translations[<?= $code ?>][meta_description]" value="<?= htmlspecialchars($t['meta_description'] ?? '', ENT_QUOTES) ?>">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <hr style="border:none;border-top:1px solid var(--admin-border);margin:6px 0;">

        <div class="form-field">
            <label for="slug">ЧПУ (slug) — общий для всех языков</label>
            <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($news['slug'] ?? '', ENT_QUOTES) ?>" placeholder="оставьте пустым для автогенерации">
        </div>

        <div class="form-field">
            <label for="image_file">Изображение (файл)</label>
            <input type="file" id="image_file" name="image_file" accept="image/*">
        </div>
        <div class="form-field">
            <label for="image_url">...либо ссылка / из медиабиблиотеки</label>
            <div style="display:flex;gap:8px;">
                <input type="text" id="image_url" name="image_url" value="<?= htmlspecialchars($news['image'] ?? '', ENT_QUOTES) ?>" style="flex:1;">
                <button type="button" class="btn btn--small" data-media-pick data-media-target="#image_url">Медиабиблиотека</button>
            </div>
        </div>

        <div class="form-field">
            <label>Фокальная точка обложки (%, для кадрирования на мобильных)</label>
            <div style="display:flex;gap:12px;">
                <input type="number" name="focal_x" min="0" max="100" placeholder="X (0–100)" value="<?= htmlspecialchars((string) ($news['focal_x'] ?? ''), ENT_QUOTES) ?>" style="max-width:160px;">
                <input type="number" name="focal_y" min="0" max="100" placeholder="Y (0–100)" value="<?= htmlspecialchars((string) ($news['focal_y'] ?? ''), ENT_QUOTES) ?>" style="max-width:160px;">
            </div>
            <span class="form-hint">Оставьте пустым для центрирования (50/50).</span>
        </div>

        <div class="form-field">
            <label for="layout_type">Тип отображения новости</label>
            <select id="layout_type" name="layout_type">
                <?php foreach ($layoutLabels as $lt => $ltLabel): ?>
                    <option value="<?= $lt ?>" <?= $layout === $lt ? 'selected' : '' ?>><?= htmlspecialchars($ltLabel, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="video_url">Ссылка на видео (YouTube)</label>
            <input type="text" id="video_url" name="video_url" value="<?= htmlspecialchars($news['video_url'] ?? '', ENT_QUOTES) ?>" placeholder="https://youtu.be/...">
            <span class="form-hint">Для типа «Видео»: обложка берётся с YouTube, плеер загружается только по клику.</span>
        </div>

        <div class="form-field">
            <label>Галерея фотографий</label>
            <?php if (!empty($gallery)): ?>
                <div class="news-gallery-admin" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:12px;">
                    <?php foreach ($gallery as $gi): ?>
                        <div style="border:1px solid var(--admin-border,#e1e3e8);border-radius:8px;padding:10px;">
                            <img src="<?= htmlspecialchars((string) $gi['path'], ENT_QUOTES) ?>" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:6px;margin-bottom:8px;">
                            <input type="text" name="gallery[<?= (int) $gi['id'] ?>][alt]" value="<?= htmlspecialchars((string) ($gi['alt_text'] ?? ''), ENT_QUOTES) ?>" placeholder="alt-текст" style="width:100%;margin-bottom:6px;">
                            <div style="display:flex;gap:6px;margin-bottom:6px;">
                                <input type="number" name="gallery[<?= (int) $gi['id'] ?>][sort]" value="<?= (int) $gi['sort_order'] ?>" title="порядок" style="width:70px;">
                                <input type="number" name="gallery[<?= (int) $gi['id'] ?>][focal_x]" min="0" max="100" value="<?= htmlspecialchars((string) ($gi['focal_x'] ?? ''), ENT_QUOTES) ?>" placeholder="fx" style="width:60px;">
                                <input type="number" name="gallery[<?= (int) $gi['id'] ?>][focal_y]" min="0" max="100" value="<?= htmlspecialchars((string) ($gi['focal_y'] ?? ''), ENT_QUOTES) ?>" placeholder="fy" style="width:60px;">
                            </div>
                            <label style="font-size:13px;display:flex;gap:6px;align-items:center;">
                                <input type="checkbox" name="gallery[<?= (int) $gi['id'] ?>][delete]" value="1"> удалить
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!$isEdit): ?>
                <span class="form-hint">Сохраните новость, затем можно будет управлять галереей.</span>
            <?php endif; ?>
            <input type="file" name="news_gallery[]" accept="image/*" multiple>
            <span class="form-hint">Можно выбрать несколько фото. Они сжимаются и конвертируются в WebP автоматически.</span>
        </div>

        <div class="form-field">
            <label for="status">Статус</label>
            <select id="status" name="status">
                <option value="draft" <?= ($news['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                <option value="published" <?= ($news['status'] ?? '') === 'published' ? 'selected' : '' ?>>Опубликовано</option>
            </select>
        </div>

        <div class="form-field">
            <label for="published_at">Дата публикации</label>
            <input type="datetime-local" id="published_at" name="published_at" value="<?= htmlspecialchars($publishedAtValue, ENT_QUOTES) ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Сохранить</button>
            <a href="/admin/news" class="btn">Отмена</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
