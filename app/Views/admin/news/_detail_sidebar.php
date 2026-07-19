<?php

/** @var array|null $news */
$ndDocs = json_decode((string) ($news['docs'] ?? '[]'), true) ?: [];
$legacyPressUrl = trim((string) ($news['press_release_url'] ?? ''));
if ($legacyPressUrl !== '') {
    $alreadyInDocs = false;
    foreach ($ndDocs as $doc) {
        if (is_array($doc) && trim((string) ($doc['url'] ?? '')) === $legacyPressUrl) {
            $alreadyInDocs = true;
            break;
        }
    }
    if (!$alreadyInDocs) {
        // Старое отдельное поле упразднено: переносим его значение в документы
        // при следующем сохранении новости, не теряя ранее загруженный файл.
        $ndDocs[] = ['title' => 'Пресс-релиз', 'meta' => '', 'url' => $legacyPressUrl];
    }
}
?>
<div class="form-card news-detail-sidebar">
    <h2>Детальная страница</h2>
    <p class="form-hint news-detail-sidebar__hint">Тезисы, мероприятие и документы</p>

    <div class="form-grid">
        <div class="form-field">
            <label for="source_note">Подпись источника</label>
            <input type="text" id="source_note" name="source_note" value="<?= htmlspecialchars($news['source_note'] ?? '', ENT_QUOTES) ?>" placeholder="Подготовлено пресс-службой Агентства">
        </div>

        <div class="form-field">
            <label for="key_points">Ключевые тезисы</label>
            <textarea id="key_points" name="key_points" rows="5" placeholder="По одному тезису на строку"><?= htmlspecialchars($news['key_points'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <div class="form-field">
            <label for="event_meta">О мероприятии</label>
            <textarea id="event_meta" name="event_meta" rows="5" placeholder="Дата, место, участники и теги — по одной строке"><?= htmlspecialchars($news['event_meta'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <div class="news-detail-sidebar__docs">
            <h3>Документы</h3>
            <div data-repeater="docs">
                <?php foreach ($ndDocs as $i => $doc): ?>
                    <div class="repeater-row">
                        <div class="form-field"><label>Название</label><input type="text" name="docs[<?= $i ?>][title]" value="<?= htmlspecialchars($doc['title'] ?? '', ENT_QUOTES) ?>"></div>
                        <div class="form-field"><label>Мета</label><input type="text" name="docs[<?= $i ?>][meta]" value="<?= htmlspecialchars($doc['meta'] ?? '', ENT_QUOTES) ?>" placeholder="PDF · 2.4 МБ"></div>
                        <div class="form-field">
                            <label>Ссылка</label>
                            <input type="text" name="docs[<?= $i ?>][url]" value="<?= htmlspecialchars($doc['url'] ?? '', ENT_QUOTES) ?>">
                            <button type="button" class="btn btn--secondary btn--small news-detail-sidebar__pick" data-media-pick data-media-target="[name='docs[<?= $i ?>][url]']" data-media-type="all_files">Выбрать</button>
                        </div>
                        <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove><?= \App\Core\AdminUi::icon('trash') ?>Удалить</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-repeater-template="docs">
                <div class="form-field"><label>Название</label><input type="text" name="docs[__INDEX__][title]"></div>
                <div class="form-field"><label>Мета</label><input type="text" name="docs[__INDEX__][meta]" placeholder="PDF · 2.4 МБ"></div>
                <div class="form-field">
                    <label>Ссылка</label>
                    <input type="text" name="docs[__INDEX__][url]">
                    <button type="button" class="btn btn--secondary btn--small news-detail-sidebar__pick" data-media-pick data-media-target="[name='docs[__INDEX__][url]']" data-media-type="all_files">Выбрать</button>
                </div>
                <button type="button" class="btn btn--small btn--danger repeater-row__remove" data-repeater-remove><?= \App\Core\AdminUi::icon('trash') ?>Удалить</button>
            </template>
            <div class="repeater-actions">
                <button type="button" class="btn btn--small" data-repeater-add="docs"><?= \App\Core\AdminUi::icon('plus') ?>Добавить документ</button>
            </div>
        </div>
    </div>
</div>
