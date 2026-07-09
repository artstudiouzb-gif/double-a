<?php
/** @var array $data */
$title = $data['title'] ?? '';
$items = $data['items'] ?? [];
?>
<div class="block-media">
    <?php if ($title !== ''): ?><h2 class="block-media__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <?php if (empty($items)): ?>
        <p class="block-media__empty">Медиаматериалы ещё не добавлены.</p>
    <?php else: ?>
        <div class="media-list">
            <?php foreach ($items as $item): ?>
                <?php
                $url = trim((string) ($item['url'] ?? ''));
                $tag = $url !== '' ? 'a' : 'div';
                $href = $url !== '' ? ' href="' . htmlspecialchars($url, ENT_QUOTES) . '"' : '';
                ?>
                <<?= $tag ?> class="media-item"<?= $href ?>>
                    <?php if (!empty($item['icon_svg'])): ?>
                        <span class="media-item__icon" aria-hidden="true"><?= $item['icon_svg'] ?></span>
                    <?php endif; ?>
                    <span class="media-item__body">
                        <span class="media-item__label"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES) ?></span>
                        <?php if (!empty($item['action'])): ?><span class="media-item__action"><?= htmlspecialchars((string) $item['action'], ENT_QUOTES) ?> →</span><?php endif; ?>
                    </span>
                </<?= $tag ?>>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
