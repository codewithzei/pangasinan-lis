<?php
$user = auth();
$userName = $user['full_name'] ?? 'Guest';
$userRole = $user['role_name'] ?? '—';
$initial = strtoupper(mb_substr($user['first_name'] ?? $userName, 0, 1) ?: 'U');

$dashboardRoute = dashboard_route_for_role($userRole);
$isMaster = is_role('Super Admin');

$currentRoute = $GLOBALS['route'] ?? '';
function isActiveNav(string $prefix, string $current): string
{
    return str_starts_with($current, $prefix)
        ? 'bg-blue-50 text-primary font-medium'
        : 'text-gray-600 hover:bg-gray-50 hover:text-primary';
}

$navBase = 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 transition';
?>

<aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-gray-200 bg-white
           -translate-x-full lg:translate-x-0 transition-transform duration-300"
>

    <div class="flex h-20 shrink-0 items-center border-b border-gray-200 px-6">
        <div class="flex items-center gap-3">
            <div class="mx-auto flex h-12 w-12 items-center justify-center overflow-hidden">
                <img 
                    src="/Pangasinan-lis/public/assets/images/branding/logo.png" 
                    alt="Pangasinan LIS Logo" 
                    class="h-full w-full object-contain p-1"
                />
            </div>
            <div>
                <h1 class="text-sm font-bold text-gray-900">Pangasinan <span class="text-blue-900">Legis+</span></h1>
                <p class="text-[11px] text-gray-500">Legislative Information System</p>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto px-4 py-5">

        <div class="mb-7">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Main</p>
            <nav class="space-y-1">
                <a href="<?= BASE_URL ?>/<?= $dashboardRoute ?>" class="<?= $navBase ?> <?= isActiveNav($dashboardRoute, $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-6H3v6zm10-18v6h8V3h-8z"/>
                    </svg>
                    Dashboard
                </a>
                <a href="<?= BASE_URL ?>/profile" class="<?= $navBase ?> <?= isActiveNav('profile', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    My Profile
                </a>
            </nav>
        </div>

        <!-- <div class="mb-7">

            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Legislative Workflow</p>
            <nav class="space-y-1">
                <?php if ($isMaster || is_role('Receiving Staff')): ?>
                <a href="<?= BASE_URL ?>/receiving/dashboard" class="<?= $navBase ?> <?= isActiveNav('receiving', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M4 4h16v16H4zM8 9h8M8 13h6"/>
                    </svg>
                    Receiving
                    <span class="ml-auto rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">0</span>
                </a>
                <?php endif; ?>

                <?php if ($isMaster || is_role('Admin')): ?>
                <a href="<?= BASE_URL ?>/admin/dashboard" class="<?= $navBase ?> <?= isActiveNav('admin/', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M4 6h16M4 12h10M4 18h7"/>
                    </svg>
                    Routing / Admin
                    <span class="ml-auto rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">0</span>
                </a>
                <?php endif; ?>

                <?php if ($isMaster || is_role('SP Secretary')): ?>
                <a href="<?= BASE_URL ?>/spsec/dashboard" class="<?= $navBase ?> <?= isActiveNav('spsec', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    SP Secretary
                </a>
                <?php endif; ?>

                <?php if ($isMaster || is_role('Plenary')): ?>
                <a href="<?= BASE_URL ?>/plenary/dashboard" class="<?= $navBase ?> <?= isActiveNav('plenary', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M6 20V10m6 10V4m6 16v-7"/>
                    </svg>
                    Plenary
                </a>
                <?php endif; ?>

                <?php if ($isMaster || is_role('Committee')): ?>
                <a href="<?= BASE_URL ?>/committee/dashboard" class="<?= $navBase ?> <?= isActiveNav('committee', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8zm4 2a4 4 0 00-4-4 4 4 0 00-4 4"/>
                    </svg>
                    Committees
                </a>
                <?php endif; ?>
            </nav>
        </div> -->

        <?php if ($isMaster): ?>
        <div class="mb-7">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Legislative Management</p>
            <nav class="space-y-1">
                <a href="<?= BASE_URL ?>/master/legislative-terms" class="<?= $navBase ?> <?= isActiveNav('master/legislative-terms', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Legislative Terms
                </a>
                <a href="<?= BASE_URL ?>/master/sp-members" class="<?= $navBase ?> <?= isActiveNav('master/sp-members', $currentRoute) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-icon lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><path d="M16 3.128a4 4 0 0 1 0 7.744"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></svg>
                    SP Members
                </a>
                <a href="<?= BASE_URL ?>/master/committees" class="<?= $navBase ?> <?= isActiveNav('master/committees', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Committees
                </a>
                <a href="<?= BASE_URL ?>/master/positions" class="<?= $navBase ?> <?= isActiveNav('master/positions', $currentRoute) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-shield-icon lucide-user-shield"><path d="M10 15H6a4 4 0 0 0-4 4v2"/><path d="M22 17.5c0 2.499-1.75 3.749-3.83 4.474a.5.5 0 0 1-.335-.005c-2.085-.72-3.835-1.97-3.835-4.47V14a.5.5 0 0 1 .5-.499c1 0 2.25-.6 3.12-1.36a.6.6 0 0 1 .76-.001c.875.765 2.12 1.36 3.12 1.36a.5.5 0 0 1 .5.5z"/><circle cx="9" cy="7" r="4"/></svg>
                    Positions
                </a>
            </nav>
        </div>

        <div class="mb-7">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">User &amp; Access Management</p>
            <nav class="space-y-1">
                <a href="<?= BASE_URL ?>/master/users" class="<?= $navBase ?> <?= isActiveNav('master/users', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    User Accounts
                </a>
                <a href="<?= BASE_URL ?>/master/user-roles" class="<?= $navBase ?> <?= isActiveNav('master/user-roles', $currentRoute) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-icon lucide-user"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    User Roles
                </a>
            </nav>
        </div>

        <div class="mb-7">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Location Management</p>
            <nav class="space-y-1">
                <a href="<?= BASE_URL ?>/master/municipalities" class="<?= $navBase ?> <?= isActiveNav('master/municipalities', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Municipalities
                </a>
                <a href="<?= BASE_URL ?>/master/districts" class="<?= $navBase ?> <?= isActiveNav('master/districts', $currentRoute) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-map-icon lucide-map"><path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/></svg>
                    Districts
                </a>
            </nav>
        </div>

        <div class="mb-7">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Document Management</p>
            <nav class="space-y-1">
                <a href="<?= BASE_URL ?>/master/document-types" class="<?= $navBase ?> <?= isActiveNav('master/document-types', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Document Types
                </a>
                <a href="<?= BASE_URL ?>/master/document-statuses" class="<?= $navBase ?> <?= isActiveNav('master/document-statuses', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Document Statuses
                </a>
                <a href="<?= BASE_URL ?>/master/checklists" class="<?= $navBase ?> <?= isActiveNav('master/checklists', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Checklists
                </a>
            </nav>
        </div>

        <div class="mb-7">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Routing &amp; Communication</p>
            <nav class="space-y-1">
                <a href="<?= BASE_URL ?>/master/routing-options" class="<?= $navBase ?> <?= isActiveNav('master/routing-options', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    Routing Options
                </a>
                <a href="<?= BASE_URL ?>/master/communication-categories" class="<?= $navBase ?> <?= isActiveNav('master/communication-categories', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    Communication Categories
                </a>
                <a href="<?= BASE_URL ?>/master/offices-opinion" class="<?= $navBase ?> <?= isActiveNav('master/offices-opinion', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Offices for Opinion
                </a>
                <a href="<?= BASE_URL ?>/master/status-opinion" class="<?= $navBase ?> <?= isActiveNav('master/status-opinion', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Status for Opinion
                </a>
            </nav>
        </div>

        <div class="mb-7">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">External References</p>
            <nav class="space-y-1">
                <a href="<?= BASE_URL ?>/master/external-offices" class="<?= $navBase ?> <?= isActiveNav('master/external-offices', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
                    </svg>
                    External Offices
                </a>
                <a href="<?= BASE_URL ?>/master/hospitals" class="<?= $navBase ?> <?= isActiveNav('master/hospitals', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Hospitals
                </a>
                <a href="<?= BASE_URL ?>/master/source-types" class="<?= $navBase ?> <?= isActiveNav('master/source-types', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                    Source Types
                </a>
            </nav>
        </div>

        <div class="mb-7">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">System Management</p>
            <nav class="space-y-1">
                <a href="<?= BASE_URL ?>/master/settings" class="<?= $navBase ?> <?= isActiveNav('master/settings', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    System Settings
                </a>
                <a href="<?= BASE_URL ?>/master/backup-restore" class="<?= $navBase ?> <?= isActiveNav('master/backup-restore', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Data Backup &amp; Restore
                </a>
            </nav>
        </div>

        <div class="mb-7">
            <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Audit &amp; Monitoring</p>
            <nav class="space-y-1">
                <a href="<?= BASE_URL ?>/master/system-logs" class="<?= $navBase ?> <?= isActiveNav('master/system-logs', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    System Logs
                </a>
                <a href="<?= BASE_URL ?>/master/audit-logs" class="<?= $navBase ?> <?= isActiveNav('master/audit-logs', $currentRoute) ?>">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Audit Logs
                </a>
            </nav>
        </div>
        <?php endif; ?>

    </div>

    <div class="shrink-0 border-t border-gray-200 p-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100">
                <span class="font-semibold text-primary"><?= htmlspecialchars($initial) ?></span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-gray-800"><?= htmlspecialchars($userName) ?></p>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($userRole) ?></p>
            </div>
            <form id="logoutForm" method="POST" action="<?= BASE_URL ?>/logout" class="m-0 hidden"></form>
            <button
                type="button"
                id="logoutBtn"
                class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600"
                title="Sign out"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </button>
        </div>
    </div>

</aside>