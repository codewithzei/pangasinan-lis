<div id="documentAttachmentPreview"
     class="fixed inset-0 z-[120] hidden items-center justify-center p-3 sm:p-6"
     role="dialog"
     aria-modal="true"
     aria-labelledby="attachmentPreviewTitle">
    <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm"
         data-preview-close></div>
    <section class="relative z-10 flex max-h-[94vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
        <header class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 sm:px-6">
            <h2 id="attachmentPreviewTitle"
                class="min-w-0 truncate text-base font-semibold text-gray-900"></h2>
            <div class="flex shrink-0 items-center gap-2">
                <a class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                   data-preview-download>Download</a>
                <button type="button"
                        class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        data-preview-close>Close</button>
            </div>
        </header>
        <div class="min-h-[40vh] flex-1 overflow-auto bg-gray-100 p-3 sm:p-5"
             data-preview-content></div>
    </section>
</div>
