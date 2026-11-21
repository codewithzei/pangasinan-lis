<div id="acceptConfirmModal"
     class="fixed inset-0 z-[10000] hidden items-center justify-center"
     role="dialog"
     aria-modal="true"
     aria-labelledby="acceptConfirmTitle"
     aria-describedby="acceptConfirmBody">
    <div id="acceptConfirmBackdrop" class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"></div>
    <div class="relative z-10 mx-4 w-full max-w-md rounded-2xl border border-gray-100 bg-white shadow-2xl">
        <div class="p-6">
            <h2 id="acceptConfirmTitle" class="mb-2 text-base font-semibold text-gray-900">
                Confirm Document Acceptance
            </h2>
            <p id="acceptConfirmBody" class="text-sm text-gray-600">
                Accepting this document will assign it to you for processing.
            </p>
        </div>
        <div class="flex items-center justify-end gap-3 rounded-b-2xl border-t border-gray-100 bg-gray-50 px-6 py-4">
            <button type="button" id="acceptConfirmCancel"
                    class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
                Cancel
            </button>
            <button type="button" id="acceptConfirmSubmit"
                    class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                Accept Document
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('acceptForm');
    const trigger = document.getElementById('acceptBtn');
    const modal = document.getElementById('acceptConfirmModal');
    const backdrop = document.getElementById('acceptConfirmBackdrop');
    const cancelButton = document.getElementById('acceptConfirmCancel');
    const confirmButton = document.getElementById('acceptConfirmSubmit');

    if (!form || !trigger || !modal || !backdrop || !cancelButton || !confirmButton) return;

    let confirmed = false;

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        trigger.focus();
    }

    form.addEventListener('submit', function (event) {
        if (confirmed) {
            confirmed = false;
            trigger.disabled = true;
            trigger.innerHTML =
                '<svg class="h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                '</svg><span class="ml-2">Accepting…</span>';
            return;
        }

        event.preventDefault();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        cancelButton.focus();
    });

    confirmButton.addEventListener('click', function () {
        confirmed = true;
        form.dataset.confirmed = 'true';
        closeModal();
        form.requestSubmit();
    });

    cancelButton.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });
});
</script>
