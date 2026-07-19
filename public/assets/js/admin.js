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
            if (window.__enhanceIconFields) { window.__enhanceIconFields(wrapper); }
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

    // Стилизованное модальное подтверждение — замена нативного window.confirm.
    // Возвращает Promise<boolean>. Доступно и другим скриптам как window.adminConfirm.
    function adminConfirm(message) {
        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.className = 'admin-modal-overlay';
            overlay.innerHTML =
                '<div class="admin-modal" role="dialog" aria-modal="true" aria-labelledby="admin-modal-msg">'
                + '<div class="admin-modal__body">'
                + '<div class="admin-modal__icon" aria-hidden="true">?</div>'
                + '<p class="admin-modal__msg" id="admin-modal-msg"></p>'
                + '</div>'
                + '<div class="admin-modal__actions">'
                + '<button type="button" class="btn admin-modal__cancel">Отмена</button>'
                + '<button type="button" class="btn btn--primary admin-modal__ok">Подтвердить</button>'
                + '</div>'
                + '</div>';
            overlay.querySelector('.admin-modal__msg').textContent = message;
            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(function () { overlay.classList.add('is-open'); });

            var okBtn = overlay.querySelector('.admin-modal__ok');
            var cancelBtn = overlay.querySelector('.admin-modal__cancel');
            okBtn.focus();

            function close(result) {
                overlay.classList.remove('is-open');
                document.removeEventListener('keydown', onKey);
                document.body.style.overflow = '';
                setTimeout(function () { overlay.remove(); }, 150);
                resolve(result);
            }
            function onKey(e) {
                if (e.key === 'Escape') { close(false); }
                else if (e.key === 'Enter') { close(true); }
            }
            okBtn.addEventListener('click', function () { close(true); });
            cancelBtn.addEventListener('click', function () { close(false); });
            overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(false); } });
            document.addEventListener('keydown', onKey);
        });
    }
    window.adminConfirm = adminConfirm;

    document.querySelectorAll('[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === '1') { return; } // уже подтверждено — пропускаем
            event.preventDefault();
            adminConfirm(form.getAttribute('data-confirm')).then(function (ok) {
                if (!ok) { return; }
                form.dataset.confirmed = '1';
                if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
                else { form.submit(); }
            });
        });
    });

    // Применение шаблона страницы: режим «заменить» требует подтверждения.
    document.querySelectorAll('[data-snippet-insert]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var mode = form.querySelector('select[name=mode]');
            if (mode && mode.value === 'replace'
                && !window.confirm('Заменить все текущие блоки этого языка блоками из шаблона? Действие необратимо.')) {
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
        var formId = items.length ? items[0].getAttribute('form') : '';
        var bulkForm = formId ? document.getElementById(formId) : null;
        var counter = bulkForm ? bulkForm.querySelector('[data-bulk-count]') : null;
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
            var formId = form.id;
            var associated = Array.prototype.filter.call(document.querySelectorAll('[data-bulk-item]:checked'), function (item) {
                return item.getAttribute('form') === formId;
            });
            var anyChecked = associated.length > 0;
            var action = form.querySelector('[name="bulk_action"]');
            if (!anyChecked) { e.preventDefault(); alert('Выберите хотя бы одну запись.'); return; }
            if (action && !action.value) { e.preventDefault(); alert('Выберите действие.'); return; }
            if (action && action.value === 'trash'
                && !window.confirm('Переместить выбранные записи в корзину?')) {
                e.preventDefault();
            }
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

    // --- Медиабиблиотека: выбор или загрузка файла прямо из формы ---
    (function () {
        var modal = document.querySelector('[data-media-modal]');
        if (!modal) { return; }
        var grid = modal.querySelector('[data-media-grid]');
        var uploadBox = modal.querySelector('[data-media-upload]');
        var uploadInput = modal.querySelector('[data-media-upload-input]');
        var uploadButton = modal.querySelector('[data-media-upload-button]');
        var uploadStatus = modal.querySelector('[data-media-upload-status]');
        var currentTarget = null;
        var currentCallback = null; // режим выбора для WYSIWYG (вставка URL в контент)
        var loaded = false;
        var loadedType = null;
        var currentType = 'image';

        var typeOptions = {
            image: { accept: '.jpg,.jpeg,.png,.gif,.webp,.svg', label: 'изображение' },
            svg: { accept: '.svg,image/svg+xml', label: 'SVG-файл' },
            video: { accept: '.mp4,video/mp4', label: 'видео MP4' },
            document: { accept: '.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip', label: 'документ' },
            all_files: { accept: '', label: 'файл' },
            all: { accept: '', label: 'файл' }
        };

        function setUploadStatus(message, state) {
            if (!uploadStatus) { return; }
            uploadStatus.textContent = message || '';
            uploadStatus.classList.toggle('is-error', state === 'error');
            uploadStatus.classList.toggle('is-success', state === 'success');
        }

        function selectUrl(url) {
            if (currentCallback) {
                currentCallback(url);
            } else if (currentTarget) {
                currentTarget.value = url;
                currentTarget.dispatchEvent(new Event('change', { bubbles: true }));
            }
            close();
        }

        function fileMatchesType(file, type) {
            var name = file.name.toLowerCase();
            if (type === 'image') { return /\.(jpe?g|png|gif|webp|svg)$/.test(name); }
            if (type === 'svg') { return /\.svg$/.test(name); }
            if (type === 'video') { return /\.mp4$/.test(name); }
            if (type === 'document') { return /\.(pdf|docx?|xlsx?|txt|zip)$/.test(name); }
            return true;
        }

        function loadLibrary(type, force) {
            if (!force && loaded && loadedType === type) { return Promise.resolve(); }
            loaded = false; loadedType = type;
            grid.setAttribute('aria-busy', 'true');
            grid.innerHTML = '<div class="media-modal__empty">Загрузка…</div>';
            return fetch('/admin/media/list?type=' + encodeURIComponent(type), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    loaded = true;
                    grid.setAttribute('aria-busy', 'false');
                    var items = data.items || [];
                    if (!items.length) { grid.innerHTML = '<div class="media-modal__empty">В библиотеке нет подходящих файлов.</div>'; return; }
                    grid.innerHTML = '';
                    items.forEach(function (it) {
                        var fig = document.createElement('button');
                        fig.type = 'button';
                        fig.className = 'media-modal__item';
                        fig.title = it.name;
                        var isVideo = /\.(mp4|webm|ogg|mov|m4v)$/i.test(it.url);
                        var isImage = /\.(jpe?g|png|gif|svg|webp)$/i.test(it.url);
                        if (isVideo) {
                            fig.classList.add('media-modal__item--file');
                            fig.innerHTML = '<span class="media-modal__fileicon" aria-hidden="true">▶</span>'
                                + '<span class="media-modal__filename"></span>';
                            fig.querySelector('.media-modal__filename').textContent = it.name;
                        } else if (!isImage) {
                            fig.classList.add('media-modal__item--file');
                            fig.innerHTML = '<span class="media-modal__fileicon" aria-hidden="true">📄</span>'
                                + '<span class="media-modal__filename"></span>';
                            fig.querySelector('.media-modal__filename').textContent = it.name;
                        } else {
                            var img = document.createElement('img');
                            img.src = it.url; img.alt = it.name; img.loading = 'lazy';
                            fig.appendChild(img);
                        }
                        fig.addEventListener('click', function () { selectUrl(it.url); });
                        grid.appendChild(fig);
                    });
                })
                .catch(function () {
                    grid.setAttribute('aria-busy', 'false');
                    grid.innerHTML = '<div class="media-modal__empty">Ошибка загрузки.</div>';
                });
        }

        function open(targetSelector, callback, type) {
            currentTarget = targetSelector ? document.querySelector(targetSelector) : null;
            currentCallback = callback || null;
            currentType = type || 'image';
            var options = typeOptions[currentType] || typeOptions.all;
            if (uploadInput) {
                uploadInput.value = '';
                uploadInput.accept = options.accept;
            }
            setUploadStatus('Можно загрузить ' + options.label + ' прямо здесь. Максимальный размер — 200 МБ.');
            modal.hidden = false;
            loadLibrary(currentType, false);
        }
        function close() { modal.hidden = true; currentCallback = null; }

        if (uploadButton && uploadInput && uploadBox) {
            uploadButton.addEventListener('click', function () {
                if (!uploadInput.files || !uploadInput.files.length) {
                    setUploadStatus('Сначала выберите файл.', 'error');
                    return;
                }
                var file = uploadInput.files[0];
                var options = typeOptions[currentType] || typeOptions.all;
                if (!file.size) {
                    setUploadStatus('Нельзя загрузить пустой файл.', 'error');
                    return;
                }
                if (file.size > 200 * 1024 * 1024) {
                    setUploadStatus('Файл превышает максимальный размер 200 МБ.', 'error');
                    return;
                }
                if (!fileMatchesType(file, currentType)) {
                    setUploadStatus('Для этого поля нужен ' + options.label + '.', 'error');
                    return;
                }

                var chunkSize = 1024 * 1024;
                var total = Math.ceil(file.size / chunkSize);
                var uploadId = '';
                if (window.crypto && window.crypto.getRandomValues) {
                    var random = new Uint8Array(16);
                    window.crypto.getRandomValues(random);
                    random.forEach(function (value) { uploadId += value.toString(16).padStart(2, '0'); });
                } else {
                    for (var i = 0; i < 32; i++) { uploadId += Math.floor(Math.random() * 16).toString(16); }
                }
                uploadButton.disabled = true;
                uploadInput.disabled = true;
                setUploadStatus('Загрузка… 0%');

                function finish() {
                    uploadButton.disabled = false;
                    uploadInput.disabled = false;
                }

                function sendChunk(index) {
                    var fd = new FormData();
                    fd.append('csrf_token', uploadBox.getAttribute('data-csrf'));
                    fd.append('upload_id', uploadId);
                    fd.append('index', String(index));
                    fd.append('total', String(total));
                    fd.append('name', file.name);
                    fd.append('access_type', 'public');
                    fd.append('chunk', file.slice(index * chunkSize, Math.min((index + 1) * chunkSize, file.size)));

                    fetch('/admin/files/chunk', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'HTTP ' + r.status }; }); })
                        .then(function (res) {
                            if (!res.ok) { throw new Error(res.error || 'Не удалось загрузить файл.'); }
                            if (res.done) {
                                finish();
                                setUploadStatus('Файл загружен и выбран.', 'success');
                                loaded = false;
                                loadLibrary(currentType, true).then(function () {
                                    if (res.url) { selectUrl(res.url); }
                                });
                                return;
                            }
                            setUploadStatus('Загрузка… ' + Math.round(((index + 1) / total) * 100) + '%');
                            sendChunk(index + 1);
                        })
                        .catch(function (error) {
                            finish();
                            setUploadStatus('Ошибка: ' + error.message, 'error');
                        });
                }

                sendChunk(0);
            });
        }

        // Публичный API для редактора: выбор изображения/SVG с колбэком.
        window.MediaPicker = {
            pick: function (cb) { open(null, cb, 'image'); },
            pickSvg: function (cb) { open(null, cb, 'svg'); }
        };

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-media-pick]');
            if (btn) { e.preventDefault(); open(btn.getAttribute('data-media-target'), null, btn.getAttribute('data-media-type')); return; }
            if (e.target.closest('[data-media-close]') || e.target === modal) { close(); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
    })();

    // --- Поля SVG-иконок: код вручную ИЛИ выбор файла из медиабиблиотеки ---
    // К каждому textarea иконки добавляется панель с кнопкой «Выбрать из медиа»:
    // выбранный SVG-файл подгружается и вставляется как код (инлайн). Так поле
    // остаётся единым (icon_svg), а на сохранении код санитайзится сервером.
    (function () {
        function enhance(ta) {
            if (ta.getAttribute('data-icon-enhanced')) { return; }
            ta.setAttribute('data-icon-enhanced', '1');
            var bar = document.createElement('div');
            bar.className = 'icon-field__tools';
            var pick = document.createElement('button');
            pick.type = 'button'; pick.className = 'btn btn--small'; pick.textContent = 'Выбрать SVG из медиа';
            var clear = document.createElement('button');
            clear.type = 'button'; clear.className = 'btn btn--small'; clear.textContent = 'Очистить';
            bar.appendChild(pick); bar.appendChild(clear);
            ta.insertAdjacentElement('afterend', bar);

            pick.addEventListener('click', function () {
                if (!window.MediaPicker) { return; }
                window.MediaPicker.pickSvg(function (url) {
                    fetch(url, { credentials: 'same-origin' })
                        .then(function (r) { return r.text(); })
                        .then(function (txt) {
                            ta.value = txt.trim();
                            ta.dispatchEvent(new Event('input', { bubbles: true }));
                        })
                        .catch(function () { window.alert('Не удалось загрузить SVG-файл.'); });
                });
            });
            clear.addEventListener('click', function () {
                ta.value = '';
                ta.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }
        function enhanceIn(root) {
            (root || document).querySelectorAll('textarea[name$="[icon_svg]"], textarea[name="icon_svg"]').forEach(enhance);
        }
        window.__enhanceIconFields = enhanceIn;
        enhanceIn(document);
    })();

    // --- Живое значение ползунков прозрачности (overlay/подложка hero и др.) ---
    document.addEventListener('input', function (e) {
        var input = e.target.closest('input[type="range"][data-range-input]');
        if (!input) { return; }
        var out = document.querySelector('[data-range-output="' + input.getAttribute('data-range-input') + '"]');
        if (out) { out.textContent = input.value; }
    });

    // --- Hero: произвольная высота с единицей измерения. ---
    (function () {
        var mode = document.querySelector('[data-hero-height]');
        var custom = document.querySelector('[data-hero-custom-height]');
        var value = document.getElementById('hero_height_value');
        var unit = document.getElementById('hero_height_unit');
        if (!mode || !custom || !value || !unit) { return; }

        function sync() {
            custom.hidden = mode.value !== 'custom';
            var limits = unit.value === 'px' ? [160, 2000]
                : (unit.value === 'rem' ? [10, 120] : [20, 150]);
            value.min = String(limits[0]);
            value.max = String(limits[1]);
        }
        mode.addEventListener('change', sync);
        unit.addEventListener('change', sync);
        sync();
    })();

    // --- Поле изображения с превью (медиабиблиотека / URL / загрузка файла) ---
    (function () {
        function setPreview(field, src) {
            var box = field.querySelector('[data-image-preview]');
            if (!box) { return; }
            if (src) {
                box.innerHTML = '';
                var img = document.createElement('img');
                img.src = src; img.alt = ''; img.loading = 'lazy';
                box.appendChild(img);
            } else {
                box.innerHTML = '<span class="image-field__placeholder" aria-hidden="true">'
                    + '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
                    + '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M5 18l5-5 4 4 3-3 2 2"/></svg></span>';
            }
        }
        // URL-инпут (в т.ч. установленный медиабиблиотекой — она шлёт change).
        document.addEventListener('input', function (e) {
            var input = e.target.closest('[data-image-input]');
            if (!input) { return; }
            var field = input.closest('[data-image-field]');
            if (field) { setPreview(field, input.value.trim()); }
        });
        document.addEventListener('change', function (e) {
            var input = e.target.closest('[data-image-input]');
            if (input) {
                var f = input.closest('[data-image-field]');
                if (f) { setPreview(f, input.value.trim()); }
                return;
            }
            // Локальное превью выбранного файла (до загрузки на сервер).
            var file = e.target.closest('[data-image-file]');
            if (file && file.files && file.files[0]) {
                var field = file.closest('[data-image-field]');
                if (field && window.FileReader) {
                    var reader = new FileReader();
                    reader.onload = function (ev) { setPreview(field, ev.target.result); };
                    reader.readAsDataURL(file.files[0]);
                }
            }
        });
        // Очистка.
        document.addEventListener('click', function (e) {
            var clear = e.target.closest('[data-image-clear]');
            if (!clear) { return; }
            e.preventDefault();
            var field = clear.closest('[data-image-field]');
            if (!field) { return; }
            var input = field.querySelector('[data-image-input]');
            var file = field.querySelector('[data-image-file]');
            if (input) { input.value = ''; }
            if (file) { file.value = ''; }
            setPreview(field, '');
        });

        // Обложка (hero): выбор фото при типе фона «Без фона» раньше молча
        // терялся — снимок сохранялся, но не показывался. Переключаем список
        // сами, чтобы редактор видел, что фон стал фотографией.
        var syncHeroBg = function (target) {
            var bgSelect = document.querySelector('[data-hero-bg]');
            if (!bgSelect || bgSelect.value !== 'none') { return; }
            var field = target.closest('[data-image-field]');
            var input = field ? field.querySelector('[data-image-input]') : null;
            // Только поле фонового изображения обложки, не прочие картинки блока.
            if (!input || input.getAttribute('name') !== 'image') { return; }
            var hasImage = input.value.trim() !== ''
                || (field.querySelector('[data-image-file]') || {}).value;
            if (hasImage) { bgSelect.value = 'image'; }
        };
        document.addEventListener('input', function (e) {
            if (e.target.closest('[data-image-input]')) { syncHeroBg(e.target); }
        });
        document.addEventListener('change', function (e) {
            if (e.target.closest('[data-image-input]') || e.target.closest('[data-image-file]')) {
                syncHeroBg(e.target);
            }
        });
    })();

    // --- Автономный WYSIWYG (задача 75): инициализация на textarea[data-wysiwyg] ---
    if (window.ArtEditor) {
        document.querySelectorAll('textarea[data-wysiwyg]').forEach(function (ta) {
            window.ArtEditor.attach(ta);
        });
    }

    // --- Панель явного сохранения порядка ---
    // Раньше перетаскивание сохранялось мгновенно (AJAX на каждый drop). Теперь
    // изменения порядка копятся, а применяются только по кнопке «Сохранить» —
    // при уходе со страницы с несохранёнными правками браузер предупреждает.
    var ReorderBar = (function () {
        var bar = null, saveBtn = null, statusEl = null;
        var pendingSave = null, dirty = false, saving = false, hideTimer = null;

        function build() {
            bar = document.createElement('div');
            bar.className = 'reorder-bar';
            bar.setAttribute('hidden', '');
            bar.setAttribute('role', 'status');
            bar.setAttribute('aria-live', 'polite');
            bar.innerHTML = '<span class="reorder-bar__text"></span>'
                + '<button type="button" class="btn btn--small" data-reorder-cancel>Отменить</button>'
                + '<button type="button" class="btn btn--small btn--primary" data-reorder-save>Сохранить</button>';
            document.body.appendChild(bar);
            statusEl = bar.querySelector('.reorder-bar__text');
            saveBtn = bar.querySelector('[data-reorder-save]');
            saveBtn.addEventListener('click', function () {
                if (!pendingSave || saving) { return; }
                saving = true; saveBtn.disabled = true;
                statusEl.textContent = 'Сохранение…';
                pendingSave(function (ok, msg) {
                    saving = false; saveBtn.disabled = false;
                    if (ok) {
                        dirty = false;
                        statusEl.textContent = 'Порядок сохранён ✓';
                        hideTimer = window.setTimeout(function () { bar.setAttribute('hidden', ''); }, 1400);
                    } else {
                        statusEl.textContent = msg || 'Не удалось сохранить. Попробуйте ещё раз.';
                    }
                });
            });
            bar.querySelector('[data-reorder-cancel]').addEventListener('click', function () {
                // Отмена = вернуться к последнему сохранённому порядку (перезагрузка).
                dirty = false;
                window.location.reload();
            });
        }

        window.addEventListener('beforeunload', function (e) {
            if (dirty) { e.preventDefault(); e.returnValue = ''; return ''; }
        });

        return {
            markDirty: function (saveFn) {
                if (!bar) { build(); }
                if (hideTimer) { window.clearTimeout(hideTimer); hideTimer = null; }
                pendingSave = saveFn;
                dirty = true;
                statusEl.textContent = 'Есть несохранённые изменения порядка';
                bar.removeAttribute('hidden');
            }
        };
    })();

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
                ReorderBar.markDirty(saveOrder);
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

        function saveOrder(done) {
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
              .then(function (res) { done(!!res.ok, res.ok ? '' : 'Не удалось сохранить порядок.'); })
              .catch(function () { done(false, 'Сетевая ошибка при сохранении порядка.'); });
        }
    });

    // --- Меню: drag-and-drop сортировка + вложенность (задача 3, группа 3) ---
    document.querySelectorAll('[data-menu-sortable]').forEach(function (root) {
        var dragged = null, startParent = null, startNext = null, moved = false;

        function isChildList(list) { return list.hasAttribute('data-menu-children'); }
        function draggedHasChildren() {
            var kids = dragged.querySelector('[data-menu-children]');
            return kids && kids.querySelector('.menu-node');
        }

        root.addEventListener('dragstart', function (e) {
            var handle = e.target.closest('.menu-node__handle');
            var node = handle ? handle.closest('.menu-node') : null;
            if (!node || node.getAttribute('data-menu-lang') !== root.getAttribute('data-menu-lang')) {
                e.preventDefault();
                return;
            }
            dragged = node;
            startParent = node.parentNode;
            startNext = node.nextElementSibling;
            moved = false;
            node.classList.add('is-dragging');
            try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', ''); } catch (err) {}
        });

        root.addEventListener('dragend', function () {
            if (dragged) { dragged.classList.remove('is-dragging'); }
            if (dragged && (moved || dragged.parentNode !== startParent || dragged.nextElementSibling !== startNext)) {
                ReorderBar.markDirty(saveOrder);
            }
            dragged = null;
            startParent = null;
            startNext = null;
            moved = false;
        });

        // Разрешаем вставку в root и в любой children-список.
        var lists = [root].concat(Array.prototype.slice.call(root.querySelectorAll('[data-menu-children]')));
        lists.forEach(function (list) {
            list.addEventListener('dragover', function (e) {
                if (!dragged) { return; }
                // Ограничение глубины 1: пункт со своими детьми нельзя вкладывать.
                if (isChildList(list) && draggedHasChildren()) { return; }
                if (isChildList(list) && dragged.classList.contains('menu-node--divider')) { return; }
                // Нельзя поместить пункт внутрь его собственной области детей.
                if (dragged.contains(list)) { return; }
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
                moved = true;
            });
        });

        function saveOrder(done) {
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
              .then(function (res) { done(!!res.ok, res.ok ? '' : (res.error || 'Не удалось сохранить меню. Обновите страницу.')); })
              .catch(function () { done(false, 'Сетевая ошибка при сохранении меню.'); });
        }
    });

    // --- Меню: языковые вкладки, редактор и зависимые поля ---
    (function () {
        function syncMenuForm(form) {
            var type = form.querySelector('[data-menu-url-type]');
            var divider = form.querySelector('[data-menu-divider]');
            var lang = form.querySelector('[data-menu-lang-select]');
            var isDivider = !!(divider && divider.checked);
            form.querySelectorAll('[data-menu-link-only]').forEach(function (field) {
                field.hidden = isDivider;
            });
            form.querySelectorAll('[data-menu-url-field]').forEach(function (field) {
                field.hidden = isDivider || !type || field.getAttribute('data-menu-url-field') !== type.value;
            });
            form.querySelectorAll('[data-menu-parent-field]').forEach(function (field) {
                field.hidden = isDivider;
            });

            var parent = form.querySelector('[data-menu-parent-select]');
            if (parent && lang) {
                Array.prototype.forEach.call(parent.options, function (option, index) {
                    if (index === 0) { option.hidden = false; option.disabled = false; return; }
                    var matches = option.getAttribute('data-lang') === lang.value;
                    option.hidden = !matches;
                    option.disabled = !matches;
                    if (!matches && option.selected) { parent.value = ''; }
                });
            }
        }

        document.querySelectorAll('[data-menu-link-form]').forEach(syncMenuForm);

        document.addEventListener('change', function (e) {
            if (!e.target.matches('[data-menu-url-type], [data-menu-divider], [data-menu-lang-select]')) { return; }
            var form = e.target.closest('[data-menu-link-form]');
            if (form) syncMenuForm(form);
        });

        // Название пункта меню подставляется из заголовка выбранной страницы,
        // пока его не отредактировали вручную (флаг data-autofilled).
        document.addEventListener('change', function (e) {
            if (!e.target.matches('[data-menu-page-select]')) { return; }
            var form = e.target.closest('[data-menu-link-form]');
            if (!form) { return; }
            var titleInput = form.querySelector('input[name="title"]');
            if (!titleInput) { return; }
            var opt = e.target.options[e.target.selectedIndex];
            var pageTitle = opt ? (opt.getAttribute('data-title') || '') : '';
            if (pageTitle === '') { return; }
            if (titleInput.value.trim() === '' || titleInput.dataset.autofilled === '1') {
                titleInput.value = pageTitle;
                titleInput.dataset.autofilled = '1';
            }
        });
        document.addEventListener('input', function (e) {
            if (e.target.matches('[data-menu-link-form] input[name="title"]')) {
                delete e.target.dataset.autofilled;
            }
        });

        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-menu-edit-toggle]');
            if (toggle) {
                var panel = document.getElementById(toggle.getAttribute('aria-controls'));
                if (!panel) { return; }
                var opening = panel.hasAttribute('hidden');
                panel.toggleAttribute('hidden', !opening);
                toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
                if (opening) {
                    var first = panel.querySelector('input:not([type="hidden"]), select, textarea');
                    if (first) first.focus();
                }
                return;
            }
            var close = e.target.closest('[data-menu-edit-close]');
            if (close) {
                var editPanel = close.closest('[data-menu-edit-panel]');
                if (!editPanel) { return; }
                editPanel.setAttribute('hidden', '');
                var editToggle = document.querySelector('[aria-controls="' + editPanel.id + '"]');
                if (editToggle) { editToggle.setAttribute('aria-expanded', 'false'); editToggle.focus(); }
                return;
            }
            var tab = e.target.closest('[data-menu-lang-tab]');
            if (tab) {
                var code = tab.getAttribute('data-menu-lang-tab');
                try { localStorage.setItem('artstudio:admin-menu-lang', code); } catch (err) {}
                document.querySelectorAll('[data-menu-lang-tab]').forEach(function (button) {
                    var active = button === tab;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                document.querySelectorAll('[data-menu-lang-panel]').forEach(function (panel) {
                    panel.toggleAttribute('hidden', panel.getAttribute('data-menu-lang-panel') !== code);
                });
                var createLang = document.querySelector('#menu-add [data-menu-lang-select]');
                if (createLang) { createLang.value = code; syncMenuForm(createLang.closest('[data-menu-link-form]')); }
            }
        });

        try {
            var savedMenuLang = localStorage.getItem('artstudio:admin-menu-lang');
            if (savedMenuLang !== null) {
                var savedTab = Array.prototype.find.call(document.querySelectorAll('[data-menu-lang-tab]'), function (button) {
                    return button.getAttribute('data-menu-lang-tab') === savedMenuLang;
                });
                if (savedTab) savedTab.click();
            }
        } catch (e) {}
    })();

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

                if (group.hasAttribute('data-sync-block-language')) {
                    var search = window.location.search;
                    var hasBlockLang = search.indexOf('block_lang=') !== -1;
                    var param = 'block_lang=' + encodeURIComponent(target);
                    var newSearch = '';
                    if (hasBlockLang) {
                        newSearch = search.replace(/block_lang=[^&]*/g, param);
                    } else {
                        newSearch = search ? (search + '&' + param) : ('?' + param);
                    }
                    newSearch = newSearch.replace(/[&?]draft_saved=[^&]*/g, '');
                    newSearch = newSearch.replace(/&&+/g, '&').replace(/\?&/, '?').replace(/&$/, '');
                    if (search !== newSearch) {
                        window.location.assign(window.location.pathname + newSearch + window.location.hash);
                    }
                }
            });
        });
    });
})();

