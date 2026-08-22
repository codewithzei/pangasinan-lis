<?php

$pageTitle = 'Dashboard';

ob_start();

?>

<div class="space-y-6">


    <!-- Welcome Banner -->
    <section class="overflow-hidden rounded-2xl bg-primary">

        <div class="relative px-6 py-7 sm:px-8">

            <div class="relative z-10 max-w-2xl">

                <p class="text-sm font-medium text-blue-100">
                    Administration Dashboard
                </p>

                <h1 class="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Welcome back, Administrator
                </h1>

                <p class="mt-2 max-w-xl text-sm leading-6 text-blue-100">
                    Monitor legislative documents, workflow activity,
                    routing, records, and system operations from one place.
                </p>

            </div>

            <div class="pointer-events-none absolute -right-10 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 right-32 h-72 w-72 rounded-full bg-white/5"></div>

        </div>

    </section>


    <!-- Statistics -->
    <section>

        <div class="mb-4 flex items-center justify-between">

            <div>
                <h2 class="text-base font-semibold text-gray-900">
                    System Overview
                </h2>

                <p class="text-xs text-gray-500">
                    Current legislative document activity
                </p>
            </div>

        </div>


        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">


            <!-- Received -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5">

                <div class="flex items-start justify-between">

                    <div>
                        <p class="text-sm text-gray-500">
                            Received Documents
                        </p>

                        <p class="mt-2 text-3xl font-bold text-gray-900">
                            0
                        </p>
                    </div>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary">

                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-width="1.8"
                                d="M6 2h9l5 5v15H6zM14 2v6h6M9 13h6M9 17h4"
                            />
                        </svg>

                    </div>

                </div>

                <div class="mt-4 flex items-center gap-2 text-xs">
                    <span class="rounded-full bg-gray-100 px-2 py-1 text-gray-500">
                        Today
                    </span>
                    <span class="text-gray-400">
                        No data yet
                    </span>
                </div>

            </div>


            <!-- Pending Routing -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5">

                <div class="flex items-start justify-between">

                    <div>
                        <p class="text-sm text-gray-500">
                            Pending Routing
                        </p>

                        <p class="mt-2 text-3xl font-bold text-gray-900">
                            0
                        </p>
                    </div>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">

                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M12 8v4l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>

                    </div>

                </div>

                <div class="mt-4 text-xs text-gray-400">
                    Documents awaiting routing
                </div>

            </div>


            <!-- In Process -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5">

                <div class="flex items-start justify-between">

                    <div>
                        <p class="text-sm text-gray-500">
                            In Process
                        </p>

                        <p class="mt-2 text-3xl font-bold text-gray-900">
                            0
                        </p>
                    </div>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">

                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-width="1.8"
                                d="M4 12h16M12 4v16"
                            />
                        </svg>

                    </div>

                </div>

                <div class="mt-4 text-xs text-gray-400">
                    Active legislative documents
                </div>

            </div>


            <!-- Archived -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5">

                <div class="flex items-start justify-between">

                    <div>
                        <p class="text-sm text-gray-500">
                            Archived Documents
                        </p>

                        <p class="mt-2 text-3xl font-bold text-gray-900">
                            0
                        </p>
                    </div>

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-600">

                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-width="1.8"
                                d="M4 7h16v13H4zM3 4h18v3H3zM9 11h6"
                            />
                        </svg>

                    </div>

                </div>

                <div class="mt-4 text-xs text-gray-400">
                    Historical records
                </div>

            </div>

        </div>

    </section>


    <!-- Main Dashboard Grid -->
    <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">


        <!-- Document Activity -->
        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-2">

            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">

                <div>
                    <h2 class="font-semibold text-gray-900">
                        Document Activity
                    </h2>

                    <p class="mt-1 text-xs text-gray-500">
                        Overview of documents by workflow stage
                    </p>
                </div>

                <select
                    class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600 outline-none focus:border-primary"
                >
                    <option>Last 7 days</option>
                    <option>Last 30 days</option>
                    <option>This year</option>
                </select>

            </div>


            <div class="p-6">

                <!-- Temporary chart area -->
                <div class="flex h-64 items-end justify-between gap-3">

                    <?php

                    $chartValues = [35, 55, 42, 70, 48, 80, 62];

                    foreach ($chartValues as $value):

                    ?>

                        <div class="flex h-full flex-1 flex-col justify-end">

                            <div
                                class="w-full rounded-t-lg bg-blue-100"
                                style="height: <?= $value ?>%;"
                            ></div>

                        </div>

                    <?php endforeach; ?>

                </div>


                <div class="mt-4 grid grid-cols-7 text-center text-[10px] text-gray-400">

                    <span>Mon</span>
                    <span>Tue</span>
                    <span>Wed</span>
                    <span>Thu</span>
                    <span>Fri</span>
                    <span>Sat</span>
                    <span>Sun</span>

                </div>

            </div>

        </div>


        <!-- Workflow Summary -->
        <div class="rounded-2xl border border-gray-200 bg-white">

            <div class="border-b border-gray-200 px-6 py-5">

                <h2 class="font-semibold text-gray-900">
                    Workflow Summary
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Current document status
                </p>

            </div>


            <div class="space-y-5 p-6">


                <div>

                    <div class="mb-2 flex items-center justify-between">

                        <span class="text-sm text-gray-600">
                            Receiving
                        </span>

                        <span class="text-xs font-medium text-gray-500">
                            0
                        </span>

                    </div>

                    <div class="h-2 rounded-full bg-gray-100">
                        <div class="h-2 w-0 rounded-full bg-blue-500"></div>
                    </div>

                </div>


                <div>

                    <div class="mb-2 flex items-center justify-between">

                        <span class="text-sm text-gray-600">
                            Routing
                        </span>

                        <span class="text-xs font-medium text-gray-500">
                            0
                        </span>

                    </div>

                    <div class="h-2 rounded-full bg-gray-100">
                        <div class="h-2 w-0 rounded-full bg-indigo-500"></div>
                    </div>

                </div>


                <div>

                    <div class="mb-2 flex items-center justify-between">

                        <span class="text-sm text-gray-600">
                            Plenary
                        </span>

                        <span class="text-xs font-medium text-gray-500">
                            0
                        </span>

                    </div>

                    <div class="h-2 rounded-full bg-gray-100">
                        <div class="h-2 w-0 rounded-full bg-violet-500"></div>
                    </div>

                </div>


                <div>

                    <div class="mb-2 flex items-center justify-between">

                        <span class="text-sm text-gray-600">
                            Committee
                        </span>

                        <span class="text-xs font-medium text-gray-500">
                            0
                        </span>

                    </div>

                    <div class="h-2 rounded-full bg-gray-100">
                        <div class="h-2 w-0 rounded-full bg-amber-500"></div>
                    </div>

                </div>


                <div>

                    <div class="mb-2 flex items-center justify-between">

                        <span class="text-sm text-gray-600">
                            Records
                        </span>

                        <span class="text-xs font-medium text-gray-500">
                            0
                        </span>

                    </div>

                    <div class="h-2 rounded-full bg-gray-100">
                        <div class="h-2 w-0 rounded-full bg-emerald-500"></div>
                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- Bottom Grid -->
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">


        <!-- Recent Documents -->
        <div class="rounded-2xl border border-gray-200 bg-white">

            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">

                <div>
                    <h2 class="font-semibold text-gray-900">
                        Recent Documents
                    </h2>

                    <p class="mt-1 text-xs text-gray-500">
                        Latest documents received by the system
                    </p>
                </div>

                <a
                    href="<?= BASE_URL ?>/receiving"
                    class="text-xs font-medium text-primary hover:text-primary-dark"
                >
                    View all
                </a>

            </div>


            <div class="p-6">

                <div class="py-8 text-center">

                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100">

                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M6 2h9l5 5v15H6zM14 2v6h6"
                            />
                        </svg>

                    </div>

                    <p class="mt-3 text-sm font-medium text-gray-700">
                        No documents yet
                    </p>

                    <p class="mt-1 text-xs text-gray-400">
                        Received documents will appear here.
                    </p>

                </div>

            </div>

        </div>


        <!-- Recent Activity -->
        <div class="rounded-2xl border border-gray-200 bg-white">

            <div class="border-b border-gray-200 px-6 py-5">

                <h2 class="font-semibold text-gray-900">
                    Recent Activity
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Latest system activities
                </p>

            </div>


            <div class="p-6">

                <div class="py-8 text-center">

                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100">

                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M12 8v4l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>

                    </div>

                    <p class="mt-3 text-sm font-medium text-gray-700">
                        No recent activity
                    </p>

                    <p class="mt-1 text-xs text-gray-400">
                        System activity will appear here.
                    </p>

                </div>

            </div>

        </div>

    </section>

</div>

<?php

$content = ob_get_clean();

require __DIR__ . '/../layouts/app.php';