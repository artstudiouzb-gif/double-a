<?php

use App\Core\UrlGuard;
use App\Core\Video;
use App\Core\Media;

/** @var array $data */
$title = $data['title'] ?? '';
$eyebrow = trim((string) ($data['eyebrow'] ?? ''));
$subtitle = $data['subtitle'] ?? '';
$image = trim((string) ($data['image'] ?? ''));

// Тип фона: none | image | video | youtube. Старые блоки без bg_type
// определяются по заполненным полям (обратная совместимость).
$bgType = (string) ($data['bg_type'] ?? '');
$videoFile = trim((string) ($data['video_url'] ?? ''));
$youtubeId = Video::youtubeId((string) ($data['youtube_url'] ?? ''));
if ($bgType === '') {
    $bgType = $youtubeId !== null ? 'youtube' : ($videoFile !== '' ? 'video' : ($image !== '' ? 'image' : 'none'));
} elseif ($bgType === 'none' && $youtubeId !== null) {
    // Старые сохранения могли оставить режим «Без фона», хотя редактор уже
    // вставил валидную ссылку YouTube. Ссылка — явный источник фона.
    $bgType = 'youtube';
}

$hasMedia = ($bgType === 'image' && $image !== '')
    || ($bgType === 'video' && $videoFile !== '')
    || ($bgType === 'youtube' && $youtubeId !== null);

// Overlay (полупрозрачная заливка поверх медиа) и подложка под текстом.
$hex2rgb = static function (string $hex): string {
    $hex = ltrim($hex, '#');
    return (int) hexdec(substr($hex, 0, 2)) . ',' . (int) hexdec(substr($hex, 2, 2)) . ',' . (int) hexdec(substr($hex, 4, 2));
};
$ovColor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($data['overlay_color'] ?? '')) ? $data['overlay_color'] : '#0b1a30';
$ovOpacity = max(0, min(100, (int) ($data['overlay_opacity'] ?? 55))) / 100;
$panelOn = !empty($data['panel_enabled']);
$panelColor = preg_match('/^#[0-9a-f]{6}$/i', (string) ($data['panel_color'] ?? '')) ? $data['panel_color'] : '#0b1a30';
$panelOpacity = max(0, min(100, (int) ($data['panel_opacity'] ?? 40))) / 100;
$textPos = in_array($data['text_position'] ?? 'left', ['left', 'center', 'right'], true) ? $data['text_position'] : 'left';
$heroText = preg_match('/^#[0-9a-f]{6}$/i', (string) ($data['text_color'] ?? '')) ? $data['text_color'] : '';
$heroBtn = preg_match('/^#[0-9a-f]{6}$/i', (string) ($data['button_color'] ?? '')) ? $data['button_color'] : '';

// Инлайн-стиль контейнера текста: подложка + переопределения цветов через CSS-переменные.
$textStyle = ($panelOn ? 'background: rgba(' . $hex2rgb($panelColor) . ', ' . $panelOpacity . ');' : '')
    . ($heroText !== '' ? '--hero-text:' . $heroText . ';' : '')
    . ($heroBtn !== '' ? '--hero-btn:' . $heroBtn . ';' : '');

// Свой цвет фона под текстом (для hero без фото/видео): полупрозрачный
// градиент выбранного цвета, не зависящий от светлой/тёмной темы. Отдаётся
// на корень hero — под медиа он не виден (там работает overlay), а на hero
// без медиа заменяет фон темы, который иначе менялся при переключении режима.
$heroBg = preg_match('/^#[0-9a-f]{6}$/i', (string) ($data['bg_color'] ?? '')) ? $data['bg_color'] : '';
$heroRootStyle = '';
if ($heroBg !== '') {
    $rgb = $hex2rgb($heroBg);
    // Направление градиента — от стороны с текстом к прозрачному краю.
    $dir = $textPos === 'right' ? '270deg' : ($textPos === 'center' ? '180deg' : '90deg');
    $heroRootStyle = 'background: linear-gradient(' . $dir . ', rgba(' . $rgb . ',.96) 0%, rgba(' . $rgb . ',.92) 42%, rgba(' . $rgb . ',.55) 72%, rgba(' . $rgb . ',.12) 100%)'
        // Инлайновый background перебивает navy темы, и сквозь полупрозрачные
        // участки градиента просвечивал белый фон страницы — hero выглядел
        // светлее задуманного. Под медиа подкладываем сплошной navy-слой;
        // hero без медиа не трогаем (там градиент поверх фона темы задуман).
        . ($hasMedia ? ', linear-gradient(var(--gov-navy, #173a63), var(--gov-navy, #173a63))' : '')
        . ';';
}

$btnText = trim((string) ($data['button_text'] ?? ''));
$btnUrl = trim((string) ($data['button_url'] ?? ''));
$btn2Text = trim((string) ($data['button2_text'] ?? ''));
$btn2Url = trim((string) ($data['button2_url'] ?? ''));
$vBtnText = trim((string) ($data['video_button_text'] ?? ''));
$vBtnUrl = trim((string) ($data['video_button_url'] ?? ''));

