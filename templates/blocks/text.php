<?php
/** @var array $data */
$title = $data['title'] ?? '';
$content = $data['content'] ?? '';
?>
<div class="block-text">
    <?php if ($title !== ''): ?>
        <h2 class="block-text__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2>
    <?php endif; ?>
    <div class="block-text__content"><?= $content ?></div>
</div>
