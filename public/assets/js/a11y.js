/* Версия для слабовидящих: панель управления + сохранение в cookie.
   Формат cookie "a11y": "scheme:size:images", например "cw:l:on".
   Отсутствие/пустое значение — обычная версия. Начальное состояние также
   применяется на сервере (_header.php), поэтому мигания нет. */
(function () {
    'use strict';

    var COOKIE = 'a11y';
    var SCHEMES = ['cw', 'wc', 'bb'];
    var SIZES = ['m', 'l', 'xl'];

    function readCookie(name) {
        var m = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
        return m ? decodeURIComponent(m[1]) : '';
    }

    function writeCookie(name, value) {
        var maxAge = value ? 60 * 60 * 24 * 365 : 0;
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; samesite=Lax';
    }

    function parse(value) {
        var p = (value || '').split(':');
        return {
            on: SCHEMES.indexOf(p[0]) !== -1,
            scheme: SCHEMES.indexOf(p[0]) !== -1 ? p[0] : 'cw',
            size: SIZES.indexOf(p[1]) !== -1 ? p[1] : 'm',
            images: p[2] === 'off' ? 'off' : 'on'
        };
    }

    var state = parse(readCookie(COOKIE));

    function apply() {
        var h = document.documentElement;
        if (state.on) {
            h.setAttribute('data-a11y', '1');
            h.setAttribute('data-a11y-scheme', state.scheme);
            h.setAttribute('data-a11y-size', state.size);
            h.setAttribute('data-a11y-images', state.images);
            writeCookie(COOKIE, state.scheme + ':' + state.size + ':' + state.images);
        } else {
            h.removeAttribute('data-a11y');
            h.removeAttribute('data-a11y-scheme');
            h.removeAttribute('data-a11y-size');
            h.removeAttribute('data-a11y-images');
            writeCookie(COOKIE, '');
        }
        syncButtons();
    }

    function syncButtons() {
        document.querySelectorAll('[data-a11y-set]').forEach(function (btn) {
            var spec = btn.getAttribute('data-a11y-set').split(':');
            var key = spec[0], val = spec[1];
            btn.setAttribute('aria-pressed', String(state[key] === val));
        });
    }

    function onReady() {
        var toggles = document.querySelectorAll('.a11y-toggle');
        var panel = document.querySelector('.a11y-panel');
        var lastToggle = null;
        var setPanelOpen = function (open, restoreFocus) {
            if (!panel) { return; }
            panel.classList.toggle('is-open', open);
            toggles.forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            window.dispatchEvent(new CustomEvent('a11y:panelchange', { detail: { open: open } }));
            if (restoreFocus && lastToggle) { lastToggle.focus(); }
        };
        if (toggles.length && panel) {
            toggles.forEach(function (toggle) {
                toggle.addEventListener('click', function () {
                    lastToggle = toggle;
                    setPanelOpen(!panel.classList.contains('is-open'), false);
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && panel.classList.contains('is-open')) {
                    setPanelOpen(false, true);
                }
            });
        }

        document.querySelectorAll('[data-a11y-set]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var spec = btn.getAttribute('data-a11y-set').split(':');
                state[spec[0]] = spec[1];
                state.on = true;
                apply();
            });
        });

        var off = document.querySelector('.a11y-panel__off');
        if (off) {
            off.addEventListener('click', function (e) {
                e.preventDefault();
                state.on = false;
                apply();
                setPanelOpen(false, true);
            });
        }

        syncButtons();
        if (panel) { setPanelOpen(panel.classList.contains('is-open'), false); }
    }

    // Атрибуты уже проставлены сервером; JS лишь синхронизирует и вешает обработчики.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
})();
