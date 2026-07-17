/* Webpush opt-in: кнопка «Уведомления о новостях» в блоке подписки футера.
   Появляется только если браузер поддерживает push и режим включён в админке
   (window.__pushEnabled). Состояние хранит сам браузер (getSubscription). */
(function () {
    'use strict';
    if (!window.__pushEnabled || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        return;
    }
    var host = document.querySelector('[data-push-optin]') || document.querySelector('.site-footer__subscribe') || document.querySelector('.site-footer');
    if (!host || Notification.permission === 'denied') { return; }
    function urlB64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) { out[i] = raw.charCodeAt(i); }
        return out;
    }

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'push-optin';
    var ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="16" height="16" aria-hidden="true"><path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>';

    var LABELS = window.__pushLabels || {};
    var LABEL_ON = LABELS.on || 'Уведомления включены';
    var LABEL_OFF = LABELS.off || 'Уведомления о новостях';

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function setState(subscribed) {
        btn.innerHTML = ICON + ' ' + escapeHtml(subscribed ? LABEL_ON : LABEL_OFF);
        btn.classList.toggle('is-on', subscribed);
        btn.setAttribute('aria-pressed', subscribed ? 'true' : 'false');
    }

    navigator.serviceWorker.register('/push-sw.js').then(function (reg) {
        return reg.pushManager.getSubscription().then(function (sub) {
            setState(!!sub);
            host.appendChild(btn);

            btn.addEventListener('click', function () {
                btn.disabled = true;
                fetch('/push/key').then(function (response) {
                    if (!response.ok) { throw new Error('push unavailable'); }
                    return response.json();
                }).then(function (config) {
                    if (!config.csrf_token) { throw new Error('csrf unavailable'); }
                    return reg.pushManager.getSubscription().then(function (current) {
                        if (current) {
                            // Отписка.
                            return fetch('/push/unsubscribe', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.csrf_token },
                                body: JSON.stringify({ endpoint: current.endpoint })
                            }).then(function () { return current.unsubscribe(); }).then(function () { setState(false); });
                        }
                        // Подписка: разрешение -> subscribe -> сервер.
                        return Notification.requestPermission().then(function (perm) {
                            if (perm !== 'granted') { return; }
                            return reg.pushManager.subscribe({
                                userVisibleOnly: true,
                                applicationServerKey: urlB64ToUint8Array(config.key)
                            }).then(function (sub) {
                                return fetch('/push/subscribe', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.csrf_token },
                                    body: JSON.stringify(sub.toJSON())
                                }).then(function () { setState(true); });
                            });
                        });
                    });
                }).catch(function () { /* молча: пользователь мог отклонить */ })
                  .finally(function () { btn.disabled = false; });
            });
        });
    }).catch(function () { /* SW не зарегистрировался — кнопку не показываем */ });
})();
