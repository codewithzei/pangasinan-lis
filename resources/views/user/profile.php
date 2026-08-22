<?php
$profile = $profile ?? auth();
$toastSuccess = null;
$toastError = null;
$toastErrors = [];

$firstName = old('first_name', $profile['first_name'] ?? '');
$middleName = old('middle_name', $profile['middle_name'] ?? '');
$lastName = old('last_name', $profile['last_name'] ?? '');
$suffix = old('suffix', $profile['suffix'] ?? '');
$contact = old('contact_number', $profile['contact_number'] ?? '');
$email = old('email', $profile['email'] ?? '');
$username = $profile['username'] ?? '';
$role = $profile['role_name'] ?? '';
$joinedAt = $profile['created_at'] ?? '—';

ob_start();
?>

<div class="space-y-6">

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="h-32 bg-gradient-to-r from-primary to-indigo-600"></div>
        <div class="px-6 pb-6 sm:px-8">
            <div class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-end gap-4">
                    <div class="flex h-24 w-24 items-center justify-center rounded-2xl border-4 border-white bg-blue-100 shadow-sm">
                        <span class="text-3xl font-bold text-primary">
                            <?= htmlspecialchars(strtoupper(mb_substr($firstName ?: $username, 0, 1) ?: 'U')) ?>
                        </span>
                    </div>
                    <div class="pb-1">
                        <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">
                            <?= htmlspecialchars(trim("$firstName $suffix $lastName") ?: $username) ?>
                        </h1>
                        <p class="mt-0.5 text-sm text-gray-500">
                            @<?= htmlspecialchars($username) ?>
                            <?php if ($role !== ''): ?>
                                &nbsp;•&nbsp; <span class="font-medium text-primary"><?= htmlspecialchars($role) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <span class="inline-flex self-start rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 sm:self-end">
                    Joined <?= htmlspecialchars($joinedAt) ?>
                </span>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form method="POST" action="<?= BASE_URL ?>/profile/update" class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-6 py-5">
                    <h2 class="font-semibold text-gray-900">Personal Information</h2>
                    <p class="mt-1 text-xs text-gray-500">Update your name and contact details</p>
                </div>
                <div class="grid grid-cols-1 gap-5 p-6 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">First Name *</label>
                        <input type="text" name="first_name" value="<?= $firstName ?>" required
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Middle Name</label>
                        <input type="text" name="middle_name" value="<?= $middleName ?>"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Last Name *</label>
                        <input type="text" name="last_name" value="<?= $lastName ?>" required
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Suffix</label>
                        <input type="text" name="suffix" value="<?= $suffix ?>" placeholder="Jr., Sr., III"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Email Address *</label>
                        <input type="email" name="email" value="<?= $email ?>" required
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Contact Number</label>
                        <input type="tel" name="contact_number" value="<?= $contact ?>" placeholder="09XXXXXXXXX"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="<?= BASE_URL ?>/<?= dashboard_route_for_role($role) ?>"
                   class="rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark focus:ring-4 focus:ring-blue-100">
                    Save Changes
                </button>
            </div>
        </form>

        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-6 py-5">
                    <h2 class="font-semibold text-gray-900">Account</h2>
                    <p class="mt-1 text-xs text-gray-500">Credentials and access</p>
                </div>
                <div class="space-y-4 p-6 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Username</span>
                        <span class="font-medium text-gray-800"><?= htmlspecialchars($username) ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Role</span>
                        <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"><?= htmlspecialchars($role) ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">Status</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                        </span>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white">
                <div class="border-b border-gray-200 px-6 py-5">
                    <h2 class="font-semibold text-gray-900">Security</h2>
                    <p class="mt-1 text-xs text-gray-500">Password and sign-in</p>
                </div>
                <div class="p-6 text-sm">
                    <p class="text-xs text-gray-500">Change password feature coming soon.</p>
                    <button disabled
                        class="mt-3 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-400 cursor-not-allowed">
                        Change Password
                    </button>
                </div>
            </div>
        </div>

    </section>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