/* ==========================================================================
   Конструктор шапки: drag-and-drop микро-виджетов по зонам (палитра ↔ зоны).
   Палитра — источник доступных элементов. Неповторяемые (поиск, языки, тема,
   слабовидящие, соцсети, кнопка) размещаются по одному; «Разделитель» —
   повторяемый (клонируется из палитры). Порядок в зоне задаётся перетаскиванием.
   ========================================================================== */
(function () {
    'use strict';
    var REPEATABLE = ['divider', 'spacer', 'space'];
    var builders = document.querySelectorAll('[data-hdr-builder]');
    if (!builders.length) { return; }
    // Pro Max: палитра — общий источник (чипы клонируются), секции — приёмники.
    // Перетаскивание работает МЕЖДУ билдерами (глобальное состояние).
    var palette = document.querySelector('[data-hdr-zone="palette"]');
    var dragged = null;       // перетаскиваемый чип (клон или размещённый)
    var fromPalette = false;  // тянем из палитры (клонировать)

    function serializeAll() {
        builders.forEach(function (builder) {
            builder.querySelectorAll('[data-hdr-input]').forEach(function (input) {
                var dz = builder.querySelector('[data-hdr-zone="' + input.getAttribute('data-hdr-input') + '"]');
                if (!dz) { return; }
                var types = Array.prototype.map.call(dz.querySelectorAll('.hdr-chip'), function (c) {
                    return c.getAttribute('data-el');
                });
                input.value = types.join(',');
            });
        });
    }

    // Уникальность в пределах одной секции (билдера): повторяем только divider.
    function sectionHasType(builder, type) {
        return !!builder.querySelector('[data-hdr-zone]:not([data-hdr-zone="palette"]) .hdr-chip[data-el="' + type + '"]:not(.is-dragging)');
    }

    function makeChip(type) {
        var src = palette ? palette.querySelector('.hdr-chip[data-el="' + type + '"]') : null;
        if (!src) { return null; }
        var chip = src.cloneNode(true);
        chip.classList.add('hdr-chip--placed');
        bindChip(chip);
        return chip;
    }

    function bindChip(chip) {
        chip.addEventListener('dragstart', function (e) {
            fromPalette = !!chip.closest('[data-hdr-zone="palette"]');
            dragged = fromPalette ? makeChip(chip.getAttribute('data-el')) : chip;
            if (!fromPalette) {
                setTimeout(function () { chip.classList.add('is-dragging'); }, 0);
            }
            e.dataTransfer.effectAllowed = fromPalette ? 'copy' : 'move';
            try { e.dataTransfer.setData('text/plain', chip.getAttribute('data-el')); } catch (err) {}
        });
        chip.addEventListener('dragend', function () {
            chip.classList.remove('is-dragging');
            // Отменённое перетаскивание из палитры: убираем невставленный клон.
            if (fromPalette && dragged && !dragged.parentNode) { /* не вставлен */ }
            dragged = null;
            fromPalette = false;
            serializeAll();
        });
        var rm = chip.querySelector('.hdr-chip__remove, .hb-el__remove');
        if (rm) {
            rm.addEventListener('click', function () {
                if (chip.closest('[data-hdr-zone="palette"]')) { return; }
                chip.remove();
                serializeAll();
            });
        }
    }

    function afterElement(zone, x, y) {
        var chips = Array.prototype.slice.call(zone.querySelectorAll('.hdr-chip:not(.is-dragging)'));
        var closest = { offset: -Infinity, el: null };
        chips.forEach(function (c) {
            var box = c.getBoundingClientRect();
            // Горизонтальные зоны: сравниваем по X в пределах строки, иначе по Y.
            var offset = (Math.abs(y - (box.top + box.height / 2)) < box.height)
                ? x - box.left - box.width / 2
                : y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) { closest = { offset: offset, el: c }; }
        });
        return closest.el;
    }

    document.querySelectorAll('[data-hdr-zone]').forEach(function (zone) {
        var isPalette = zone.getAttribute('data-hdr-zone') === 'palette';
        zone.addEventListener('dragover', function (e) {
            if (!dragged) { return; }
            e.preventDefault();
            zone.classList.add('is-over');
            var type = dragged.getAttribute('data-el');

            if (isPalette) {
                // Бросок размещённого чипа в палитру = удаление из секции.
                if (!fromPalette && dragged.parentNode) { dragged.remove(); }
                return;
            }

            var builder = zone.closest('[data-hdr-builder]');
            // Не даём дублировать неповторяемый элемент в той же секции
            // (перенос внутри секции — можно; из палитры/другой секции — нет).
            var draggedBuilder = dragged.parentNode ? dragged.closest('[data-hdr-builder]') : null;
            if (REPEATABLE.indexOf(type) === -1 && draggedBuilder !== builder && sectionHasType(builder, type)) {
                return;
            }
            var after = afterElement(zone, e.clientX, e.clientY);
            if (after == null) { zone.appendChild(dragged); }
            else { zone.insertBefore(dragged, after); }
        });
        zone.addEventListener('dragleave', function () { zone.classList.remove('is-over'); });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('is-over');
            serializeAll();
        });
    });

    document.querySelectorAll('.hdr-chip').forEach(bindChip);
    serializeAll();
})();

