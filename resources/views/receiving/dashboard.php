<?php
$user = auth();
$userName = $user['full_name'] ?? 'Receiving Staff';
$pageTitle = 'Receiving Dashboard';
ob_start();
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 to-teal-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-emerald-100">RECEIVING SECTION</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Welcome back, <?= htmlspecialchars($userName) ?>
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-emerald-100">
                    Record, log, and tag incoming legislative documents, requests, and communications as they
                    arrive. Assign tracking numbers before forwarding to Routing / Admin.
                </p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Received Today</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">0</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
            </div>
            <p class="mt-4 text-xs text-gray-400">New items logged today</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">For Routing</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">0</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="mt-4 text-xs text-gray-400">Awaiting Admin routing</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">Rejected / Returned</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">0</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="mt-4 text-xs text-gray-400">Deficient / incomplete docs</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-gray-500">This Month</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">0</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
            <p class="mt-4 text-xs text-gray-400">Total monthly receipts</p>
        </div>

    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border-2 border-dashed border-emerald-300 bg-emerald-50/50 p-8 text-center lg:col-span-1">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <h3 class="mt-4 text-lg font-semibold text-emerald-900">Log New Document</h3>
            <p class="mt-1 text-sm text-emerald-700">Assign tracking number, attach files, and record sender details.</p>
            <button class="mt-5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                + Receive Item
            </button>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white lg:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Inbox (Today)</h2>
                    <p class="mt-1 text-xs text-gray-500">Recently received items awaiting action</p>
                </div>
                <div class="flex gap-2">
                    <select class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-600">
                        <option>All Types</option>
                        <option>Ordinance</option>
                        <option>Resolution</option>
                        <option>Memo</option>
                    </select>
                </div>
            </div>
            <div class="p-6">
                <div class="py-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100">
                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-medium text-gray-700">Nothing in your inbox yet</p>
                    <p class="mt-1 text-xs text-gray-400">Use the "Receive Item" button to log your first document.</p>
                </div>
            </div>
        </div>
    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
