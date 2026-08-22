<?php

$pageTitle = 'Register';

$regSuccess = flash_get('success');
$regError = flash_get('error');
$regErrors = flash_get('errors', []);

$firstName = old('first_name');
$middleName = old('middle_name');
$lastName = old('last_name');
$suffix = old('suffix');
$contact = old('contact_number');
$email = old('email');
$username = old('username');

ob_start();
?>

<div class="min-h-screen bg-linear-to-br from-slate-50 via-blue-50 to-indigo-50 px-4 py-10">

    <div class="mx-auto max-w-xl">

        <div class="mb-6 text-center">
            <div class="mx-auto flex h-20 w-20 items-center justify-center overflow-hidden">
                <img 
                    src="/Pangasinan-lis/public/assets/images/branding/logo.png" 
                    alt="Pangasinan LIS Logo" 
                    class="h-full w-full object-contain p-1"
                />
            </div>
            <h1 class="mt-4 text-xl font-bold text-gray-900">Create your account</h1>
            <p class="mt-1 text-sm text-gray-500">Join Pangasinan Legis+</p>
        </div>

        <?php if ($regError): ?>
            <div class="mb-4 flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </span>
                <span class="text-red-800"><?= htmlspecialchars($regError) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($regErrors)): ?>
            <div class="mb-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="mb-1 font-semibold">Please review the following:</p>
                <ul class="list-disc space-y-0.5 pl-5">
                    <?php foreach ($regErrors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">

            <form action="<?= BASE_URL ?>/register" method="POST" class="space-y-4" novalidate>

                <div>
                    <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Personal Information</h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="sm:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-700">First *</label>
                            <input type="text" name="first_name" value="<?= $firstName ?>" required
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-blue-100" placeholder="Juan">
                        </div>
                        <div class="sm:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-700">Middle</label>
                            <input type="text" name="middle_name" value="<?= $middleName ?>"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-blue-100" placeholder="Dela">
                        </div>
                        <div class="sm:col-span-1">
                            <label class="mb-1 block text-xs font-medium text-gray-700">Last *</label>
                            <input type="text" name="last_name" value="<?= $lastName ?>" required
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-blue-100" placeholder="Cruz">
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Suffix</label>
                            <input type="text" name="suffix" value="<?= $suffix ?>"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-blue-100" placeholder="Jr., III">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-700">Contact Number</label>
                            <input type="tel" name="contact_number" value="<?= $contact ?>"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-blue-100" placeholder="09XXXXXXXXX">
                        </div>
                    </div>
                </div>

                <div class="h-px bg-gray-100"></div>

                <div>
                    <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400">Account Credentials</h3>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Username *</label>
                                <input type="text" name="username" value="<?= $username ?>" required autocomplete="username"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-blue-100" placeholder="juandcruz">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Email *</label>
                                <input type="email" name="email" value="<?= $email ?>" required autocomplete="email"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-blue-100" placeholder="you@pangasinan.gov.ph">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Password *</label>
                                <input type="password" name="password" required autocomplete="new-password"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-blue-100" placeholder="At least 6 characters">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-700">Confirm Password *</label>
                                <input type="password" name="password_confirm" required autocomplete="new-password"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-blue-100" placeholder="Re-enter password">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit"
                    class="w-full rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark focus:outline-none focus:ring-4 focus:ring-blue-100">
                    Create Account
                </button>

            </form>

            <p class="mt-6 text-center text-xs text-gray-500">
                Already registered?
                <a href="<?= BASE_URL ?>/login" class="font-semibold text-primary hover:text-primary-dark">Sign in instead</a>
            </p>

        </div>

        <p class="mt-6 text-center text-xs text-gray-400">Province of Pangasinan • Registration</p>

    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/auth.php';
