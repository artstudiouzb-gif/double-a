    </main>
</div>

<div class="media-modal" data-media-modal hidden role="dialog" aria-modal="true" aria-labelledby="media-modal-title">
    <div class="media-modal__dialog">
        <div class="media-modal__head">
            <strong id="media-modal-title">Медиабиблиотека</strong>
            <button type="button" class="media-modal__close" data-media-close aria-label="Закрыть">×</button>
        </div>
        <div class="media-modal__upload" data-media-upload data-csrf="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES) ?>">
            <label class="media-modal__upload-field">
                <span>Загрузить новый файл</span>
                <input type="file" data-media-upload-input>
            </label>
            <button type="button" class="btn btn-primary" data-media-upload-button>Загрузить</button>
            <small class="media-modal__upload-hint">До 200 МБ</small>
            <div class="media-modal__upload-status" data-media-upload-status aria-live="polite"></div>
        </div>
        <div class="media-modal__grid" data-media-grid aria-busy="true">
            <div class="media-modal__empty">Загрузка…</div>
        </div>
    </div>
</div>

<script nonce="<?= \App\Core\SecurityHeaders::nonce() ?>">
var stickyActions = Array.prototype.slice.call(document.querySelectorAll('.form-actions--sticky'));
if (stickyActions.length) {
    document.body.classList.add('has-sticky-actions');
    stickyActions.forEach(function (actions) {
        actions.setAttribute('role', 'toolbar');
        actions.setAttribute('aria-label', 'Действия формы');
    });
    var stickyTicking = false;
    var syncStickyActions = function () {
        var topOffset = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--admin-topbar-h'), 10) || 46;
        stickyActions.forEach(function (actions) {
            var form = actions.closest('form');
            if (!form) return;
            var rect = form.getBoundingClientRect();
            var inContext = rect.bottom > topOffset && rect.top < window.innerHeight;
            actions.classList.toggle('is-context-hidden', !inContext);
        });
        stickyTicking = false;
    };
    var requestStickySync = function () {
        if (stickyTicking) return;
        stickyTicking = true;
        window.requestAnimationFrame(syncStickyActions);
    };
    syncStickyActions();
    window.addEventListener('scroll', requestStickySync, {passive: true});
    window.addEventListener('resize', requestStickySync);
}
/* Навигация админки: мобильная панель и запоминаемое сворачивание на десктопе. */
(function () {
    var t = document.querySelector('[data-sidebar-toggle]');
    var s = document.querySelector('[data-sidebar]');
    var backdrop = document.querySelector('[data-sidebar-backdrop]');
    var collapse = document.querySelector('[data-sidebar-collapse]');

    function setMobileOpen(open) {
        document.body.classList.toggle('sidebar-open', open);
        if (s) {
            var mobile = window.matchMedia('(max-width: 960px)').matches;
            s.inert = mobile && !open;
            if (mobile) s.setAttribute('aria-hidden', open ? 'false' : 'true');
            else s.removeAttribute('aria-hidden');
        }
        if (t) {
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
            t.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
        }
    }

    function syncCollapsedState() {
        if (!collapse) return;
        var collapsed = document.documentElement.classList.contains('admin-nav-collapsed');
        collapse.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        collapse.setAttribute('title', collapsed ? 'Развернуть меню' : 'Свернуть меню');
        var label = collapse.querySelector('span');
        if (label) label.textContent = collapsed ? 'Развернуть меню' : 'Свернуть меню';
    }

    if (t && s) {
        setMobileOpen(false);
        t.addEventListener('click', function () {
            var opening = !document.body.classList.contains('sidebar-open');
            setMobileOpen(opening);
            if (opening) {
                var current = s.querySelector('[aria-current="page"]') || s.querySelector('.admin-nav-item');
                if (current) current.focus();
            }
        });
        s.addEventListener('click', function (e) {
            if (e.target.closest('.admin-nav-item') && window.matchMedia('(max-width: 960px)').matches) {
                setMobileOpen(false);
            }
        });
        if (backdrop) backdrop.addEventListener('click', function () { setMobileOpen(false); t.focus(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
                setMobileOpen(false);
                t.focus();
            }
        });
        window.addEventListener('resize', function () {
            if (!window.matchMedia('(max-width: 960px)').matches) setMobileOpen(false);
        });
    }

    if (collapse) {
        syncCollapsedState();
        collapse.addEventListener('click', function () {
            var collapsed = document.documentElement.classList.toggle('admin-nav-collapsed');
            try { localStorage.setItem('artstudio:admin-sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {}
            syncCollapsedState();
        });
    }
})();
</script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/vendor/editor.js'), ENT_QUOTES) ?>"></script>
<script src="<?= htmlspecialchars(\App\Core\Asset::url('/assets/js/admin.js'), ENT_QUOTES) ?>"></script>
</body>
</html>
