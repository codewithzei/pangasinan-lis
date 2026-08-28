<?php
ob_start();

$success = $success ?? null;
$error = $error ?? null;
$term = $term ?? null;
$availableMembers = $availableMembers ?? [];
$legislators = $legislators ?? [];
$pageTitle = $pageTitle ?? 'Term Details';
$pageSubtitle = $pageSubtitle ?? '';
$accent = $accent ?? 'primary';

function formatName(array $row): string
{
    $parts = array_filter([
        $row['first_name'] ?? '',
        $row['middle_name'] ?? '',
        $row['last_name'] ?? '',
        $row['suffix'] ?? '',
    ]);
    return trim(implode(' ', $parts));
}
?>

<div class="space-y-6">

    <nav class="flex items-center gap-2 text-xs font-medium text-gray-500">
        <a href="<?= BASE_URL ?>/master/legislative-terms" class="hover:text-primary transition">
            Legislative Terms
        </a>
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-800">Term Details</span>
    </nav>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <div class="relative px-6 py-6 sm:px-8 border-b border-gray-100">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl <?= !empty($term['is_active']) ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-100 text-gray-500' ?>">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($term['name'] ?? '') ?></h1>
                            <?php if (!empty($term['is_active'])): ?>
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 border border-gray-200">
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($term['description'])): ?>
                            <p class="mt-1 text-sm text-gray-500"><?= htmlspecialchars($term['description'] ?? '') ?></p>
                        <?php endif; ?>
                        <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Session #<?= (int)($term['session_number'] ?? 0) ?> · <?= htmlspecialchars($term['year'] ?? '') ?>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?= !empty($term['start_date']) ? date('F j, Y', strtotime($term['start_date'])) : '—' ?> — <?= !empty($term['end_date']) ? date('F j, Y', strtotime($term['end_date'])) : '—' ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="<?= BASE_URL ?>/master/legislative-terms"
                       class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-px bg-gray-100 sm:grid-cols-2">
            <div class="bg-white px-6 py-4 text-center">
                <div class="text-xs font-medium text-gray-500">Total SP Members</div>
                <div class="mt-1 text-2xl font-bold text-gray-900"><?= (int)($term['member_count'] ?? 0) ?></div>
            </div>
            <div class="bg-white px-6 py-4 text-center">
                <div class="text-xs font-medium text-blue-600">Available to Assign</div>
                <div class="mt-1 text-2xl font-bold text-gray-900"><?= count($availableMembers) ?></div>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">

        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-1 h-fit">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Assign SP Members</h2>
                    <p class="mt-1 text-xs text-gray-500">Add SP members to this term</p>
                </div>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/master/legislative-terms/assign-legislators" class="p-6 space-y-4">
                <input type="hidden" name="term_id" value="<?= (int)($term['id'] ?? 0) ?>">
                
                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label class="block text-xs font-semibold text-gray-700">Search Members</label>
                        <button type="button" id="clearSearch" class="text-xs text-gray-400 hover:text-gray-600 transition hidden">
                            Clear
                        </button>
                    </div>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="memberSearch" placeholder="Search by name, position, or district..."
                            class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm text-gray-800 placeholder-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label class="block text-xs font-semibold text-gray-700">SP Members</label>
                        <label class="flex items-center gap-1.5 text-[11px] text-gray-500">
                            <input type="checkbox" id="selectAllMembers" class="h-3.5 w-3.5 rounded border-gray-300 text-primary focus:ring-primary">
                            <span id="selectAllLabel">Select all (<span id="visibleCount"><?= count($availableMembers) ?></span>)</span>
                        </label>
                    </div>
                    <div class="max-h-96 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50 p-2 space-y-1" id="membersList">
                        <?php if (empty($availableMembers)): ?>
                            <div class="py-8 text-center text-xs text-gray-400" id="emptyState">
                                All active SP members are already assigned to this term.
                            </div>
                        <?php else: ?>
                            <div class="hidden py-8 text-center text-xs text-gray-400" id="noResultsState">
                                <svg class="mx-auto h-8 w-8 mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                No members match your search.
                            </div>
                            <?php foreach ($availableMembers as $m):
                                $full = formatName($m);
                                $mId = (int)($m['id'] ?? 0);
                                $position = htmlspecialchars($m['position'] ?? '');
                                $district = htmlspecialchars($m['district_name'] ?? '');
                                $searchData = strtolower($full . ' ' . $position . ' ' . $district);
                            ?>
                            <label class="member-item flex items-center gap-2.5 rounded-lg p-2 hover:bg-white cursor-pointer transition" data-search="<?= htmlspecialchars($searchData) ?>">
                                <input type="checkbox" name="member_ids[]" value="<?= $mId ?>"
                                    class="member-checkbox h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary">
                                <?php if (!empty($m['photo_path'])): ?>
                                    <img src="<?= htmlspecialchars($m['photo_path']) ?>"
                                         alt="" class="h-8 w-8 rounded-full object-cover border border-gray-200">
                                <?php else: ?>
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-[11px] font-semibold text-primary">
                                        <?= strtoupper(mb_substr($m['first_name'] ?? '', 0, 1) . mb_substr($m['last_name'] ?? '', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-gray-800"><?= htmlspecialchars($full) ?></div>
                                    <div class="truncate text-[11px] text-gray-500">
                                        <?= $position ?><?= $district ? ' · ' . $district : '' ?>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" id="assignButton"
                    <?= empty($availableMembers) ? 'disabled' : '' ?>
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Assign to Term
                </button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white xl:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                <div>
                    <h2 class="font-semibold text-gray-900">Assigned SP Members</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        <?= count($legislators) ?> member<?= count($legislators) !== 1 ? 's' : '' ?> assigned to this term
                    </p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-medium">Member</th>
                            <th class="px-6 py-3 font-medium">Position</th>
                            <th class="px-6 py-3 font-medium">District</th>
                            <th class="px-6 py-3 font-medium">Assigned</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($legislators)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">
                                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                        </svg>
                                    </div>
                                    <p class="mt-4 text-sm font-medium text-gray-700">No SP members assigned yet.</p>
                                    <p class="mt-1 text-xs text-gray-500">Use the form on the left to add SP members.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($legislators as $leg):
                                $full = formatName($leg);
                                $legId = (int)($leg['id'] ?? 0);
                                $position = htmlspecialchars($leg['position'] ?? 'SP Member');
                                $district = htmlspecialchars($leg['district_name'] ?? '');
                            ?>
                            <tr class="hover:bg-gray-50/50 transition" data-row-id="<?= $legId ?>">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($leg['photo_path'])): ?>
                                            <img src="<?= htmlspecialchars($leg['photo_path']) ?>"
                                                 alt="" class="h-10 w-10 rounded-full object-cover border border-gray-200">
                                        <?php else: ?>
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-primary">
                                                <?= strtoupper(mb_substr($leg['first_name'] ?? '', 0, 1) . mb_substr($leg['last_name'] ?? '', 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <div class="truncate font-medium text-gray-900"><?= htmlspecialchars($full) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                        <?= $position ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-600">
                                    <?= $district ?: '—' ?>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    <?= !empty($leg['date_assigned']) ? date('M j, Y', strtotime($leg['date_assigned'])) : '—' ?>
                                    <?php if (!empty($leg['notes'])): ?>
                                        <div title="<?= htmlspecialchars($leg['notes']) ?>" class="truncate max-w-[140px] text-[10px] text-gray-400 mt-0.5">
                                            <?= htmlspecialchars($leg['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button type="button" onclick="removeMember(<?= $legId ?>, '<?= htmlspecialchars($full, ENT_QUOTES) ?>')"
                                        class="rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:border-red-400 hover:bg-red-50 hover:text-red-600 transition"
                                        title="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </section>

</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="fixed inset-0 z-[100] hidden items-center justify-center" role="dialog" aria-modal="true">
    <div id="confirmModalBackdrop" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity opacity-0"></div>
    <div id="confirmModalPanel" class="relative z-10 w-full max-w-sm mx-4 bg-white rounded-2xl shadow-2xl border border-gray-100 transform scale-95 opacity-0 transition-all duration-200">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div id="confirmIconBox" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg id="confirmIcon" class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 id="confirmTitle" class="text-lg font-semibold text-gray-900">Confirm Action</h3>
                    <p id="confirmMessage" class="mt-1 text-sm text-gray-600">Are you sure?</p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
            <button type="button" onclick="closeConfirmModal()" id="confirmCancelBtn"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" onclick="executeConfirmAction()" id="confirmProceedBtn"
                class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition shadow-sm">
                Confirm
            </button>
        </div>
    </div>
</div>

<div id="toastContainer" class="fixed top-4 right-4 z-[200] space-y-2 pointer-events-none"></div>

<script>
const BASE = '<?= BASE_URL ?>';
const TERM_ID = <?= (int)($term['id'] ?? 0) ?>;

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const colors = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        error: 'border-red-200 bg-red-50 text-red-800',
        info: 'border-blue-200 bg-blue-50 text-blue-800'
    };
    const icons = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
        error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
        info: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
    };
    const iconColors = {
        success: 'bg-emerald-100 text-emerald-600',
        error: 'bg-red-100 text-red-600',
        info: 'bg-blue-100 text-blue-600'
    };
    toast.className = `pointer-events-auto max-w-sm rounded-xl border px-4 py-3 flex items-center gap-3 shadow-lg transform transition-all duration-300 translate-x-full ${colors[type]}`;
    toast.innerHTML = `
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${iconColors[type]}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">${icons[type]}</svg>
        </div>
        <p class="text-sm font-medium flex-1">${message}</p>
        <button type="button" onclick="this.parentElement.remove()" class="text-current opacity-60 hover:opacity-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.remove('translate-x-full'));
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}

// Member search functionality
const memberSearch = document.getElementById('memberSearch');
const clearSearchBtn = document.getElementById('clearSearch');
const memberItems = document.querySelectorAll('.member-item');
const noResultsState = document.getElementById('noResultsState');
const visibleCountSpan = document.getElementById('visibleCount');

if (memberSearch && memberItems.length > 0) {
    memberSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        let visibleCount = 0;
        
        if (searchTerm === '') {
            clearSearchBtn.classList.add('hidden');
        } else {
            clearSearchBtn.classList.remove('hidden');
        }
        
        memberItems.forEach(item => {
            const searchData = item.getAttribute('data-search') || '';
            const checkbox = item.querySelector('.member-checkbox');
            const isChecked = checkbox && checkbox.checked;
            
            if (searchTerm === '' || searchData.includes(searchTerm) || isChecked) {
                item.classList.remove('hidden');
                visibleCount++;
            } else {
                item.classList.add('hidden');
            }
        });
        
        if (visibleCountSpan) {
            visibleCountSpan.textContent = visibleCount;
        }
        
        if (noResultsState) {
            if (visibleCount === 0) {
                noResultsState.classList.remove('hidden');
            } else {
                noResultsState.classList.add('hidden');
            }
        }
        
        updateSelectAllState();
    });
    
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            memberSearch.value = '';
            memberSearch.dispatchEvent(new Event('input'));
            memberSearch.focus();
        });
    }
}

// Select all functionality
const selectAll = document.getElementById('selectAllMembers');
const memberCheckboxes = document.querySelectorAll('.member-checkbox');

function updateSelectAllState() {
    const visibleCheckboxes = Array.from(memberCheckboxes).filter(cb => {
        const item = cb.closest('.member-item');
        return item && !item.classList.contains('hidden');
    });
    
    if (visibleCheckboxes.length === 0) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
        return;
    }
    
    const allChecked = visibleCheckboxes.every(c => c.checked);
    const anyChecked = visibleCheckboxes.some(c => c.checked);
    selectAll.checked = allChecked;
    selectAll.indeterminate = !allChecked && anyChecked;
}

if (selectAll && memberCheckboxes.length > 0) {
    selectAll.addEventListener('change', () => {
        const checked = selectAll.checked;
        memberCheckboxes.forEach(cb => {
            const item = cb.closest('.member-item');
            if (item && !item.classList.contains('hidden')) {
                cb.checked = checked;
            }
        });
    });
    
    memberCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectAllState);
    });
    
    updateSelectAllState();
}

// Modal functionality
let confirmModalOpen = false;
let pendingAction = null;

function openConfirmModal({ title, message, confirmText = 'Confirm', cancelText = 'Cancel', type = 'danger', onConfirm }) {
    if (confirmModalOpen) return;
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').innerHTML = message;
    const proceedBtn = document.getElementById('confirmProceedBtn');
    const iconBox = document.getElementById('confirmIconBox');
    const icon = document.getElementById('confirmIcon');
    if (type === 'danger') {
        proceedBtn.className = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition shadow-sm';
        iconBox.className = 'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100';
        icon.className = 'h-6 w-6 text-red-600';
    } else {
        proceedBtn.className = 'inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-blue-700 transition shadow-sm';
        iconBox.className = 'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-100';
        icon.className = 'h-6 w-6 text-blue-600';
    }
    proceedBtn.textContent = confirmText;
    document.getElementById('confirmCancelBtn').textContent = cancelText;
    pendingAction = onConfirm;
    confirmModalOpen = true;
    const m = document.getElementById('confirmModal');
    const b = document.getElementById('confirmModalBackdrop');
    const p = document.getElementById('confirmModalPanel');
    m.classList.remove('hidden');
    m.classList.add('flex');
    requestAnimationFrame(() => {
        b.classList.remove('opacity-0');
        p.classList.remove('scale-95', 'opacity-0');
        p.classList.add('scale-100', 'opacity-100');
    });
}

function closeConfirmModal() {
    if (!confirmModalOpen) return;
    confirmModalOpen = false;
    pendingAction = null;
    const m = document.getElementById('confirmModal');
    const b = document.getElementById('confirmModalBackdrop');
    const p = document.getElementById('confirmModalPanel');
    b.classList.add('opacity-0');
    p.classList.remove('scale-100', 'opacity-100');
    p.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        m.classList.remove('flex');
        m.classList.add('hidden');
    }, 200);
}

function executeConfirmAction() {
    const fn = pendingAction;
    closeConfirmModal();
    if (typeof fn === 'function') fn();
}

document.getElementById('confirmModalBackdrop')?.addEventListener('click', closeConfirmModal);
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && confirmModalOpen) closeConfirmModal();
});

async function removeMember(id, name) {
    openConfirmModal({
        title: 'Remove SP Member',
        message: `Remove <strong class="text-gray-800">${name}</strong> from this term?<br><span class="text-xs text-gray-500">This only removes their assignment for the current term.</span>`,
        confirmText: 'Remove',
        type: 'danger',
        onConfirm: async () => {
            try {
                const fd = new FormData();
                fd.append('id', id);
                fd.append('term_id', TERM_ID);
                const res = await fetch(`${BASE}/master/legislative-terms/remove-legislator`, { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 700);
            } catch (e) { showToast('Network error.', 'error'); }
        }
    });
}

<?php if ($success): ?>
showToast(<?= json_encode((string)$success) ?>, 'success');
<?php endif; ?>
<?php if ($error): ?>
showToast(<?= json_encode((string)$error) ?>, 'error');
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