// Своя ширина текстовой колонки: px (200–2000) или %/vw (10–100).
// Пусто/невалидно — ширина темы (560/620px). Отдаётся CSS-переменной.
$textWidth = (string) ($data['text_width'] ?? '');
if ($textWidth !== '' && preg_match('/^(\d+(?:\.\d+)?)(px|%|vw)$/', $textWidth, $twParts)) {
    $twValue = (float) $twParts[1];
    $twLimits = $twParts[2] === 'px' ? [200.0, 2000.0] : [10.0, 100.0];
    $twValue = max($twLimits[0], min($twLimits[1], $twValue));
    $twNumber = rtrim(rtrim(number_format($twValue, 1, '.', ''), '0'), '.');
    $heroRootStyle .= '--hero-text-width:' . $twNumber . $twParts[2] . ';';
}

$heroWidth = ($data['width'] ?? 'full') === 'standard' ? 'standard' : 'full';
$heroHeight = in_array($data['height'] ?? 'regular', ['regular', 'full', 'custom'], true) ? $data['height'] : 'regular';
$customHeight = (string) ($data['custom_height'] ?? '720px');
if ($heroHeight === 'custom' && preg_match('/^(\d+(?:\.\d+)?)(px|vh|dvh|rem)$/', $customHeight, $heightParts)) {
    $heightValue = (float) $heightParts[1];
    $heightUnit = $heightParts[2];
    $heightLimits = $heightUnit === 'px' ? [160.0, 2000.0]
        : ($heightUnit === 'rem' ? [10.0, 120.0] : [20.0, 150.0]);
    $heightValue = max($heightLimits[0], min($heightLimits[1], $heightValue));
    $heightNumber = rtrim(rtrim(number_format($heightValue, 1, '.', ''), '0'), '.');
    $heroRootStyle .= '--hero-custom-height:' . $heightNumber . $heightUnit . ';';
}
?>
<?php // Без медиа и без своего фона hero — это просто шапка страницы:
      // карточка с рамкой и подложкой в этой роли читается как чужой блок. ?>
<div class="block-hero<?= $hasMedia ? ' block-hero--media' : '' ?><?= (!$hasMedia && $heroBg === '') ? ' block-hero--plain' : '' ?><?= $heroBg !== '' ? ' block-hero--bgcolor' : '' ?><?= ($bgType === 'video' || $bgType === 'youtube') ? ' block-hero--video' : '' ?> block-hero--w-<?= $heroWidth ?> block-hero--h-<?= $heroHeight ?> block-hero--pos-<?= $textPos ?>"<?= $heroRootStyle !== '' ? ' style="' . $heroRootStyle . '"' : '' ?>>
    <?php if ($bgType === 'video' && $videoFile !== ''): ?>
        <video class="block-hero__video" autoplay muted loop playsinline preload="metadata"
               disablepictureinpicture disableremoteplayback controlslist="nodownload nofullscreen noremoteplayback noplaybackrate"
               tabindex="-1" <?= $image !== '' ? 'poster="' . htmlspecialchars($image, ENT_QUOTES) . '"' : '' ?> aria-hidden="true">
            <source src="<?= htmlspecialchars($videoFile, ENT_QUOTES) ?>" type="video/mp4">
        </video>
    <?php elseif ($bgType === 'youtube' && $youtubeId !== null): ?>
        <div class="block-hero__yt" aria-hidden="true">
            <?php // Hero находится на первом экране: lazy iframe Chrome может
                  // отложить навсегда из-за абсолютного позиционирования фона. ?>
            <iframe src="https://www.youtube-nocookie.com/embed/<?= htmlspecialchars($youtubeId, ENT_QUOTES) ?>?autoplay=1&mute=1&loop=1&playlist=<?= htmlspecialchars($youtubeId, ENT_QUOTES) ?>&controls=0&showinfo=0&modestbranding=1&rel=0&playsinline=1&disablekb=1&fs=0&iv_load_policy=3" title="" tabindex="-1" frameborder="0" loading="eager" referrerpolicy="strict-origin-when-cross-origin" allow="autoplay; encrypted-media" allowfullscreen></iframe>
        </div>
    <?php elseif ($bgType === 'image' && $image !== ''): ?>
        <?= Media::picture($image, '', null, null, 'block-hero__image', false, '100vw', true, 'block-hero__media') ?>
    <?php endif; ?>
    <?php if ($hasMedia): ?><div class="block-hero__scrim" aria-hidden="true" style="--hero-scrim: rgba(<?= $hex2rgb($ovColor) ?>, <?= $ovOpacity ?>);"></div><?php endif; ?>
    <div class="block-hero__inner">
        <div class="block-hero__text<?= $panelOn ? ' block-hero__text--panel' : '' ?>"<?= $textStyle !== '' ? ' style="' . $textStyle . '"' : '' ?>>
            <?php if ($eyebrow !== ''): ?><span class="block-hero__eyebrow"><?= htmlspecialchars($eyebrow, ENT_QUOTES) ?></span><?php endif; ?>
            <?php if ($title !== ''): ?><?php $hTag = $data['_heading_tag'] ?? 'h1'; ?><<?= $hTag ?> class="block-hero__title"><?= htmlspecialchars($title, ENT_QUOTES) ?></<?= $hTag ?>><?php endif; ?>
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
