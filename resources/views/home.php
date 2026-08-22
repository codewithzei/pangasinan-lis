<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= APP_SHORT_NAME ?> - Welcome</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/output.css">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="font-poppins bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    <!-- Navigation Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="mx-auto flex h-12 w-12 items-center justify-center overflow-hidden">
                    <img 
                        src="/Pangasinan-lis/public/assets/images/branding/logo.png" 
                        alt="Pangasinan LIS Logo" 
                        class="h-full w-full object-contain p-1"
                    />
                </div>
                <span class="text-xl font-bold text-gray-900 tracking-tight"><?= APP_NAME ?></span>
            </div>

            <nav class="flex items-center gap-4">
                <a href="<?= BASE_URL ?>/login" class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 transition-all shadow-sm flex items-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In
                </a>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="grow flex items-center justify-center">
        
        <!-- Hero Section -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 text-emerald-600 text-xs font-semibold mb-6">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                System Operational
            </div>

            <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 tracking-tight leading-tight max-w-3xl mx-auto">
                Welcome to <span class="text-primary"><?= APP_NAME ?></span>
            </h1>

            <p class="mt-4 text-lg text-gray-600 max-w-2xl mx-auto">
                The central hub for your operations and management workflow. Fast, secure, and easy to use.
            </p>

            <div class="mt-8 flex justify-center gap-4 flex-wrap">
                <a href="<?= BASE_URL ?>/login" class="px-6 py-3 bg-primary text-white font-medium rounded-xl shadow-md hover:shadow-lg hover:bg-primary/90 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In to Get Started
                </a>
                <a href="<?= BASE_URL ?>/docs" class="px-6 py-3 bg-white text-gray-700 border border-gray-200 font-medium rounded-xl hover:bg-gray-50 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-book"></i> Documentation
                </a>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-gray-500">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
            <div class="flex gap-6">
                <a href="#" class="hover:text-gray-900 transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-gray-900 transition-colors">Terms of Service</a>
                <a href="#" class="hover:text-gray-900 transition-colors">Support</a>
            </div>
        </div>
    </footer>

</body>

</html>