<?php

use App\Core\Csrf;

/** @var array $data */
/** @var int $blockId */
?>
<div class="container">
    <div class="subscribe-block">
        <?php if (!empty($data['title'])): ?>
            <h2 class="subscribe-block__title"><?= htmlspecialchars((string) $data['title'], ENT_QUOTES) ?></h2>
        <?php endif; ?>
        <?php if (!empty($data['text'])): ?>
            <p class="subscribe-block__text"><?= htmlspecialchars((string) $data['text'], ENT_QUOTES) ?></p>
        <?php endif; ?>
        <form class="subscribe-block__form" method="post" action="/subscribe">
            <?= Csrf::field() ?>
            <?= Csrf::honeypotField() ?>
            <label class="visually-hidden" for="subscribe-email-<?= (int) $blockId ?>">Email</label>
            <input type="email" id="subscribe-email-<?= (int) $blockId ?>" name="email" placeholder="Ваш email" required>
            <button type="submit" class="btn btn--primary"><?= htmlspecialchars((string) ($data['button_text'] ?: 'Подписаться'), ENT_QUOTES) ?></button>
        </form>
    </div>
</div>
