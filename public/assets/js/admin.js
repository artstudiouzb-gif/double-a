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

    // Чанковая загрузка больших файлов через File API.
    var chunkBtn = document.getElementById('chunk_upload_btn');
    if (chunkBtn) {
        chunkBtn.addEventListener('click', function () {
            var input = document.getElementById('chunk_file');
            var progress = document.getElementById('chunk_progress');
            var access = document.getElementById('chunk_access');
            if (!input.files || !input.files.length) {
                progress.textContent = 'Выберите файл.';
                return;
            }
            var file = input.files[0];
            var chunkSize = 1024 * 1024; // 1 МБ
            var total = Math.ceil(file.size / chunkSize);
            var uploadId = '';
            for (var i = 0; i < 32; i++) { uploadId += Math.floor(Math.random() * 16).toString(16); }
            var csrf = chunkBtn.getAttribute('data-csrf');
            chunkBtn.disabled = true;

            function sendChunk(index) {
                var start = index * chunkSize;
                var blob = file.slice(start, Math.min(start + chunkSize, file.size));
                var fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('upload_id', uploadId);
                fd.append('index', String(index));
                fd.append('total', String(total));
                fd.append('name', file.name);
                fd.append('access_type', access.value);
                fd.append('chunk', blob);

                fetch('/admin/files/chunk', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'HTTP ' + r.status }; }); })
                    .then(function (res) {
                        if (!res.ok) {
                            progress.textContent = 'Ошибка: ' + (res.error || 'неизвестная');
                            chunkBtn.disabled = false;
                            return;
                        }
                        if (res.done) {
                            progress.textContent = 'Готово! Файл загружен. Обновите страницу.';
                            chunkBtn.disabled = false;
                            setTimeout(function () { window.location.reload(); }, 800);
                            return;
                        }
                        progress.textContent = 'Загрузка… ' + Math.round(((index + 1) / total) * 100) + '%';
                        sendChunk(index + 1);
                    })
                    .catch(function () {
                        progress.textContent = 'Сетевая ошибка при загрузке.';
                        chunkBtn.disabled = false;
                    });
            }

            progress.textContent = 'Загрузка… 0%';
            sendChunk(0);
        });
    }

    // --- Массовый выбор в списках (задача 91) ---
    document.querySelectorAll('[data-select-all]').forEach(function (master) {
        var table = master.closest('table');
        if (!table) { return; }
        var items = table.querySelectorAll('[data-bulk-item]');
        var counter = document.querySelector('[data-bulk-count]');
        function refresh() {
            var checked = table.querySelectorAll('[data-bulk-item]:checked').length;
            if (counter) { counter.textContent = checked + ' выбрано'; }
            master.checked = checked > 0 && checked === items.length;
            master.indeterminate = checked > 0 && checked < items.length;
        }
        master.addEventListener('change', function () {
            items.forEach(function (i) { i.checked = master.checked; });
            refresh();
        });
        items.forEach(function (i) { i.addEventListener('change', refresh); });
    });

    // Не отправлять bulk-форму без выбранного действия/записей.
    document.querySelectorAll('[data-bulk-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var anyChecked = document.querySelectorAll('[data-bulk-item]:checked').length > 0;
            var action = form.querySelector('[name="bulk_action"]');
            if (!anyChecked) { e.preventDefault(); alert('Выберите хотя бы одну запись.'); return; }
            if (action && !action.value) { e.preventDefault(); alert('Выберите действие.'); }
        });
    });

    // --- Быстрый глобальный поиск (задача 92, Ctrl+K) ---
    (function () {
        var box = document.querySelector('[data-search]');
        if (!box) { return; }
        var input = box.querySelector('[data-search-input]');
        var results = box.querySelector('[data-search-results]');
        var timer = null, lastQuery = '';

        function render(items) {
            if (!items.length) { results.innerHTML = '<div class="admin-search__empty">Ничего не найдено</div>'; }
            else {
                results.innerHTML = items.map(function (r) {
                    return '<a class="admin-search__item" href="' + r.url + '">' +
                        '<span class="admin-search__type">' + r.type + '</span>' +
                        '<span class="admin-search__title"></span></a>';
                }).join('');
                // Заголовки вставляем через textContent (без риска XSS).
                var links = results.querySelectorAll('.admin-search__item');
                items.forEach(function (r, i) {
                    links[i].querySelector('.admin-search__title').textContent = r.title;
                });
            }
            results.hidden = false;
        }

        function search() {
            var q = input.value.trim();
            if (q === lastQuery) { return; }
            lastQuery = q;
            if (q.length < 2) { results.hidden = true; results.innerHTML = ''; return; }
            fetch('/admin/search?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) { render(data.results || []); })
                .catch(function () { results.hidden = true; });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(search, 200);
        });
        input.addEventListener('focus', function () { if (results.innerHTML) { results.hidden = false; } });
        document.addEventListener('click', function (e) {
            if (!box.contains(e.target)) { results.hidden = true; }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault(); input.focus(); input.select();
            }
            if (e.key === 'Escape') { results.hidden = true; input.blur(); }
        });
    })();

    // --- Медиабиблиотека: выбор уже загруженного файла (задача 90) ---
    (function () {
        var modal = document.querySelector('[data-media-modal]');
        if (!modal) { return; }
        var grid = modal.querySelector('[data-media-grid]');
        var currentTarget = null;
        var loaded = false;

        function open(targetSelector) {
            currentTarget = document.querySelector(targetSelector);
            modal.hidden = false;
            if (loaded) { return; }
            grid.innerHTML = '<div class="media-modal__empty">Загрузка…</div>';
            fetch('/admin/media/list', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    loaded = true;
                    grid.setAttribute('aria-busy', 'false');
                    var items = data.items || [];
                    if (!items.length) { grid.innerHTML = '<div class="media-modal__empty">В библиотеке нет изображений.</div>'; return; }
                    grid.innerHTML = '';
                    items.forEach(function (it) {
                        var fig = document.createElement('button');
                        fig.type = 'button';
                        fig.className = 'media-modal__item';
                        fig.title = it.name;
                        var img = document.createElement('img');
                        img.src = it.url; img.alt = it.name; img.loading = 'lazy';
                        fig.appendChild(img);
                        fig.addEventListener('click', function () {
                            if (currentTarget) {
                                currentTarget.value = it.url;
                                currentTarget.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            close();
                        });
                        grid.appendChild(fig);
                    });
                })
                .catch(function () { grid.innerHTML = '<div class="media-modal__empty">Ошибка загрузки.</div>'; });
        }
        function close() { modal.hidden = true; }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-media-pick]');
            if (btn) { e.preventDefault(); open(btn.getAttribute('data-media-target')); return; }
            if (e.target.closest('[data-media-close]') || e.target === modal) { close(); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
    })();

    // --- Автономный WYSIWYG (задача 75): инициализация на textarea[data-wysiwyg] ---
    if (window.ArtEditor) {
        document.querySelectorAll('textarea[data-wysiwyg]').forEach(function (ta) {
            window.ArtEditor.attach(ta);
        });
    }

    // --- Drag-and-drop сортировка блоков (задача 134, нативный HTML5 DnD) ---
    document.querySelectorAll('[data-block-sortable]').forEach(function (list) {
        var dragged = null;

        list.querySelectorAll('.block-list-item').forEach(function (item) {
            item.addEventListener('dragstart', function (e) {
                dragged = item;
                item.classList.add('is-dragging');
                try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', ''); } catch (err) {}
            });
            item.addEventListener('dragend', function () {
                item.classList.remove('is-dragging');
                persist();
            });
        });

        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragged) { return; }
            var after = null;
            var items = Array.prototype.slice.call(list.querySelectorAll('.block-list-item:not(.is-dragging)'));
            for (var i = 0; i < items.length; i++) {
                var box = items[i].getBoundingClientRect();
                if (e.clientY < box.top + box.height / 2) { after = items[i]; break; }
            }
            if (after == null) { list.appendChild(dragged); }
            else { list.insertBefore(dragged, after); }
        });

        function persist() {
            var order = Array.prototype.map.call(
                list.querySelectorAll('.block-list-item'),
                function (el) { return el.getAttribute('data-block-id'); }
            );
            var body = new URLSearchParams();
            body.append('csrf_token', list.getAttribute('data-csrf'));
            body.append('page_id', list.getAttribute('data-page-id'));
            body.append('block_lang', list.getAttribute('data-block-lang'));
            order.forEach(function (id) { body.append('order[]', id); });

            fetch('/admin/blocks/reorder', {
                method: 'POST', body: body, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); })
              .then(function (res) { if (!res.ok) { alert('Не удалось сохранить порядок.'); } })
              .catch(function () { alert('Сетевая ошибка при сохранении порядка.'); });
        }
    });

    // --- Меню: drag-and-drop сортировка + вложенность (задача 3, группа 3) ---
    document.querySelectorAll('[data-menu-sortable]').forEach(function (root) {
        var dragged = null;

        function isChildList(list) { return list.hasAttribute('data-menu-children'); }
        function draggedHasChildren() {
            var kids = dragged.querySelector('[data-menu-children]');
            return kids && kids.querySelector('.menu-node');
        }

        root.querySelectorAll('.menu-node').forEach(function (node) {
            node.setAttribute('draggable', 'true');
        });

        root.addEventListener('dragstart', function (e) {
            var node = e.target.closest('.menu-node');
            if (!node) { return; }
            dragged = node;
            node.classList.add('is-dragging');
            try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', ''); } catch (err) {}
        });

        root.addEventListener('dragend', function () {
            if (dragged) { dragged.classList.remove('is-dragging'); }
            dragged = null;
            persist();
        });

        // Разрешаем вставку в root и в любой children-список.
        var lists = [root].concat(Array.prototype.slice.call(root.querySelectorAll('[data-menu-children]')));
        lists.forEach(function (list) {
            list.addEventListener('dragover', function (e) {
                if (!dragged) { return; }
                // Ограничение глубины 1: пункт со своими детьми нельзя вкладывать.
                if (isChildList(list) && draggedHasChildren()) { return; }
                e.preventDefault();
                e.stopPropagation();
                var siblings = Array.prototype.slice.call(list.querySelectorAll(':scope > .menu-node:not(.is-dragging)'));
                var after = null;
                for (var i = 0; i < siblings.length; i++) {
                    var box = siblings[i].getBoundingClientRect();
                    if (e.clientY < box.top + box.height / 2) { after = siblings[i]; break; }
                }
                if (after == null) { list.appendChild(dragged); }
                else { list.insertBefore(dragged, after); }
                dragged.classList.toggle('menu-node--child', isChildList(list));
            });
        });

        function persist() {
            var ids = [];
            var parents = [];
            Array.prototype.forEach.call(root.querySelectorAll(':scope > .menu-node'), function (top) {
                ids.push(top.getAttribute('data-menu-id'));
                parents.push('');
                var childList = top.querySelector(':scope > [data-menu-children]');
                if (childList) {
                    Array.prototype.forEach.call(childList.querySelectorAll(':scope > .menu-node'), function (child) {
                        ids.push(child.getAttribute('data-menu-id'));
                        parents.push(top.getAttribute('data-menu-id'));
                    });
                }
            });

            var body = new URLSearchParams();
            body.append('csrf_token', root.getAttribute('data-csrf'));
            ids.forEach(function (id) { body.append('id[]', id); });
            parents.forEach(function (p) { body.append('parent_id[]', p); });

            fetch('/admin/menu/reorder', {
                method: 'POST', body: body, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); })
              .then(function (res) { if (!res.ok) { alert('Не удалось сохранить меню. Обновите страницу.'); } })
              .catch(function () { alert('Сетевая ошибка при сохранении меню.'); });
        }
    });

    // Языковые вкладки: переключение панелей внутри одной группы [data-lang-tabs]
    document.querySelectorAll('[data-lang-tabs]').forEach(function (group) {
        const buttons = group.querySelectorAll('.lang-tab-btn');
        const panels = group.querySelectorAll('.lang-tab-panel');
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                const target = btn.getAttribute('data-lang-target');
                buttons.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
                panels.forEach(function (p) {
                    p.classList.toggle('is-active', p.getAttribute('data-lang-panel') === target);
                });
            });
        });
    });
})();
