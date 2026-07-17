<?php

// Индикаторы языков контента для строки списка.
// $siteLangs — активные языки сайта (коды); $has — языки, где контент есть.
/** @var array<int, string> $siteLangs */
$siteLangs = $siteLangs ?? [];
/** @var array<int, string> $has */
$has = $has ?? [];

foreach ($siteLangs as $code):
    $on = in_array($code, $has, true);
    ?>
    <span title="<?= $on ? 'Контент на этом языке есть' : 'Перевода нет' ?>"
          style="display:inline-block;margin-right:4px;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:700;text-transform:uppercase;<?= $on
              ? 'background:#e6f4ea;color:#1e7e34;'
              : 'background:#f1f2f4;color:#9aa0a6;' ?>"><?= htmlspecialchars($code, ENT_QUOTES) ?></span>
<?php endforeach; ?>
