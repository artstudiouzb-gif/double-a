    </main>
</div>

<div class="media-modal" data-media-modal hidden role="dialog" aria-modal="true" aria-labelledby="media-modal-title">
    <div class="media-modal__dialog">
        <div class="media-modal__head">
            <strong id="media-modal-title">Медиабиблиотека</strong>
            <button type="button" class="media-modal__close" data-media-close aria-label="Закрыть">×</button>
        </div>
        <div class="media-modal__grid" data-media-grid aria-busy="true">
            <div class="media-modal__empty">Загрузка…</div>
        </div>
    </div>
</div>

<script>
/* Мобильный тумблер сайдбара (нативно, без внешних библиотек). */
(function () {
    var t = document.querySelector('[data-sidebar-toggle]');
    var s = document.querySelector('[data-sidebar]');
    if (t && s) {
        t.addEventListener('click', function () { document.body.classList.toggle('sidebar-open'); });
        s.addEventListener('click', function (e) { if (e.target.closest('.admin-nav-item')) { document.body.classList.remove('sidebar-open'); } });
    }
})();
</script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/vendor/editor.js'), ENT_QUOTES) ?>"></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/admin.js'), ENT_QUOTES) ?>"></script>
</body>
</html>
