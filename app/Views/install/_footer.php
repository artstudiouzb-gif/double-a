<script nonce="<?= \App\Core\SecurityHeaders::nonce() ?>">
document.querySelectorAll('.install-card form').forEach(function (form) {
    form.addEventListener('submit', function () {
        var button = form.querySelector('button[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.textContent = 'Подождите…';
    });
});
</script>
</div>
</body>
</html>
