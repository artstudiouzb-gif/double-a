<?php

use App\Core\UrlGuard;

/** @var array $data */
$title = $data['title'] ?? '';
$eyebrow = trim((string) ($data['eyebrow'] ?? ''));
$subtitle = $data['subtitle'] ?? '';
$image = trim((string) ($data['image'] ?? ''));
$video = trim((string) ($data['video_url'] ?? ''));
$btnText = trim((string) ($data['button_text'] ?? ''));
$btnUrl = trim((string) ($data['button_url'] ?? ''));
$btn2Text = trim((string) ($data['button2_text'] ?? ''));
$btn2Url = trim((string) ($data['button2_url'] ?? ''));
$vBtnText = trim((string) ($data['video_button_text'] ?? ''));
$vBtnUrl = trim((string) ($data['video_button_url'] ?? ''));
$hasMedia = $image !== '' || $video !== '';
?>
<?php
$heroWidth = ($data['width'] ?? 'full') === 'standard' ? 'standard' : 'full';
$heroHeight = ($data['height'] ?? 'regular') === 'full' ? 'full' : 'regular';
?>
<div class="block-hero<?= $hasMedia ? ' block-hero--media' : '' ?><?= $video !== '' ? ' block-hero--video' : '' ?> block-hero--w-<?= $heroWidth ?> block-hero--h-<?= $heroHeight ?>">
    <?php if ($video !== ''): ?>
        <video class="block-hero__video" autoplay muted loop playsinline <?= $image !== '' ? 'poster="' . htmlspecialchars($image, ENT_QUOTES) . '"' : '' ?> aria-hidden="true">
            <source src="<?= htmlspecialchars($video, ENT_QUOTES) ?>" type="video/mp4">
        </video>
    <?php elseif ($image !== ''): ?>
        <div class="block-hero__media" style="background-image:url('<?= htmlspecialchars($image, ENT_QUOTES) ?>')" aria-hidden="true"></div>
    <?php endif; ?>
    <?php if ($hasMedia): ?><div class="block-hero__scrim" aria-hidden="true"></div><?php endif; ?>
    <div class="block-hero__inner">
        <div class="block-hero__text">
            <?php if ($eyebrow !== ''): ?><span class="block-hero__eyebrow"><?= htmlspecialchars($eyebrow, ENT_QUOTES) ?></span><?php endif; ?>
            <?php if ($title !== ''): ?><h1 class="block-hero__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1><?php endif; ?>
            <?php if ($subtitle !== ''): ?><p class="block-hero__subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES) ?></p><?php endif; ?>
            <?php if (($btnText !== '' && $btnUrl !== '') || ($btn2Text !== '' && $btn2Url !== '') || ($vBtnText !== '')): ?>
            <div class="block-hero__actions">
                <?php if ($btnText !== '' && $btnUrl !== '' && UrlGuard::isSafeLink($btnUrl)): ?>
                    <a class="block-hero__button" href="<?= htmlspecialchars($btnUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($btnText, ENT_QUOTES) ?> →</a>
                <?php endif; ?>
                <?php if ($btn2Text !== '' && $btn2Url !== '' && UrlGuard::isSafeLink($btn2Url)): ?>
                    <a class="block-hero__button block-hero__button--ghost" href="<?= htmlspecialchars($btn2Url, ENT_QUOTES) ?>"><?= htmlspecialchars($btn2Text, ENT_QUOTES) ?> →</a>
                <?php endif; ?>
                <?php if ($vBtnText !== ''): ?>
                    <?php $vSafe = $vBtnUrl !== '' && UrlGuard::isSafeLink($vBtnUrl); ?>
                    <<?= $vSafe ? 'a' : 'span' ?> class="block-hero__play"<?= $vSafe ? ' href="' . htmlspecialchars($vBtnUrl, ENT_QUOTES) . '"' : '' ?>>
                        <span class="block-hero__play-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
                        <span class="block-hero__play-label"><?= htmlspecialchars($vBtnText, ENT_QUOTES) ?></span>
                    </<?= $vSafe ? 'a' : 'span' ?>>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