/* Вкладки конструктора (Десктоп / Мобильный). */
(function () {
    'use strict';
    document.querySelectorAll('[data-hdr-tabs]').forEach(function (tabs) {
        var group = tabs.parentElement;
        tabs.querySelectorAll('[data-hdr-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var name = tab.getAttribute('data-hdr-tab');
                tabs.querySelectorAll('[data-hdr-tab]').forEach(function (t) {
                    t.classList.toggle('is-active', t === tab);
                });
                group.querySelectorAll('[data-hdr-panel]').forEach(function (p) {
                    p.classList.toggle('is-active', p.getAttribute('data-hdr-panel') === name);
                });
            });
        });
    });
})();

/* Конструктор футера: перестановка колонок стрелками. */
(function () {
    'use strict';
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-fb-move]');
        if (!btn) { return; }
        var row = btn.closest('.repeater-row');
        if (!row) { return; }
        if (btn.getAttribute('data-fb-move') === 'up') {
            var prev = row.previousElementSibling;
            if (prev) { row.parentNode.insertBefore(row, prev); }
        } else {
            var next = row.nextElementSibling;
            if (next) { row.parentNode.insertBefore(next, row); }
        }
    });
})();

/* Делегированные обработчики вместо инлайн-атрибутов (CSP без 'unsafe-inline'). */
(function () {
    'use strict';
    // Селект с автоотправкой формы (фильтры списков новостей/страниц/проектов).
    document.addEventListener('change', function (e) {
        var el = e.target;
        if (el.matches && el.matches('select[data-auto-submit]') && el.form) {
            el.form.submit();
            return;
        }
        // Селект типа виджета показывает поля выбранного типа.
        if (el.matches && el.matches('select[data-widget-type-select]')) {
            document.querySelectorAll('[data-wtype]').forEach(function (block) {
                block.style.display = block.getAttribute('data-wtype') === el.value ? 'flex' : 'none';
            });
        }
    });
})();

