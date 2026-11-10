(function () {
    'use strict';

    document.querySelectorAll('[data-approval-file-upload]').forEach(function (container) {
        var input = container.querySelector('[data-approval-file-input]');
        var zone = container.querySelector('[data-approval-file-zone]');
        var list = container.querySelector('[data-approval-file-list]');
        var error = container.querySelector('[data-approval-file-error]');
        var selected = [];
        var requirement = (container.getAttribute('data-required-when-radio') || '').split(':');

        if (!input || !zone || !list || !error) {
            return;
        }

        function syncRequired() {
            var required = requirement.length === 2
                && !!container.closest('form').querySelector(
                    'input[name="' + requirement[0] + '"]:checked[value="' + requirement[1] + '"]'
                );
            input.required = required;
            input.setAttribute('aria-required', required ? 'true' : 'false');
        }

        function syncInput() {
            var transfer = new DataTransfer();
            selected.forEach(function (file) { transfer.items.add(file); });
            input.files = transfer.files;
        }

        function render() {
            list.textContent = '';
            list.classList.toggle('hidden', selected.length === 0);

            selected.forEach(function (file, index) {
                var item = document.createElement('li');
                item.className = 'flex items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm';

                var name = document.createElement('span');
                name.className = 'truncate text-gray-700';
                name.textContent = file.name + ' (' + Math.ceil(file.size / 1024) + ' KB)';

                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'ml-3 shrink-0 text-xs font-medium text-red-600 hover:text-red-800';
                remove.textContent = 'Remove';
                remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.addEventListener('click', function () {
                    selected.splice(index, 1);
                    syncInput();
                    render();
                });

                item.appendChild(name);
                item.appendChild(remove);
                list.appendChild(item);
            });
        }

        function addFiles(files) {
            var errors = [];
            var allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
            Array.prototype.forEach.call(files, function (file) {
                var extension = (file.name.split('.').pop() || '').toLowerCase();
                if (!allowed.includes(extension)) {
                    errors.push(file.name + ': unsupported file type.');
                } else if (file.size > 25 * 1024 * 1024) {
                    errors.push(file.name + ': exceeds 25 MB.');
                } else if (selected.length >= 10) {
                    errors.push('A maximum of 10 files is allowed.');
                } else if (!selected.some(function (item) {
                    return item.name === file.name && item.size === file.size;
                })) {
                    selected.push(file);
                }
            });

            error.textContent = errors.join(' ');
            error.classList.toggle('hidden', errors.length === 0);
            syncInput();
            render();
        }

        zone.addEventListener('click', function () { input.click(); });
        zone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                input.click();
            }
        });
        zone.addEventListener('dragover', function (event) {
            event.preventDefault();
            zone.classList.add('border-primary', 'bg-primary/5');
        });
        zone.addEventListener('dragleave', function () {
            zone.classList.remove('border-primary', 'bg-primary/5');
        });
        zone.addEventListener('drop', function (event) {
            event.preventDefault();
            zone.classList.remove('border-primary', 'bg-primary/5');
            addFiles(event.dataTransfer.files);
        });
        input.addEventListener('change', function () {
            addFiles(input.files);
        });
        input.addEventListener('invalid', function () {
            zone.focus();
        });

        var form = container.closest('form');
        form.querySelectorAll('input[name="' + requirement[0] + '"]').forEach(function (radio) {
            radio.addEventListener('change', syncRequired);
        });
        form.addEventListener('reset', function () {
            selected = [];
            render();
            error.textContent = '';
            error.classList.add('hidden');
            window.setTimeout(syncRequired, 0);
        });

        syncRequired();
    });
}());
