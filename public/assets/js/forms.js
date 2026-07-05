(function () {
    'use strict';

    var forms = document.querySelectorAll('.block-form__form');
    if (!forms.length) { return; }

    forms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Снимаем прошлые ошибки.
            form.querySelectorAll('.block-form__field.is-error').forEach(function (f) {
                f.classList.remove('is-error');
                var msg = f.querySelector('.block-form__error');
                if (msg) { msg.remove(); }
            });
            var topMsg = form.querySelector('.block-form__message');
            if (topMsg) { topMsg.remove(); }

            var btn = form.querySelector('button[type="submit"], .block-form__submit');
            var oldLabel = btn ? btn.textContent : '';
            if (btn) { btn.disabled = true; btn.classList.add('is-loading'); btn.textContent = 'Отправка…'; }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Ошибка сервера.' }; }); })
                .then(function (res) {
                    if (res.ok) {
                        // Успех: плавно скрываем форму, показываем благодарность.
                        form.style.transition = 'opacity .4s ease';
                        form.style.opacity = '0';
                        setTimeout(function () {
                            var done = document.createElement('div');
                            done.className = 'block-form__thanks';
                            done.textContent = res.message || 'Спасибо! Ваша заявка отправлена.';
                            form.parentNode.replaceChild(done, form);
                        }, 400);
                        return;
                    }

                    // Ошибки валидации по полям.
                    if (res.errors) {
                        Object.keys(res.errors).forEach(function (name) {
                            var input = form.querySelector('[name="' + name + '"]');
                            var field = input ? input.closest('.block-form__field') : null;
                            if (field) {
                                field.classList.add('is-error');
                                var em = document.createElement('span');
                                em.className = 'block-form__error';
                                em.textContent = res.errors[name];
                                field.appendChild(em);
                            }
                        });
                    }
                    showTopMessage(form, res.message || 'Проверьте форму.', 'error');
                })
                .catch(function () {
                    showTopMessage(form, 'Сетевая ошибка. Попробуйте ещё раз.', 'error');
                })
                .finally(function () {
                    if (btn) { btn.disabled = false; btn.classList.remove('is-loading'); btn.textContent = oldLabel; }
                });
        });
    });

    function showTopMessage(form, text, type) {
        var m = document.createElement('div');
        m.className = 'block-form__message block-form__message--' + type;
        m.textContent = text;
        form.insertBefore(m, form.firstChild);
    }
})();