/* Локальное автосохранение контентных форм. Не сохраняем CSRF, файлы и
   пароли; черновик остаётся только в браузере редактора. */
(function () {
    'use strict';

    var currentUrl = new URL(window.location.href);
    var savedDraft = currentUrl.searchParams.get('draft_saved');
    if (savedDraft) {
        try { localStorage.removeItem('artstudio:draft:' + savedDraft); } catch (e) {}
        currentUrl.searchParams.delete('draft_saved');
        window.history.replaceState({}, document.title, currentUrl.pathname + currentUrl.search + currentUrl.hash);
    }

    document.querySelectorAll('form[data-content-draft]').forEach(function (form) {
        var key = 'artstudio:draft:' + form.getAttribute('data-content-draft');
        var dirty = false;

        function fields() {
            var values = {};
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el.name || el.disabled || el.type === 'file' || el.type === 'password'
                    || el.name === 'csrf_token' || el.name === 'expected_updated_at') { return; }
                if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { return; }
                if (values[el.name] !== undefined) {
                    if (!Array.isArray(values[el.name])) { values[el.name] = [values[el.name]]; }
                    values[el.name].push(el.value);
                } else {
                    values[el.name] = el.value;
                }
            });
            return values;
        }

        function save() {
            if (!dirty) { return; }
            try {
                localStorage.setItem(key, JSON.stringify({ savedAt: Date.now(), values: fields() }));
            } catch (e) {}
        }

        function apply(values) {
            form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function (el) {
                el.checked = false;
            });
            Object.keys(values || {}).forEach(function (name) {
                var controls = form.querySelectorAll('[name="' + CSS.escape(name) + '"]');
                var inputValues = Array.isArray(values[name]) ? values[name].map(String) : [String(values[name])];
                controls.forEach(function (el) {
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        el.checked = inputValues.indexOf(el.value) !== -1;
                    } else {
                        el.value = inputValues[0] || '';
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                        el.dispatchEvent(new Event('arteditor:restore'));
                    }
                });
            });
            dirty = true;
        }

        form.addEventListener('input', function () { dirty = true; });
        form.addEventListener('change', function () { dirty = true; });
        form.addEventListener('submit', function () {
            save();
            dirty = false;
        });
        window.setInterval(save, 20000);
        window.addEventListener('beforeunload', function (event) {
            save();
            if (dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        try {
            var draft = JSON.parse(localStorage.getItem(key) || 'null');
            if (!draft || !draft.savedAt || !draft.values) { return; }
            if (Date.now() - Number(draft.savedAt) > 7 * 24 * 60 * 60 * 1000) {
                localStorage.removeItem(key);
                return;
            }
            var banner = document.createElement('div');
            banner.className = 'alert alert--warning content-draft-banner';
            banner.innerHTML = '<span>Найден локальный черновик от ' + new Date(draft.savedAt).toLocaleString() + '.</span> '
                + '<button type="button" class="btn btn--small" data-draft-restore>Восстановить</button> '
                + '<button type="button" class="btn btn--small" data-draft-discard>Удалить</button>';
            form.parentNode.insertBefore(banner, form);
            banner.querySelector('[data-draft-restore]').addEventListener('click', function () {
                apply(draft.values);
                banner.remove();
            });
            banner.querySelector('[data-draft-discard]').addEventListener('click', function () {
                localStorage.removeItem(key);
                banner.remove();
            });
        } catch (e) {}
    });
})();

