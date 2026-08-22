<?php
$user = auth();
$userName = $user['full_name'] ?? 'Committee Staff';
$pageTitle = 'Committee Dashboard';
ob_start();
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 shadow-md">
        <div class="relative px-6 py-8 sm:px-8">
            <div class="relative z-10 max-w-3xl">
                <p class="text-sm font-medium text-amber-100">COMMITTEE REVIEW</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Greetings, <?= htmlspecialchars($userName) ?>
                </h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-amber-100">
                    Review measures referred to your committee, conduct public hearings, consolidate committee
                    reports with recommendations, and return documents to Admin for routing to Plenary.
                </p>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div><p class="text-sm text-gray-500">For Review</p><p class="mt-2 text-3xl font-bold text-amber-600">0</p></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div><p class="text-sm text-gray-500">Under Hearing</p><p class="mt-2 text-3xl font-bold text-orange-600">0</p></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-50 text-orange-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div><p class="text-sm text-gray-500">Reported Out</p><p class="mt-2 text-3xl font-bold text-emerald-600">0</p></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5">
            <div class="flex items-start justify-between">
                <div><p class="text-sm text-gray-500">Avg. Review Days</p><p class="mt-2 text-3xl font-bold text-gray-900">0</p></div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-5">
        <div class="rounded-2xl border border-gray-200 bg-white lg:col-span-3">
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Committee Inbox</h2>
                    <p class="mt-1 text-xs text-gray-500">Referred measures pending review</p>
                </div>
                <select class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-600">
                    <option>All Committees</option>
                    <option>Appropriations</option>
                    <option>Laws &amp; Rules</option>
                    <option>Environment</option>
                </select>
            </div>
            <div class="divide-y divide-gray-100">
                <div class="px-6 py-10 text-center text-sm text-gray-500">
                    No referred measures yet. Routing will assign items to the appropriate committees.
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white lg:col-span-2">
            <div class="border-b border-gray-200 px-6 py-5">
                <h2 class="font-semibold text-gray-900">Committee Actions</h2>
                <p class="mt-1 text-xs text-gray-500">Standard workflows</p>
            </div>
            <div class="space-y-2 p-6">
                <button class="flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-amber-300 hover:bg-amber-50">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <span class="text-sm font-medium">Mark as Reported Out (Approve)</span>
                </button>
                <button class="flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-red-300 hover:bg-red-50">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-100 text-red-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <span class="text-sm font-medium">Return with Objections</span>
                </button>
                <button class="flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-blue-300 hover:bg-blue-50">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </span>
                    <span class="text-sm font-medium">Schedule Public Hearing</span>
                </button>
                <button class="flex w-full items-center gap-3 rounded-xl border border-gray-200 p-3 hover:border-emerald-300 hover:bg-emerald-50">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <span class="text-sm font-medium">Attach Committee Report</span>
                </button>
            </div>
        </div>
    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
