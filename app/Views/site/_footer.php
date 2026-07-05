<?php

use App\Models\Setting;

$siteName = Setting::get('site_name', 'ArtStudio');
$counterCodes = Setting::get('counter_codes', '');
?>
</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES) ?></p>
</footer>
<script src="/assets/js/frontend.js"></script>
<?= $counterCodes /* коды счётчиков вводятся администратором в настройках */ ?>
</body>
</html>
