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
$layoutLabels = ['standard' => 'Стандартный', 'gallery' => 'Галерея', 'video' => 'Видео', 'side_image' => 'Изображение сбоку', 'premium' => 'Премиум (тёмный hero)'];

$action = $isEdit ? '/admin/news/' . (int) $news['id'] . '/edit' : '/admin/news/create';
$publishedAtValue = '';
if (!empty($news['published_at'])) {
    $publishedAtValue = str_replace(' ', 'T', substr((string) $news['published_at'], 0, 16));
}
$defaultCode = Language::defaultCode();
$languages = Language::active();
?>
<?php if ($error): ?><div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
<?php if ($isEdit): ?>
    <div style="margin-bottom:16px;"><a class="btn btn--small" href="/admin/revisions/news/<?= (int) $news['id'] ?>">История версий</a></div>
<?php endif; ?>
<form method="post" action="<?= $action ?>" enctype="multipart/form-data" data-content-draft="news:<?= $isEdit ? (int) $news['id'] : 'new' ?>" data-record-updated="<?= htmlspecialchars((string) ($news['updated_at'] ?? ''), ENT_QUOTES) ?>">
    <?= Csrf::field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="expected_updated_at" value="<?= htmlspecialchars((string) $news['updated_at'], ENT_QUOTES) ?>">
        <input type="hidden" name="expected_lock_version" value="<?= (int) ($news['lock_version'] ?? 1) ?>">
    <?php endif; ?>
    <div class="entry-grid">
    <div class="entry-main">
    <div class="form-card">
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
    </div>

    <div class="form-card">
        <h2 style="margin-top:0;">Медиа и отображение</h2>
        <div class="form-grid">
        <?= \App\Core\AdminUi::imageField('image_url', $news['image'] ?? '', [
            'label' => 'Изображение (обложка)',
            'file' => 'image_file',
            'hint' => 'Выберите из медиабиблиотеки, вставьте URL или загрузите файл.',
        ]) ?>

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

        <details class="form-field" style="border:1px solid var(--admin-border,#e1e3e8);border-radius:8px;padding:14px;">
            <summary style="cursor:pointer;font-weight:700;">Детальная страница: тезисы, мероприятие, документы</summary>
            <div class="form-field" style="margin-top:14px;">
                <label for="badge">Бейдж категории (напр. МЕРОПРИЯТИЕ)</label>
                <input type="text" id="badge" name="badge" value="<?= htmlspecialchars($news['badge'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="form-field">
                <label for="source_note">Подпись источника</label>
                <input type="text" id="source_note" name="source_note" value="<?= htmlspecialchars($news['source_note'] ?? '', ENT_QUOTES) ?>" placeholder="Подготовлено пресс-службой Агентства">
            </div>
            <div class="form-field">
                <label for="press_release_url">Пресс-релиз (URL файла)</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" id="press_release_url" name="press_release_url" value="<?= htmlspecialchars($news['press_release_url'] ?? '', ENT_QUOTES) ?>" placeholder="/uploads/public/press.pdf" style="flex:1;">
                    <button type="button" class="btn btn--secondary btn--small" data-media-pick data-media-target="#press_release_url" data-media-type="all_files">Выбрать из медиа</button>
                </div>
            </div>
            <div class="form-field">
                <label for="key_points">Ключевые тезисы (по одному на строку)</label>
                <textarea id="key_points" name="key_points" rows="4"><?= htmlspecialchars($news['key_points'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <div class="form-field">
                <label for="event_meta">О мероприятии (по одной строке: дата, место, участники, теги)</label>
                <textarea id="event_meta" name="event_meta" rows="4"><?= htmlspecialchars($news['event_meta'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <div>
                <label>Документы</label>
                <?php $ndDocs = json_decode((string) ($news['docs'] ?? '[]'), true) ?: []; ?>
                <div data-repeater="docs">
                    <?php foreach ($ndDocs as $i => $doc): ?>
                        <div class="repeater-row">
                            <div class="form-field"><label>Название</label><input type="text" name="docs[<?= $i ?>][title]" value="<?= htmlspecialchars($doc['title'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field"><label>Мета (PDF · 2.4 МБ)</label><input type="text" name="docs[<?= $i ?>][meta]" value="<?= htmlspecialchars($doc['meta'] ?? '', ENT_QUOTES) ?>"></div>
                            <div class="form-field">
                                <label>Ссылка</label>
                                <div style="display:flex;gap:8px;">
                                    <input type="text" name="docs[<?= $i ?>][url]" value="<?= htmlspecialchars($doc['url'] ?? '', ENT_QUOTES) ?>" style="flex:1;">
                                    <button type="button" class="btn btn--secondary btn--small" data-media-pick data-media-target="[name='docs[<?= $i ?>][url]']" data-media-type="all_files">Выбрать</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <template data-repeater-template="docs">
                    <div class="form-field"><label>Название</label><input type="text" name="docs[__INDEX__][title]"></div>
                    <div class="form-field"><label>Мета (PDF · 2.4 МБ)</label><input type="text" name="docs[__INDEX__][meta]"></div>
                    <div class="form-field">
                        <label>Ссылка</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" name="docs[__INDEX__][url]" style="flex:1;">
                            <button type="button" class="btn btn--secondary btn--small" data-media-pick data-media-target="[name='docs[__INDEX__][url]']" data-media-type="all_files">Выбрать</button>
                        </div>
                    </div>
                    <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove>Удалить</button>
                </template>
                <div class="repeater-actions"><button type="button" class="btn btn--small" data-repeater-add="docs">+ Добавить документ</button></div>
            </div>
        </details>

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
        </div>
    </div>
    </div>

    <aside class="entry-side">
        <div class="form-card">
            <h2 style="margin-top:0;">Публикация</h2>
            <div class="form-grid">
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
                <div class="form-field">
                    <label for="slug">ЧПУ (slug) — общий для всех языков</label>
                    <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($news['slug'] ?? '', ENT_QUOTES) ?>" placeholder="оставьте пустым для автогенерации">
                </div>
            </div>
            <div class="form-actions form-actions--sticky">
                <button type="submit" class="btn btn--primary">Сохранить</button>
                <a href="/admin/news" class="btn">Отмена</a>
                <?php if ($isEdit): ?>
                    <a href="/admin/news/<?= (int) $news['id'] ?>/preview" class="btn" target="_blank" rel="noopener">Предпросмотр ↗</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isEdit): ?>
            <?php
            // Кнопка публикации отправляет отдельную форму (см. #news-social-form
            // ниже, вне основной формы — вложенные <form> недопустимы).
            $socialPosts = \App\Models\SocialPost::forNews((int) $news['id']);
            $readyNetworks = \App\Core\SocialSettings::readyNetworks();
            $netLabels = ['telegram' => 'Telegram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram'];
            $stBadge = ['sent' => 'published', 'failed' => 'danger', 'pending' => 'draft'];
            ?>
            <div class="form-card" style="margin-top:20px;">
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
                                        <td><span class="badge badge--<?= $stBadge[$sp['status']] ?? 'draft' ?>"><?= htmlspecialchars((string) $sp['status'], ENT_QUOTES) ?></span></td>
                                        <td><?= (int) $sp['attempts'] ?></td>
                                        <td><?= htmlspecialchars((string) ($sp['remote_id'] ?: ($sp['last_error'] ?? '')), ENT_QUOTES) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <button type="submit" form="news-social-form" class="btn btn--social">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Опубликовать в соцсетях сейчас
                    </button>
                    <p class="form-hint" style="margin-bottom:0;">Пытается отправить сразу; что не ушло — досылает воркер по Cron.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
    </div>
</form>
<?php if ($isEdit): ?>
    <?php // Отдельная форма публикации в соцсети — вне основной формы (вложение форм недопустимо). ?>
    <form id="news-social-form" method="post" action="/admin/news/<?= (int) $news['id'] ?>/social" hidden>
        <?= Csrf::field() ?>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../layout/footer.php'; ?>
