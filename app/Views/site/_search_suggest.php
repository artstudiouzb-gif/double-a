<?php

/**
 * Подсказки живого поиска: короткий список найденного под полем ввода.
 * Отдаётся фрагментом (SearchController::suggest) и вставляется как есть.
 *
 * @var list<array{type:string,title:string,url:string,excerpt:string}> $results
 * @var string $query
 * @var string $allUrl
 */
?>
<?php if ($results === []): ?>
    <p class="search-suggest__empty"><?= htmlspecialchars(t('Ничего не найдено'), ENT_QUOTES) ?></p>
<?php else: ?>
    <ul class="search-suggest__list" role="listbox">
        <?php foreach ($results as $item): ?>
            <li role="option">
                <a class="search-suggest__item" href="<?= htmlspecialchars($item['url'], ENT_QUOTES) ?>">
                    <span class="search-suggest__title"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></span>
                    <span class="search-suggest__type"><?= htmlspecialchars($item['type'], ENT_QUOTES) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <a class="search-suggest__all" href="<?= htmlspecialchars($allUrl, ENT_QUOTES) ?>">
        <?= htmlspecialchars(t('Все результаты'), ENT_QUOTES) ?> →
    </a>
<?php endif; ?>
