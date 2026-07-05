(function () {
    'use strict';

    document.querySelectorAll('.block-slider').forEach(function (slider) {
        const slides = slider.querySelectorAll('.block-slider__slide');
        if (slides.length < 2) {
            return;
        }
        let current = 0;

        function show(index) {
            slides[current].classList.remove('is-active');
            current = (index + slides.length) % slides.length;
            slides[current].classList.add('is-active');
        }

        const prev = slider.querySelector('.block-slider__prev');
        const next = slider.querySelector('.block-slider__next');
        if (prev) {
            prev.addEventListener('click', function () { show(current - 1); });
        }
        if (next) {
            next.addEventListener('click', function () { show(current + 1); });
        }
    });
})();
