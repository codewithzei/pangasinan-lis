<?php
$user = auth();
$userName = $user['full_name'] ?? 'Plenary Staff';
$pageTitle = 'Plenary Dashboard';
ob_start();
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 to-purple-700 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-violet-100">PLENARY SESSIONS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Good day, <?= htmlspecialchars($userName) ?>
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-violet-100">
                    Manage the 1st, 2nd, and 3rd readings of ordinances and resolutions. Record session
                    attendance, voting results, approved measures, and transmit final copies to Records.
                </p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">On First Reading</p>
            <p class="mt-2 text-3xl font-bold text-violet-700">0</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">On Second Reading</p>
            <p class="mt-2 text-3xl font-bold text-purple-700">0</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">On Third Reading</p>
            <p class="mt-2 text-3xl font-bold text-indigo-700">0</p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <p class="text-sm text-gray-500">Approved This Month</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600">0</p>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white lg:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Session Floor</h2>
                    <p class="mt-1 text-xs text-gray-500">Current reading calendar</p>
                </div>
                <select class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-600">
                    <option>Regular Session</option>
                    <option>Special Session</option>
                </select>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    <div class="rounded-xl border-2 border-dashed border-violet-200 bg-violet-50/40 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">No Items</p>
                        <p class="mt-2 text-sm text-gray-700">The plenary calendar is empty. Documents forwarded from Committees will appear here for readings.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white">
            <div class="border-b border-gray-200 px-6 py-5">
                <h2 class="font-semibold text-gray-900">Session Attendance</h2>
                <p class="mt-1 text-xs text-gray-500">Board members present</p>
            </div>
            <div class="p-6">
                <div class="mb-4 flex items-baseline justify-between">
                    <div>
                        <span class="text-3xl font-bold text-gray-900">0</span>
                        <span class="ml-1 text-sm text-gray-400">/ 0</span>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">0%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full w-0 rounded-full bg-violet-500"></div>
                </div>
                <button class="mt-5 w-full rounded-xl bg-violet-600 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                    Take Attendance
                </button>
            </div>
        </div>
    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
