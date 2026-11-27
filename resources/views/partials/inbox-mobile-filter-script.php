<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-inbox-filter-target]').forEach(function (button) {
        const sheet = document.querySelector(button.dataset.inboxFilterTarget);
        if (!sheet) return;
        const close = function () {
            sheet.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };
        button.addEventListener('click', function () {
            sheet.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        });
        sheet.querySelectorAll('.inbox-filter-close, .inbox-filter-backdrop').forEach(function (element) {
            element.addEventListener('click', close);
        });
    });
});
</script>
