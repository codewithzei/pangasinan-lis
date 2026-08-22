<?php

$pageTitle = $pageTitle ?? APP_SHORT_NAME;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($pageTitle) ?> | <?= APP_SHORT_NAME ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>/public/assets/css/output.css"
    >

</head>

<body class="font-poppins bg-gray-50 text-gray-800">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div
        id="sidebarBackdrop"
        class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm opacity-0 invisible transition-all duration-300 lg:hidden"
    ></div>

    <div class="min-h-screen lg:ml-72">

        <?php require __DIR__ . '/header.php'; ?>

        <main class="p-4 sm:p-6 lg:p-8">

            <?= $content ?? '' ?>

        </main>

    </div>

    <div
        id="logoutModal"
        class="fixed inset-0 z-[100] hidden items-center justify-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="logoutModalTitle"
    >
        <div id="logoutModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
        <div
            id="logoutModalPanel"
            class="relative z-10 w-full max-w-sm mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200"
        >
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 id="logoutModalTitle" class="text-lg font-semibold text-gray-900">Sign out</h3>
                        <p class="mt-1 text-sm text-gray-600">Are you sure you want to sign out of your account?</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button
                    type="button"
                    id="logoutCancelBtn"
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    id="logoutConfirmBtn"
                    class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition"
                >
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Sign out
                </button>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/assets/js/app.js"></script>

</body>

</html>