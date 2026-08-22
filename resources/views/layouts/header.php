<?php
$user = auth();
$headerName = $user['full_name'] ?? 'Guest';
$headerRole = $user['role_name'] ?? '';
$headerInitial = strtoupper(mb_substr($user['first_name'] ?? $headerName, 0, 1) ?: 'U');

$toastSuccess = flash_get('success');
$toastError = flash_get('error');
$toastErrors = flash_get('errors', []);
?>

<header class="sticky top-0 z-40 h-20 border-b border-gray-200 bg-white">

    <div class="flex h-full items-center justify-between px-4 sm:px-6 lg:px-8">

        <div class="flex items-center gap-4">

            <button
                id="sidebarToggle"
                type="button"
                class="rounded-xl p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>
            </button>

            <div>
                <h2 class="text-lg font-semibold text-gray-900">
                    <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
                </h2>

                <p class="hidden text-xs text-gray-500 sm:block">
                    Pangasinan Legislative Information System
                </p>
            </div>

        </div>

        <div class="flex items-center gap-2 sm:gap-4">

            <div class="hidden items-center gap-2 sm:flex">
                <?php if ($toastSuccess !== null && $toastSuccess !== ''): ?>
                    <div id="toast-success" class="fixed top-24 right-6 z-50 flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 shadow-lg animate-slide-in-right">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-emerald-800">Success</p>
                            <p class="text-xs text-emerald-700"><?= htmlspecialchars($toastSuccess) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($toastError !== null && $toastError !== ''): ?>
                    <div id="toast-error" class="fixed top-24 right-6 z-50 flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 px-4 py-3 shadow-lg">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-red-800">Error</p>
                            <p class="text-xs text-red-700"><?= htmlspecialchars($toastError) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($toastErrors)): ?>
                    <div id="toast-errors" class="fixed top-24 right-6 z-50 max-w-sm rounded-xl border border-red-100 bg-red-50 px-4 py-3 shadow-lg">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-red-800">Please review the following:</p>
                                <ul class="mt-1 list-disc space-y-0.5 pl-4 text-xs text-red-700">
                                    <?php foreach ($toastErrors as $err): ?>
                                        <li><?= htmlspecialchars($err) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button
                type="button"
                class="relative rounded-xl p-2.5 text-gray-500 hover:bg-gray-100"
                title="Notifications"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.5-1.5V11a6.5 6.5 0 00-13 0v4.5L4 17h5m6 0a3 3 0 01-6 0"/>
                </svg>
                <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500"></span>
            </button>

            <div class="hidden h-8 w-px bg-gray-200 sm:block"></div>

            <a
                href="<?= BASE_URL ?>/profile"
                class="flex items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-gray-50"
            >
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100">
                    <span class="text-sm font-semibold text-primary">
                        <?= htmlspecialchars($headerInitial) ?>
                    </span>
                </div>

                <div class="hidden text-left md:block">
                    <p class="text-sm font-semibold text-gray-800">
                        <?= htmlspecialchars($headerName) ?>
                    </p>
                    <p class="text-[11px] text-gray-500">
                        <?= htmlspecialchars($headerRole) ?>
                    </p>
                </div>

                <svg class="hidden h-4 w-4 text-gray-400 md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/>
                </svg>
            </a>

        </div>

    </div>

</header>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        ['toast-success', 'toast-error', 'toast-errors'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                setTimeout(() => { el.style.transition = 'opacity 300ms, transform 300ms'; el.style.opacity = '0'; el.style.transform = 'translateX(20px)'; }, 4500);
                setTimeout(() => { if (el.parentNode) el.parentNode.removeChild(el); }, 5000);
            }
        });
    });
</script>