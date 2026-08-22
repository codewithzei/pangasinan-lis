<?php

$pageTitle = 'Login';

$loginSuccess = flash_get('success');
$loginError = flash_get('error');
$loginErrors = flash_get('errors', []);

$usernameVal = old('username');
$hasError = !empty($loginError) || !empty($loginErrors);

ob_start();
?>

<div class="min-h-screen flex items-center justify-center bg-linear-to-br from-slate-50 via-blue-50 to-indigo-50 px-4 py-10">

    <div class="w-full max-w-md">

        <div class="mb-8 text-center">
            <div class="mx-auto flex h-20 w-20 items-center justify-center overflow-hidden">
                <img 
                    src="/Pangasinan-lis/public/assets/images/branding/logo.png" 
                    alt="Pangasinan LIS Logo" 
                    class="h-full w-full object-contain p-1"
                />
            </div>

            <h1 class="mt-5 text-2xl font-bold text-gray-900">Pangasinan <span class="text-blue-900">Legis+</span></h1>
            <p class="mt-1 text-sm text-gray-500">Legislative Information System</p>
        </div>

        <?php if ($loginSuccess): ?>
            <div class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </span>
                <span class="text-emerald-800"><?= htmlspecialchars($loginSuccess) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($loginError): ?>
            <div class="mb-4 flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </span>
                <span class="text-red-800"><?= htmlspecialchars($loginError) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($loginErrors)): ?>
            <div class="mb-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc space-y-0.5 pl-5">
                    <?php foreach ($loginErrors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">

            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Welcome back</h2>
                <p class="mt-1 text-sm text-gray-500">Sign in to access the legislative information system.</p>
            </div>

            <form action="<?= BASE_URL ?>/login" method="POST" class="space-y-5" novalidate>

                <div>
                    <label for="username" class="mb-2 block text-sm font-medium text-gray-700">Username or Email</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= $usernameVal ?>"
                        placeholder="Enter your username"
                        autocomplete="username"
                        autofocus
                        class="w-full rounded-xl border <?= $hasError ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : 'border-gray-300 focus:ring-blue-100' ?> bg-white px-4 py-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:ring-4"
                    >
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <a href="#" class="text-xs font-medium text-primary hover:text-primary-dark">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pr-12 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-primary focus:ring-4 focus:ring-blue-100"
                        >
                        <button
                            type="button"
                            id="togglePassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                            aria-label="Show password"
                        >
                            <svg id="eyeIcon" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/>
                                <circle cx="12" cy="12" r="2.5" stroke-width="1.8"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary">
                        <span class="text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark focus:outline-none focus:ring-4 focus:ring-blue-100"
                >
                    Sign in
                </button>

            </form>

            <p class="mt-6 text-center text-xs text-gray-500">
                Don't have an account?
                <a href="<?= BASE_URL ?>/register" class="font-semibold text-primary hover:text-primary-dark">Create one</a>
            </p>

        </div>

        <p class="mt-6 text-center text-xs text-gray-400">Province of Pangasinan • Official Portal</p>

    </div>

</div>

<script>
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function () {
            password.type = password.type === 'password' ? 'text' : 'password';
        });
    }
    setTimeout(() => {
        document.querySelectorAll('[data-autodismiss]').forEach(el => el.style.display = 'none');
    }, 5000);
</script>

<?php

$content = ob_get_clean();

require __DIR__ . '/../layouts/auth.php';