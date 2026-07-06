(function () {
    'use strict';

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
