<?php
$profile = $profile ?? auth();
$firstName = (string) old('first_name', $profile['first_name'] ?? '');
$middleName = (string) old('middle_name', $profile['middle_name'] ?? '');
$lastName = (string) old('last_name', $profile['last_name'] ?? '');
$suffix = (string) old('suffix', $profile['suffix'] ?? '');
$contact = (string) old('contact_number', $profile['contact_number'] ?? '');
$email = (string) old('email', $profile['email'] ?? '');
$username = (string) ($profile['username'] ?? '');
$role = (string) ($profile['role_name'] ?? '');
$status = trim((string) ($profile['status'] ?? 'Active'));
$statusLabel = ucfirst(strtolower($status));
$isActive = strtolower($status) === 'active';
$fullName = trim(implode(' ', array_filter([
    $firstName,
    $middleName,
    $lastName,
    $suffix,
])));
$joinedAt = '—';
if (!empty($profile['created_at'])) {
    $joinedTimestamp = strtotime((string) $profile['created_at']);
    if ($joinedTimestamp !== false) {
        $joinedAt = date('F j, Y', $joinedTimestamp);
    }
}
$initials = strtoupper(
    (mb_substr($firstName ?: $username, 0, 1) ?: 'U')
    . (mb_substr($lastName, 0, 1) ?: '')
);

ob_start();
?>

<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-indigo-700 shadow-md">
        <div class="pointer-events-none absolute -right-14 -top-24 h-72 w-72 rounded-full border-[32px] border-white/10"></div>
        <div class="pointer-events-none absolute bottom-0 right-1/4 h-32 w-32 rounded-full bg-white/5"></div>
        <div class="relative flex flex-col gap-6 px-6 py-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-5">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl border border-white/30 bg-white/15 text-2xl font-bold text-white shadow-inner sm:h-24 sm:w-24 sm:text-3xl">
                    <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-100">Account settings</p>
                    <h1 class="mt-1 truncate text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        <?= htmlspecialchars($fullName !== '' ? $fullName : $username, ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-blue-100">
                        <span>@<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($role !== ''): ?>
                            <span class="hidden text-blue-200 sm:inline" aria-hidden="true">•</span>
                            <span><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-2 text-sm text-white">
                <span class="h-2 w-2 rounded-full <?= $isActive ? 'bg-emerald-300' : 'bg-amber-300' ?>"></span>
                <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
        <form id="profileForm" method="POST" action="<?= BASE_URL ?>/profile/update" class="space-y-6 lg:col-span-2">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-start gap-4 border-b border-gray-100 px-6 py-5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m16 0v-2a4 4 0 00-3-3.87M15 3.13a4 4 0 010 7.75M14 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-900">Personal information</h2>
                        <p class="mt-1 text-sm text-gray-500">Keep your name and contact details up to date.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-x-5 gap-y-5 p-6 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="mb-1.5 block text-sm font-medium text-gray-700">
                            First name <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <input id="first_name" type="text" name="first_name" value="<?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>"
                               autocomplete="given-name" required
                               class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="middle_name" class="mb-1.5 block text-sm font-medium text-gray-700">Middle name</label>
                        <input id="middle_name" type="text" name="middle_name" value="<?= htmlspecialchars($middleName, ENT_QUOTES, 'UTF-8') ?>"
                               autocomplete="additional-name"
                               class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="last_name" class="mb-1.5 block text-sm font-medium text-gray-700">
                            Last name <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <input id="last_name" type="text" name="last_name" value="<?= htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8') ?>"
                               autocomplete="family-name" required
                               class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="suffix" class="mb-1.5 block text-sm font-medium text-gray-700">Suffix</label>
                        <input id="suffix" type="text" name="suffix" value="<?= htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Jr., Sr., III" autocomplete="honorific-suffix"
                               class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">
                            Email address <span class="text-red-500" aria-hidden="true">*</span>
                        </label>
                        <input id="email" type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                               autocomplete="email" required
                               class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="contact_number" class="mb-1.5 block text-sm font-medium text-gray-700">Contact number</label>
                        <input id="contact_number" type="tel" name="contact_number" value="<?= htmlspecialchars($contact, ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="09XXXXXXXXX" autocomplete="tel"
                               class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-primary focus:ring-4 focus:ring-blue-100">
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 bg-gray-50/70 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-gray-500"><span class="text-red-500">*</span> Required fields</p>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <a href="<?= BASE_URL ?>/<?= dashboard_route_for_role($role) ?>"
                           class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100">
                            Cancel
                        </a>
                        <button type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-dark focus:outline-none focus:ring-4 focus:ring-blue-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save changes
                        </button>
                    </div>
                </div>
            </section>
        </form>

        <aside class="space-y-6">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m16 0v-2a4 4 0 00-3-3.87M15 3.13a4 4 0 010 7.75M14 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="font-semibold text-gray-900">Account details</h2>
                            <p class="mt-0.5 text-xs text-gray-500">Your system account information</p>
                        </div>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 px-6">
                    <div class="flex items-start justify-between gap-4 py-4">
                        <dt class="text-sm text-gray-500">Username</dt>
                        <dd class="break-all text-right text-sm font-medium text-gray-900"><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-4">
                        <dt class="text-sm text-gray-500">Role</dt>
                        <dd class="text-right">
                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                <?= htmlspecialchars($role !== '' ? $role : '—', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-4">
                        <dt class="text-sm text-gray-500">Account status</dt>
                        <dd class="inline-flex items-center gap-2 text-sm font-medium <?= $isActive ? 'text-emerald-700' : 'text-amber-700' ?>">
                            <span class="h-2 w-2 rounded-full <?= $isActive ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 py-4">
                        <dt class="text-sm text-gray-500">Member since</dt>
                        <dd class="text-right text-sm font-medium text-gray-900"><?= htmlspecialchars($joinedAt, ENT_QUOTES, 'UTF-8') ?></dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-blue-100 bg-blue-50/70 p-6">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-primary shadow-sm">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2v2m-8 0h8m-9 0h10a1 1 0 011 1v6a1 1 0 01-1 1H8a1 1 0 01-1-1v-6a1 1 0 011-1zm2-2V7a3 3 0 016 0v2"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">Account security</h2>
                        <p class="mt-1 text-sm leading-6 text-gray-600">
                            Your username and access role are managed by the system. Contact your administrator if either needs to be changed.
                        </p>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
