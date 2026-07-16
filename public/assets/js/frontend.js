(function () {
    'use strict';

    // Переключатели главного меню: бургер (мобильные / макет «боковая панель»),
    // а также фон и кнопка закрытия off-canvas панели. Любой из них
    // открывает/закрывает меню через класс body; Esc закрывает.
    var menuToggles = document.querySelectorAll('[data-mobile-menu-toggle]');
    var burger = document.querySelector('.site-burger[data-mobile-menu-toggle]');
    var setBurgerState = function (open) {
        if (burger) { burger.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    };
    if (menuToggles.length) {
        menuToggles.forEach(function (el) {
            el.addEventListener('click', function () {
                var open = document.body.classList.toggle('mobile-menu-open');
                setBurgerState(open);
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.body.classList.contains('mobile-menu-open')) {
                document.body.classList.remove('mobile-menu-open');
                setBurgerState(false);
            }
        });
        // Клик по пункту внутри off-canvas панели закрывает её.
        document.querySelectorAll('.site-drawer__panel .site-menu__link').forEach(function (link) {
            link.addEventListener('click', function () {
                document.body.classList.remove('mobile-menu-open');
                setBurgerState(false);
            });
        });
    }

    // Выпадающий поиск (режим «Выпадающий» в настройках дизайна): кнопка-лупа
    // открывает панель поиска сверху; закрытие по ×, Esc или клику вне формы.
    var searchToggles = document.querySelectorAll('[data-search-toggle]');
    var searchOverlay = document.querySelector('[data-search-overlay]');
    if (searchToggles.length && !searchOverlay) {
        searchToggles.forEach(function (t) {
            t.addEventListener('click', function () {
                var wrap = t.parentElement;
                var form = wrap ? wrap.querySelector('.site-search') : null;
                if (!form) { return; }
                var open = form.classList.toggle('is-open');
                t.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) { var inp = form.querySelector('input'); if (inp) { inp.focus(); } }
            });
        });
    }
    if (searchToggles.length && searchOverlay) {
        var searchInput = searchOverlay.querySelector('[data-search-input]');
        var searchForm = searchOverlay.querySelector('.site-search-overlay__form');
        var activeSearchToggle = null;
        var searchCloseTimer = null;
        var positionSearch = function (toggle) {
            if (!toggle || !searchForm) { return; }
            var toggleRect = toggle.getBoundingClientRect();
            var header = toggle.closest('.site-header');
            var anchorRect = header ? header.getBoundingClientRect() : toggleRect;
            var desiredTop = anchorRect.bottom + 10;
            var maxTop = Math.max(12, window.innerHeight - searchForm.offsetHeight - 12);
            var top = Math.max(12, Math.min(desiredTop, maxTop));
            var desiredRight = Math.max(12, window.innerWidth - toggleRect.right);
            var maxRight = Math.max(12, window.innerWidth - searchForm.offsetWidth - 12);
            var right = Math.min(desiredRight, maxRight);
            searchOverlay.style.setProperty('--search-popover-top', top + 'px');
            searchOverlay.style.setProperty('--search-popover-right', right + 'px');
        };
        var openSearch = function (toggle) {
            if (searchOverlay.classList.contains('is-open') && activeSearchToggle === toggle) {
                closeSearch(true);
                return;
            }
            if (searchCloseTimer) { clearTimeout(searchCloseTimer); searchCloseTimer = null; }
            activeSearchToggle = toggle;
            searchOverlay.hidden = false;
            document.body.classList.add('site-search-open');
            positionSearch(toggle);
            searchToggles.forEach(function (t) { t.setAttribute('aria-expanded', 'true'); });
            requestAnimationFrame(function () {
                searchOverlay.classList.add('is-open');
                if (searchInput) { searchInput.focus(); }
            });
        };
        var closeSearch = function (restoreFocus) {
            var focusTarget = activeSearchToggle;
            searchOverlay.classList.remove('is-open');
            document.body.classList.remove('site-search-open');
            searchToggles.forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
            searchCloseTimer = setTimeout(function () {
                searchOverlay.hidden = true;
                searchCloseTimer = null;
                if (restoreFocus && focusTarget) { focusTarget.focus(); }
                activeSearchToggle = null;
            }, 180);
        };
        searchToggles.forEach(function (t) {
            t.addEventListener('click', function () { openSearch(t); });
        });
        searchOverlay.addEventListener('click', function (e) {
            if (e.target === searchOverlay || e.target.closest('[data-search-close]')) {
                closeSearch(true);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (searchOverlay.hidden) { return; }
            if (e.key === 'Escape') {
                closeSearch(true);
                return;
            }
            if (e.key === 'Tab' && searchForm) {
                var focusable = Array.prototype.slice.call(searchForm.querySelectorAll('input, button, [href], [tabindex]:not([tabindex="-1"])'))
                    .filter(function (element) { return !element.disabled && element.offsetParent !== null; });
                if (!focusable.length) { return; }
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });
        window.addEventListener('resize', function () {
            if (!searchOverlay.hidden && activeSearchToggle) { positionSearch(activeSearchToggle); }
        });
        window.addEventListener('a11y:panelchange', function () {
            if (!searchOverlay.hidden && activeSearchToggle) { positionSearch(activeSearchToggle); }
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

    // Медиа-галерея: переключатели «Видео / Фото».
    document.querySelectorAll('[data-media-gallery]').forEach(function (gallery) {
        var tabs = gallery.querySelectorAll('[data-media-tab]');
        if (!tabs.length) { return; }
        var cards = gallery.querySelectorAll('[data-media-kind]');
        var apply = function (kind) {
            cards.forEach(function (c) { c.style.display = c.getAttribute('data-media-kind') === kind ? '' : 'none'; });
            tabs.forEach(function (t) {
                var on = t.getAttribute('data-media-tab') === kind;
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        };
        tabs.forEach(function (t) { t.addEventListener('click', function () { apply(t.getAttribute('data-media-tab')); }); });
        apply('video');
    });

    // Карусель проектов: прокрутка трека кнопками ‹ ›.
    document.querySelectorAll('[data-carousel]').forEach(function (root) {
        var track = root.querySelector('[data-carousel-track]');
        var prev = root.querySelector('[data-carousel-prev]');
        var next = root.querySelector('[data-carousel-next]');
        if (!track || !prev || !next) { return; }
        var step = function () {
            var card = track.querySelector('.imgcard');
            var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap || '20') || 20;
            return card ? card.getBoundingClientRect().width + gap : track.clientWidth;
        };
        var sync = function () {
            var max = track.scrollWidth - track.clientWidth - 1;
            prev.disabled = track.scrollLeft <= 0;
            next.disabled = track.scrollLeft >= max;
        };
        prev.addEventListener('click', function () { track.scrollBy({ left: -step() * 2, behavior: 'smooth' }); });
        next.addEventListener('click', function () { track.scrollBy({ left: step() * 2, behavior: 'smooth' }); });
        track.addEventListener('scroll', sync, { passive: true });
        window.addEventListener('resize', sync);
        sync();
    });

    // Детальная новость: слайдер медиа-модуля (главное фото + миниатюры + счётчик).
    document.querySelectorAll('[data-ndgallery]').forEach(function (root) {
        var slides = root.querySelectorAll('.newsdetail-gallery__slide');
        if (slides.length < 2) { return; }
        var thumbs = root.querySelectorAll('[data-ndg-thumb]');
        var counter = root.querySelector('[data-ndg-current]');
        var idx = 0;
        var show = function (i) {
            idx = (i + slides.length) % slides.length;
            slides.forEach(function (s, n) { s.classList.toggle('is-active', n === idx); });
            thumbs.forEach(function (t, n) { t.classList.toggle('is-active', n === idx); });
            if (counter) { counter.textContent = String(idx + 1); }
        };
        var prev = root.querySelector('[data-ndg-prev]');
        var next = root.querySelector('[data-ndg-next]');
        if (prev) { prev.addEventListener('click', function () { show(idx - 1); }); }
        if (next) { next.addEventListener('click', function () { show(idx + 1); }); }
        thumbs.forEach(function (t, n) { t.addEventListener('click', function () { show(n); }); });
        root.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { show(idx - 1); }
            if (e.key === 'ArrowRight') { show(idx + 1); }
        });
    });

    // «Скопировать ссылку» в блоке «Поделиться».
    document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-copy-link');
            var done = function () {
                btn.classList.add('is-copied');
                var prevLabel = btn.getAttribute('aria-label');
                btn.setAttribute('aria-label', 'Ссылка скопирована');
                setTimeout(function () { btn.classList.remove('is-copied'); btn.setAttribute('aria-label', prevLabel); }, 1600);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = url; document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(ta);
            }
        });
    });

    // Кнопка «Печать».
    document.querySelectorAll('[data-print-page]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    });

    // Липкая/прозрачная шапка: класс is-scrolled после небольшой прокрутки.
    (function () {
        var hdr = document.querySelector('[data-header-scroll]');
        if (!hdr) { return; }
        // Прозрачная шапка стартует сразу под верхней полосой (если есть).
        var topbar = document.querySelector('.site-topbar');
        var a11yPanel = document.querySelector('.a11y-panel');
        var offset = function () {
            var panelHeight = a11yPanel && a11yPanel.classList.contains('is-open') ? a11yPanel.offsetHeight : 0;
            hdr.style.setProperty('--hdr-panel-height', panelHeight + 'px');
            if (hdr.classList.contains('site-header--transparent')) {
                var topbarHeight = topbar ? topbar.offsetHeight : 0;
                hdr.style.setProperty('--hdr-top', (topbarHeight + panelHeight) + 'px');
            }
        };
        var apply = function () {
            hdr.classList.toggle('is-scrolled', window.scrollY > 12);
        };
        window.addEventListener('scroll', apply, { passive: true });
        window.addEventListener('resize', offset);
        window.addEventListener('a11y:panelchange', offset);
        offset();
        apply();
    })();

    // Делегированные обработчики вместо инлайн-атрибутов (CSP без 'unsafe-inline'):
    // [data-auto-submit] — селект отправляет свою форму; [data-captcha-refresh] —
    // кнопка обновляет картинку капчи рядом с собой.
    document.addEventListener('change', function (e) {
        var el = e.target;
        if (el && el.matches && el.matches('select[data-auto-submit]') && el.form) {
            el.form.submit();
        }
    });
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-captcha-refresh]') : null;
        if (!btn) { return; }
        var img = btn.parentNode.querySelector('img');
        if (img) { img.src = '/captcha.png?ts=' + Date.now(); }
    });

    // Сворачивание/разворачивание формы поиска при клике на иконку
    (function () {
        var searchForms = document.querySelectorAll('.site-search');
        searchForms.forEach(function (form) {
            var input = form.querySelector('input[type="search"]');
            var button = form.querySelector('button[type="submit"]');
            if (!input || !button) { return; }
            
            button.addEventListener('click', function (e) {
                if (!form.classList.contains('is-active')) {
                    e.preventDefault();
                    form.classList.add('is-active');
                    input.focus();
                } else {
                    if (input.value.trim() === '') {
                        e.preventDefault();
                        form.classList.remove('is-active');
                    }
                }
            });
            
            document.addEventListener('click', function (e) {
                if (!form.contains(e.target)) {
                    if (input.value.trim() === '') {
                        form.classList.remove('is-active');
                    }
                }
            });
        });
    })();

    // Лайтбокс: фото (альбомы, блок-галерея, фотолента новости, медиа-карточки)
    // и видео YouTube (карточки на главной/страницах, «Смотреть видео» в новостях).
    (function () {
        var IMG_RE = /\.(jpe?g|png|gif|webp|avif)(\?.*)?$/i;
        var PHOTO_SCOPES = '.album-photos, .block-gallery__grid, .newsdetail-photos__grid, .mediagallery-grid';

        function ytId(url) {
            var patterns = [
                /youtu\.be\/([\w-]{11})/,
                /youtube\.com\/watch\?[^\s]*v=([\w-]{11})/,
                /youtube\.com\/embed\/([\w-]{11})/,
                /youtube\.com\/shorts\/([\w-]{11})/
            ];
            for (var i = 0; i < patterns.length; i++) {
                var m = String(url || '').match(patterns[i]);
                if (m) { return m[1]; }
            }
            return null;
        }

        var box = null, stage = null, captionEl = null, prevBtn = null, nextBtn = null;
        var items = [], index = 0, lastFocus = null;

        function ensure() {
            if (box) { return; }
            box = document.createElement('div');
            box.className = 'cms-lightbox';
            box.setAttribute('role', 'dialog');
            box.setAttribute('aria-modal', 'true');
            box.setAttribute('aria-label', 'Просмотр медиа');
            box.innerHTML =
                '<button type="button" class="cms-lightbox__close" aria-label="Закрыть">&times;</button>' +
                '<button type="button" class="cms-lightbox__nav cms-lightbox__nav--prev" aria-label="Предыдущее">&#10094;</button>' +
                '<div class="cms-lightbox__stage"></div>' +
                '<button type="button" class="cms-lightbox__nav cms-lightbox__nav--next" aria-label="Следующее">&#10095;</button>' +
                '<div class="cms-lightbox__caption"></div>';
            document.body.appendChild(box);
            stage = box.querySelector('.cms-lightbox__stage');
            captionEl = box.querySelector('.cms-lightbox__caption');
            prevBtn = box.querySelector('.cms-lightbox__nav--prev');
            nextBtn = box.querySelector('.cms-lightbox__nav--next');

            box.querySelector('.cms-lightbox__close').addEventListener('click', close);
            box.addEventListener('click', function (e) {
                if (e.target === box || e.target === stage) { close(); }
            });
            prevBtn.addEventListener('click', function () { go(-1); });
            nextBtn.addEventListener('click', function () { go(1); });
            document.addEventListener('keydown', function (e) {
                if (!box.classList.contains('is-open')) { return; }
                if (e.key === 'Escape') { close(); return; }
                if (e.key === 'ArrowLeft') { go(-1); return; }
                if (e.key === 'ArrowRight') { go(1); return; }
                // Focus-trap: Tab не выпускает фокус за пределы модалки (WCAG 2.4.3).
                if (e.key === 'Tab') {
                    var focusable = Array.prototype.filter.call(
                        box.querySelectorAll('button:not([hidden]), a[href], iframe'),
                        function (el) { return el.offsetParent !== null; }
                    );
                    if (!focusable.length) { return; }
                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
        }

        function render() {
            var item = items[index];
            if (!item) { return; }
            if (item.type === 'video') {
                stage.innerHTML = '<iframe class="cms-lightbox__video" src="https://www.youtube-nocookie.com/embed/'
                    + item.id + '?rel=0&modestbranding=1&autoplay=1" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen title="Видео"></iframe>';
            } else {
                var img = document.createElement('img');
                img.src = item.src;
                img.alt = item.caption || '';
                stage.innerHTML = '';
                stage.appendChild(img);
            }
            captionEl.textContent = item.caption || '';
            captionEl.hidden = !item.caption;
            var many = items.length > 1;
            prevBtn.hidden = !many;
            nextBtn.hidden = !many;
        }

        function open(list, i, trigger) {
            ensure();
            items = list;
            index = i;
            lastFocus = trigger || document.activeElement;
            render();
            box.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            box.querySelector('.cms-lightbox__close').focus();
        }

        function close() {
            if (!box) { return; }
            box.classList.remove('is-open');
            stage.innerHTML = ''; // останавливает видео
            document.body.style.overflow = '';
            if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
        }

        function go(step) {
            if (items.length < 2) { return; }
            index = (index + step + items.length) % items.length;
            render();
        }

        document.addEventListener('click', function (e) {
            var a = e.target.closest('a[href]');
            if (!a || e.defaultPrevented) { return; }
            var href = a.getAttribute('href') || '';

            // Видео YouTube — в лайтбокс на любой публичной странице.
            var id = ytId(href);
            if (id) {
                e.preventDefault();
                open([{ type: 'video', id: id }], 0, a);
                return;
            }

            // Фото: только в известных контейнерах, группой с листанием.
            var scope = a.closest(PHOTO_SCOPES);
            if (!scope || !IMG_RE.test(href)) { return; }
            var links = Array.prototype.filter.call(scope.querySelectorAll('a[href]'), function (el) {
                return IMG_RE.test(el.getAttribute('href') || '');
            });
            var list = links.map(function (el) {
                var fig = el.closest('figure');
                var cap = fig ? fig.querySelector('figcaption') : null;
                return {
                    type: 'image',
                    src: el.getAttribute('href'),
                    caption: (cap && cap.textContent) || el.getAttribute('aria-label') || (el.querySelector('img') && el.querySelector('img').alt) || ''
                };
            });
            e.preventDefault();
            open(list, Math.max(0, links.indexOf(a)), a);
        });
    })();
