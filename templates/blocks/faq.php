<?php
/** @var array $data */
$title = $data['title'] ?? '';
$items = $data['items'] ?? [];
?>
<div class="block-faq">
    <?php if ($title !== ''): ?><h2 class="block-faq__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <div class="block-faq__list">
        <?php foreach ($items as $item): ?>
            <details class="faq-item">
                <summary class="faq-item__q"><?= htmlspecialchars($item['question'] ?? '', ENT_QUOTES) ?></summary>
                <?php // Ответ уже прошёл TextProcessor (типограф + санитайзер) при сохранении. ?>
                <div class="faq-item__a"><?= $item['answer'] ?? '' ?></div>
            </details>
        <?php endforeach; ?>
    </div>
</div>