// --- Выбор картинки из медиабиблиотеки в строках повторителей ---
// Поля логотипов, фото и изображений внутри повторяющихся строк были обычными
// текстовыми input: путь приходилось вписывать руками. Кнопку добавляем
// автоматически всем таким полям — включая строки, добавленные уже после
// загрузки страницы (шаблон __INDEX__ клонируется скриптом повторителя).
(function () {
    var NAME_RE = /\[(image|logo|photo|cover|media)\]$/i;
    var seq = 0;

    function enhance(input) {
        if (!input || input.dataset.mediaEnhanced === '1') { return; }
        var name = input.getAttribute('name') || '';
        if (input.type !== 'text' || !NAME_RE.test(name)) { return; }
        // Поля, уже обёрнутые общим компонентом, трогать не нужно.
        if (input.closest('[data-image-field]') || input.hasAttribute('data-image-input')) { return; }
        input.dataset.mediaEnhanced = '1';

        if (!input.id) { input.id = 'mediafld_' + (++seq); }
        var pick = document.createElement('button');
        pick.type = 'button';
        pick.className = 'btn btn--small';
        pick.textContent = 'Медиабиблиотека';
        pick.setAttribute('data-media-pick', '');
        pick.setAttribute('data-media-target', '#' + input.id);

        var row = document.createElement('div');
        row.className = 'repeater-media';
        input.parentNode.insertBefore(row, input);
        row.appendChild(input);
        row.appendChild(pick);
    }

    function scan(root) {
        (root || document).querySelectorAll('input[type="text"]').forEach(enhance);
    }

    scan(document);
    // Новые строки повторителя появляются после клика «Добавить».
    if (window.MutationObserver) {
        new MutationObserver(function (records) {
            records.forEach(function (r) {
                Array.prototype.forEach.call(r.addedNodes, function (node) {
                    if (node.nodeType !== 1) { return; }
                    if (node.matches && node.matches('input[type="text"]')) { enhance(node); }
                    scan(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }
})();
