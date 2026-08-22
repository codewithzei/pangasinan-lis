<?php
$user = auth();
$userName = $user['full_name'] ?? 'SP Secretary';
$pageTitle = 'SP Secretary Dashboard';
ob_start();
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-sky-600 to-blue-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-sky-100">SANGGUNIANG PANLALAWIGAN SECRETARIAT</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Welcome, <?= htmlspecialchars($userName) ?>
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-sky-100">
                    Prepare session agendas, record minutes, manage ordinances &amp; resolutions numbers,
                    and coordinate with Committee chairs and the Presiding Officer.
                </p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">Next Session</p>
            <p class="mt-2 text-2xl font-bold text-gray-900">—</p>
            <p class="mt-1 text-xs text-sky-600">No scheduled session</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">Agenda Items</p>
            <p class="mt-2 text-3xl font-bold text-gray-900">0</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">Ordinances Filed</p>
            <p class="mt-2 text-3xl font-bold text-gray-900">0</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">Resolutions Filed</p>
            <p class="mt-2 text-3xl font-bold text-gray-900">0</p>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <div class="rounded-2xl border border-gray-200 bg-white">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Upcoming Sessions</h2>
                    <p class="mt-1 text-xs text-gray-500">Scheduled board meetings</p>
                </div>
                <button class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">Schedule</button>
            </div>
            <div class="p-6 text-center text-sm text-gray-500">No sessions scheduled yet.</div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-6 py-5">
                <h2 class="font-semibold text-gray-900">Secretariat Actions</h2>
                <p class="mt-1 text-xs text-gray-500">Frequently used tools</p>
            </div>
            <div class="grid grid-cols-2 gap-3 p-6">
                <button class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-sky-50 p-4 text-sky-700 hover:bg-sky-100">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="text-xs font-medium">Prepare Agenda</span>
                </button>
                <button class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-blue-50 p-4 text-blue-700 hover:bg-blue-100">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span class="text-xs font-medium">Record Minutes</span>
                </button>
                <button class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-violet-50 p-4 text-violet-700 hover:bg-violet-100">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    <span class="text-xs font-medium">Assign Tracking Nos.</span>
                </button>
                <button class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-emerald-50 p-4 text-emerald-700 hover:bg-emerald-100">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m0-4V7a4 4 0 118 0v5M12 19v2"/>
                    </svg>
                    <span class="text-xs font-medium">Certify Documents</span>
                </button>
            </div>
        </div>

    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
