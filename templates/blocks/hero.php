<?php

use App\Core\UrlGuard;

/** @var array $data */
$title = $data['title'] ?? '';
$subtitle = $data['subtitle'] ?? '';
$image = trim((string) ($data['image'] ?? ''));
$btnText = trim((string) ($data['button_text'] ?? ''));
$btnUrl = trim((string) ($data['button_url'] ?? ''));
?>
<div class="block-hero<?= $image !== '' ? ' block-hero--image' : '' ?>">
    <?php if ($image !== ''): ?>
        <div class="block-hero__media" style="background-image:url('<?= htmlspecialchars($image, ENT_QUOTES) ?>')" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="block-hero__inner">
        <div class="block-hero__text">
            <?php if ($title !== ''): ?><h1 class="block-hero__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1><?php endif; ?>
            <?php if ($subtitle !== ''): ?><p class="block-hero__subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES) ?></p><?php endif; ?>
            <?php if ($btnText !== '' && $btnUrl !== '' && UrlGuard::isSafeLink($btnUrl)): ?>
                <a class="block-hero__button" href="<?= htmlspecialchars($btnUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($btnText, ENT_QUOTES) ?> →</a>
            <?php endif; ?>
        </div>
    </div>
</div>
