<?php
$user = auth();
$userName = $user['full_name'] ?? 'Admin Officer';
$pageTitle = 'Admin / Routing Dashboard';
ob_start();
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-indigo-100">ADMINISTRATION &amp; ROUTING</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Hi, <?= htmlspecialchars($userName) ?> &#9889;
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-indigo-100">
                    Review documents received from the Receiving desk, assign routing slips, and forward to
                    SP Secretary, appropriate Committees, Plenary, or Records.
                </p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div><p class="text-sm text-gray-500">In Queue</p><p class="mt-2 text-3xl font-bold">0</p></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div><p class="text-sm text-gray-500">Routed Today</p><p class="mt-2 text-3xl font-bold">0</p></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div><p class="text-sm text-gray-500">With Committee</p><p class="mt-2 text-3xl font-bold">0</p></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8zm4 2a4 4 0 00-4-4 4 4 0 00-4 4"/>
                    </svg>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div><p class="text-sm text-gray-500">Escalated</p><p class="mt-2 text-3xl font-bold">0</p></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-5">

        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-3">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Routing Queue</h2>
                    <p class="mt-1 text-xs text-gray-500">Documents requiring your routing decision</p>
                </div>
                <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                    Batch Assign
                </button>
            </div>
            <div class="divide-y divide-gray-100">
                <div class="flex items-center gap-4 px-6 py-6 text-center">
                    <div class="w-full">
                        <p class="text-sm font-medium text-gray-700">Queue is empty</p>
                        <p class="mt-1 text-xs text-gray-400">Documents from Receiving will appear here.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-2">
            <div class="border-b border-gray-200 px-6 py-5">
                <h2 class="font-semibold text-gray-900">Route To Destinations</h2>
                <p class="mt-1 text-xs text-gray-500">Common workstations</p>
            </div>
            <div class="space-y-3 p-6">
                <a class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-indigo-300 hover:bg-indigo-50" href="<?= BASE_URL ?>/spsec/dashboard">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">SP</span>
                    <div><p class="text-sm font-medium">SP Secretary</p><p class="text-xs text-gray-500">Schedule &amp; Agenda Prep</p></div>
                </a>
                <a class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-amber-300 hover:bg-amber-50" href="<?= BASE_URL ?>/committee/dashboard">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-600">C</span>
                    <div><p class="text-sm font-medium">Committees</p><p class="text-xs text-gray-500">Review &amp; Recommendations</p></div>
                </a>
                <a class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-violet-300 hover:bg-violet-50" href="<?= BASE_URL ?>/plenary/dashboard">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-100 text-violet-600">P</span>
                    <div><p class="text-sm font-medium">Plenary</p><p class="text-xs text-gray-500">Session Readings &amp; Voting</p></div>
                </a>
                <a class="flex items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-emerald-300 hover:bg-emerald-50" href="<?= BASE_URL ?>/records">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">R</span>
                    <div><p class="text-sm font-medium">Records / Archives</p><p class="text-xs text-gray-500">Filing &amp; Storage</p></div>
                </a>
            </div>
        </div>

    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
