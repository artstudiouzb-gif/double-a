(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        const addBtn = event.target.closest('[data-repeater-add]');
        if (addBtn) {
            event.preventDefault();
            const name = addBtn.getAttribute('data-repeater-add');
            const container = document.querySelector('[data-repeater="' + name + '"]');
            const template = document.querySelector('template[data-repeater-template="' + name + '"]');
            if (!container || !template) {
                return;
            }
            const index = container.children.length;
            const html = template.innerHTML.replace(/__INDEX__/g, String(index));
            const wrapper = document.createElement('div');
            wrapper.className = 'repeater-row';
            wrapper.innerHTML = html;
            container.appendChild(wrapper);
            return;
        }

        const removeBtn = event.target.closest('[data-repeater-remove]');
        if (removeBtn) {
            event.preventDefault();
            const row = removeBtn.closest('.repeater-row');
            if (row) {
                row.remove();
            }
        }
    });

    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });
})();
