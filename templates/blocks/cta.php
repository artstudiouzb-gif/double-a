<?php
/** @var array $data */
$title = $data['title'] ?? '';
$text = $data['text'] ?? '';
$buttonText = $data['button_text'] ?? '';
$buttonUrl = $data['button_url'] ?? '#';
?>
<div class="block-cta">
    <?php if ($title !== ''): ?><h2><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
    <?php if ($text !== ''): ?><p><?= htmlspecialchars($text, ENT_QUOTES) ?></p><?php endif; ?>
    <?php if ($buttonText !== ''): ?>
        <a class="block-cta__button" href="<?= htmlspecialchars($buttonUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($buttonText, ENT_QUOTES) ?></a>
    <?php endif; ?>
</div>
