<?php
/** @var array $data */
$title = $data['title'] ?? '';
$text = $data['text'] ?? '';
$image = (string) ($data['image'] ?? '');
$btnText = (string) ($data['button_text'] ?? '');
$btnUrl = (string) ($data['button_url'] ?? '');
if ($btnUrl !== '' && !\App\Core\UrlGuard::isSafeLink($btnUrl)) {
    $btnUrl = '';
}
// Стиль: dark — тёмная подложка поверх фото (по умолчанию),
// light — светлый сплит: текст слева, фото справа с растворением.
$variant = ($data['style'] ?? 'dark') === 'light' ? 'light' : 'dark';
?>
<?php if ($variant === 'light'): ?>
<div class="block-banner block-banner--light">
    <div class="block-banner__inner">
        <?php if ($title !== ''): ?><h1 class="block-banner__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1><?php endif; ?>
        <?php if ($text !== ''): ?><p class="block-banner__text"><?= htmlspecialchars($text, ENT_QUOTES) ?></p><?php endif; ?>
        <?php if ($btnText !== '' && $btnUrl !== ''): ?>
            <a class="block-banner__button" href="<?= htmlspecialchars($btnUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($btnText, ENT_QUOTES) ?></a>
        <?php endif; ?>
    </div>
    <?php if ($image !== ''): ?><span class="block-banner__photo" style="background-image:url('<?= htmlspecialchars($image, ENT_QUOTES) ?>')"></span><?php endif; ?>
</div>
<?php else: ?>
<?php $style = $image !== '' ? ' style="background-image:linear-gradient(rgba(15,23,42,.55),rgba(15,23,42,.55)),url(' . htmlspecialchars($image, ENT_QUOTES) . ')"' : ''; ?>
<div class="block-banner<?= $image !== '' ? ' block-banner--image' : '' ?>"<?= $style ?>>
    <div class="block-banner__inner">
        <?php if ($title !== ''): ?><h2 class="block-banner__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h2><?php endif; ?>
        <?php if ($text !== ''): ?><p class="block-banner__text"><?= htmlspecialchars($text, ENT_QUOTES) ?></p><?php endif; ?>
        <?php if ($btnText !== '' && $btnUrl !== ''): ?>
            <a class="block-banner__button" href="<?= htmlspecialchars($btnUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($btnText, ENT_QUOTES) ?></a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
