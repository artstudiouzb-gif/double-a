(function () {
    'use strict';

    // --- Скелетоны: снимаем состояние загрузки, когда картинка готова ---
    function clearSkeleton(img) {
        var box = img.closest('.skeleton');
        if (box) { box.classList.remove('skeleton'); }
    }
    document.querySelectorAll('.skeleton img').forEach(function (img) {
        if (img.complete && img.naturalWidth > 0) {
            clearSkeleton(img);
        } else {
            img.addEventListener('load', function () { clearSkeleton(img); });
            img.addEventListener('error', function () {
                // Fallback обложки (YouTube hqdefault) при ошибке загрузки.
                var fb = img.getAttribute('data-fallback');
                if (fb && img.src !== fb) { img.src = fb; }
                else { clearSkeleton(img); }
            });
        }
    });

    // --- Слайдер галереи новости ---
    document.querySelectorAll('[data-news-slider]').forEach(function (slider) {
        var slides = Array.prototype.slice.call(slider.querySelectorAll('.news-slider__slide'));
        if (slides.length < 2) { return; }
        var index = 0;

        function show(next) {
            slides[index].classList.remove('is-active');
            index = (next + slides.length) % slides.length;
            slides[index].classList.add('is-active');
            // Лениво «разбудим» соседние картинки.
            var img = slides[index].querySelector('img[loading="lazy"]');
            if (img && img.dataset.pending !== '0') { img.dataset.pending = '0'; }
        }

        var prev = slider.querySelector('.news-slider__nav--prev');
        var next = slider.querySelector('.news-slider__nav--next');
        if (prev) { prev.addEventListener('click', function () { show(index - 1); }); }
        if (next) { next.addEventListener('click', function () { show(index + 1); }); }
    });

    // --- Ленивый YouTube: превью -> iframe только по клику ---
    document.querySelectorAll('[data-youtube]').forEach(function (box) {
        var btn = box.querySelector('.news-video__play');
        var target = btn || box;
        target.addEventListener('click', function () {
            var embed = box.getAttribute('data-embed');
            if (!embed) { return; }
            var iframe = document.createElement('iframe');
            iframe.setAttribute('src', embed);
            iframe.setAttribute('title', 'YouTube video');
            iframe.setAttribute('frameborder', '0');
            iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
            iframe.setAttribute('allowfullscreen', '');
            iframe.className = 'news-video__iframe';
            box.innerHTML = '';
            box.classList.remove('skeleton');
            box.appendChild(iframe);
        });
    });
})();
