(function () {
    'use strict';

    var attachmentExtensions = [
        'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'docx',
        'doc', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'zip', 'rar'
    ];
    var modal;
    var title;
    var content;
    var downloadLink;

    function getExtension(url) {
        var path = url.split('?')[0].split('#')[0];
        var filename = path.substring(path.lastIndexOf('/') + 1);
        var dot = filename.lastIndexOf('.');
        return dot >= 0 ? filename.substring(dot + 1).toLowerCase() : '';
    }

    function createModal() {
        if (modal) return;

        modal = document.getElementById('documentAttachmentPreview');
        if (!modal) return;
        title = modal.querySelector('#attachmentPreviewTitle');
        content = modal.querySelector('[data-preview-content]');
        downloadLink = modal.querySelector('[data-preview-download]');

        modal.querySelectorAll('[data-preview-close]').forEach(function (element) {
            element.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        content.replaceChildren();
    }

    function showDownloadFallback(message) {
        content.innerHTML = '';
        var wrapper = document.createElement('div');
        wrapper.className = 'flex min-h-[35vh] flex-col items-center justify-center gap-3 text-center';
        var description = document.createElement('p');
        description.className = 'text-sm text-gray-700';
        description.textContent = message;
        wrapper.appendChild(description);
        content.appendChild(wrapper);
    }

    function renderPreview(url, filename) {
        var extension = getExtension(filename || url);
        content.replaceChildren();

        if (extension === 'pdf') {
            var frame = document.createElement('iframe');
            frame.src = url;
            frame.title = filename;
            frame.className = 'h-[78vh] min-h-[480px] w-full rounded-lg border border-gray-200 bg-white';
            content.appendChild(frame);
            return;
        }

        if (['png', 'jpg', 'jpeg', 'gif', 'webp'].indexOf(extension) !== -1) {
            var imageWrap = document.createElement('div');
            imageWrap.className = 'flex min-h-[50vh] items-center justify-center';
            var image = document.createElement('img');
            image.src = url;
            image.alt = filename;
            image.className = 'max-h-[78vh] max-w-full object-contain';
            imageWrap.appendChild(image);
            content.appendChild(imageWrap);
            return;
        }

        if (extension === 'docx') {
            var status = document.createElement('p');
            status.className = 'text-sm text-gray-600';
            status.textContent = 'Preparing document preview…';
            var preview = document.createElement('div');
            preview.className = 'min-h-[60vh] overflow-auto bg-white p-4';
            content.append(status, preview);

            Promise.all([
                loadScript(window.documentAttachmentPreviewBase + '/public/assets/js/jszip.min.js'),
                loadScript(window.documentAttachmentPreviewBase + '/public/assets/js/docx-preview.min.js')
            ]).then(function () {
                return fetch(url, { credentials: 'same-origin' });
            }).then(function (response) {
                if (!response.ok) throw new Error('The attachment could not be loaded.');
                return response.arrayBuffer();
            }).then(function (buffer) {
                if (!window.docx || typeof window.docx.renderAsync !== 'function') {
                    throw new Error('The document preview library did not load.');
                }
                return window.docx.renderAsync(buffer, preview);
            }).then(function () {
                status.remove();
            }).catch(function (error) {
                status.className = 'rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700';
                status.textContent = error.message || 'Unable to preview this document.';
            });
            return;
        }

        showDownloadFallback('Preview is not available for this file type. Use Download to open it in a compatible application.');
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[src="' + src + '"]');
            if (existing) {
                if (existing.dataset.loaded === 'true'
                    || (src.indexOf('docx-preview.min.js') !== -1 && window.docx)
                    || (src.indexOf('jszip.min.js') !== -1 && window.JSZip)) {
                    resolve();
                } else {
                    existing.addEventListener('load', resolve, { once: true });
                    existing.addEventListener('error', reject, { once: true });
                }
                return;
            }
            var script = document.createElement('script');
            script.src = src;
            script.onload = function () {
                script.dataset.loaded = 'true';
                resolve();
            };
            script.onerror = function () {
                reject(new Error('The document preview library could not be loaded.'));
            };
            document.head.appendChild(script);
        });
    }

    function isAttachmentLink(link) {
        if (link.hasAttribute('data-attachment-preview')) return true;
        var url = link.href || '';
        return link.target === '_blank'
            && attachmentExtensions.indexOf(getExtension(url)) !== -1;
    }

    function labelAsPreview(link) {
        Array.from(link.childNodes).forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                node.textContent = node.textContent.replace(/^\s*(Download|View)\s*$/i, 'Preview');
            }
        });
        if (!link.hasAttribute('aria-label') && !link.textContent.trim()) {
            link.setAttribute('aria-label', 'Preview attachment');
        }
        if (/^(Download|View)$/i.test((link.getAttribute('title') || '').trim())) {
            link.setAttribute('title', 'Preview attachment');
        }
    }

    document.querySelectorAll('a[href]').forEach(function (link) {
        if (isAttachmentLink(link)) labelAsPreview(link);
    });

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : event.target.parentElement;
        var link = target ? target.closest('a[href]') : null;
        if (!link || !isAttachmentLink(link)) return;

        event.preventDefault();
        createModal();
        if (!modal) return;

        var previewUrl = link.getAttribute('data-preview-url') || link.href;
        var filename = link.getAttribute('data-preview-name')
            || link.getAttribute('download')
            || decodeURIComponent((link.href.split('?')[0].split('#')[0].split('/').pop() || '').replace(/\+/g, ' '));

        title.textContent = filename || 'Attachment Preview';
        downloadLink.href = link.getAttribute('data-download-url') || link.href;
        downloadLink.setAttribute('download', '');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        renderPreview(previewUrl, filename);
    });

    window.documentAttachmentPreviewBase = document.currentScript
        ? document.currentScript.getAttribute('data-base-url') || ''
        : '';
}());
