/*
 * ArtEditor — миниатюрный автономный WYSIWYG-редактор (без npm/Composer/зависимостей).
 * Оборачивает <textarea data-wysiwyg> в панель форматирования + contenteditable.
 * Значение синхронизируется обратно в textarea (сервер прогоняет его через
 * TextProcessor). Использует document.execCommand — устаревший, но поддержан
 * во всех актуальных браузерах и не требует внешнего кода.
 */
(function () {
    'use strict';

    var BUTTONS = [
        { cmd: 'bold', label: 'Ж', title: 'Жирный', style: 'font-weight:700' },
        { cmd: 'italic', label: 'К', title: 'Курсив', style: 'font-style:italic' },
        { cmd: 'underline', label: 'П', title: 'Подчёркнутый', style: 'text-decoration:underline' },
        { cmd: 'formatBlock', value: 'H2', label: 'H2', title: 'Заголовок 2' },
        { cmd: 'formatBlock', value: 'H3', label: 'H3', title: 'Заголовок 3' },
        { cmd: 'formatBlock', value: 'P', label: '¶', title: 'Абзац' },
        { cmd: 'insertUnorderedList', label: '•—', title: 'Маркированный список' },
        { cmd: 'insertOrderedList', label: '1.', title: 'Нумерованный список' },
        { cmd: 'createLink', label: '🔗', title: 'Ссылка' },
        { cmd: 'unlink', label: '⛓', title: 'Убрать ссылку' },
        { cmd: 'removeFormat', label: '✕', title: 'Очистить форматирование' }
    ];

    function exec(cmd, value) {
        try { document.execCommand(cmd, false, value || null); } catch (e) {}
    }

    function attach(textarea) {
        if (textarea.dataset.wysiwygReady === '1') { return; }
        textarea.dataset.wysiwygReady = '1';

        var wrap = document.createElement('div');
        wrap.className = 'art-editor';

        var toolbar = document.createElement('div');
        toolbar.className = 'art-editor__toolbar';

        var area = document.createElement('div');
        area.className = 'art-editor__area';
        area.contentEditable = 'true';
        area.innerHTML = textarea.value || '';

        BUTTONS.forEach(function (b) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'art-editor__btn';
            btn.title = b.title;
            btn.textContent = b.label;
            if (b.style) { btn.setAttribute('style', b.style); }
            btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
            btn.addEventListener('click', function () {
                area.focus();
                if (b.cmd === 'createLink') {
                    var url = window.prompt('Адрес ссылки (https://…):', 'https://');
                    if (url) {
                        // Разрешаем только безопасные схемы.
                        if (/^(https?:|mailto:|tel:|\/)/i.test(url)) { exec('createLink', url); }
                        else { window.alert('Недопустимый адрес ссылки.'); }
                    }
                } else if (b.cmd === 'formatBlock') {
                    exec('formatBlock', b.value);
                } else {
                    exec(b.cmd);
                }
                sync();
            });
            toolbar.appendChild(btn);
        });

        function sync() { textarea.value = area.innerHTML; }
        area.addEventListener('input', sync);
        area.addEventListener('blur', sync);

        // Синхронизация при отправке формы.
        if (textarea.form) {
            textarea.form.addEventListener('submit', sync);
        }

        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(wrap, textarea);
        wrap.appendChild(toolbar);
        wrap.appendChild(area);
        wrap.appendChild(textarea);
    }

    window.ArtEditor = { attach: attach };
})();
