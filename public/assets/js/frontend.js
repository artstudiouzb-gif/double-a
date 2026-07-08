(function () {
    'use strict';

    // Бургер-меню (режим «Бургер-меню» в настройках дизайна): открывает и
    // закрывает главное меню на мобильных; Esc закрывает.
    var burger = document.querySelector('[data-mobile-menu-toggle]');
    if (burger) {
        burger.addEventListener('click', function () {
            var open = document.body.classList.toggle('mobile-menu-open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.body.classList.contains('mobile-menu-open')) {
                document.body.classList.remove('mobile-menu-open');
                burger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Выпадающий поиск (режим «Выпадающий» в настройках дизайна): кнопка-лупа
    // открывает панель поиска сверху; закрытие по ×, Esc или клику вне формы.
    var searchToggle = document.querySelector('[data-search-toggle]');
    var searchOverlay = document.querySelector('[data-search-overlay]');
    if (searchToggle && searchOverlay) {
        var searchInput = searchOverlay.querySelector('[data-search-input]');
        var openSearch = function () {
            searchOverlay.hidden = false;
            searchToggle.setAttribute('aria-expanded', 'true');
            requestAnimationFrame(function () {
                searchOverlay.classList.add('is-open');
                if (searchInput) { searchInput.focus(); }
            });
        };
        var closeSearch = function () {
            searchOverlay.classList.remove('is-open');
            searchToggle.setAttribute('aria-expanded', 'false');
            setTimeout(function () { searchOverlay.hidden = true; }, 200);
        };
        searchToggle.addEventListener('click', openSearch);
        searchOverlay.addEventListener('click', function (e) {
            if (e.target === searchOverlay || e.target.hasAttribute('data-search-close')) {
                closeSearch();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !searchOverlay.hidden) { closeSearch(); }
        });
    }

    // Выпадающее подменю: клик по стрелке раскрывает (мобильные/клавиатура).
    // На desktop работает и hover/focus-within (CSS), клик — дополнительно.
    document.querySelectorAll('.site-menu__item--has-children .site-menu__toggle').forEach(function (toggle) {
        var item = toggle.closest('.site-menu__item');
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var open = item.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
    // Клик вне меню — закрыть все раскрытые подменю.
    document.addEventListener('click', function (e) {
        document.querySelectorAll('.site-menu__item.is-open').forEach(function (item) {
            if (!item.contains(e.target)) {
                item.classList.remove('is-open');
                var t = item.querySelector('.site-menu__toggle');
                if (t) { t.setAttribute('aria-expanded', 'false'); }
            }
        });
    });

    // Переключатель светлой/тёмной темы с сохранением выбора в localStorage.
    document.querySelectorAll('.site-theme-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var root = document.documentElement;
            var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) {}
        });
    });

    // Счётчики (группа 4): анимация инкремента числа при попадании в зону
    // видимости. Переиспользуем IntersectionObserver. Уважает reduced-motion.
    (function () {
        var counters = document.querySelectorAll('.counter__value[data-counter-target]');
        if (!counters.length) { return; }
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce || !('IntersectionObserver' in window)) {
            counters.forEach(function (el) { el.textContent = el.getAttribute('data-counter-target'); });
            return;
        }
        function animate(el) {
            var target = parseInt(el.getAttribute('data-counter-target'), 10) || 0;
            var start = null, dur = 1400;
            function step(ts) {
                if (start === null) { start = ts; }
                var p = Math.min((ts - start) / dur, 1);
                el.textContent = Math.round(p * target).toString();
                if (p < 1) { requestAnimationFrame(step); }
            }
            requestAnimationFrame(step);
        }
        var cio = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (e) {
                if (e.isIntersecting) { animate(e.target); obs.unobserve(e.target); }
            });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { cio.observe(el); });
    })();

    // Микро-движок анимаций появления при скролле на Intersection Observer.
    var reveals = document.querySelectorAll('[data-reveal]');
    if (!reveals.length) {
        return;
    }
    if (!('IntersectionObserver' in window)) {
        reveals.forEach(function (el) { el.classList.add('is-visible'); });
        return;
    }
    var io = new IntersectionObserver(function (entries, observer) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    reveals.forEach(function (el) { io.observe(el); });
})();
