document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.checkout-redirect');
        if (!btn) return;

        e.preventDefault();

        var id = btn.id;
        if (!id) return;

        window.location.href = '/?add-to-cart=' + id;
    });
});
