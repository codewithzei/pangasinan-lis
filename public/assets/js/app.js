const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');

function isMobileView() {
    return window.innerWidth < 1024;
}

function openSidebar() {
    if (!sidebar || !isMobileView()) return;
    sidebar.classList.remove('-translate-x-full');
    if (sidebarBackdrop) {
        sidebarBackdrop.classList.remove('invisible');
        sidebarBackdrop.classList.remove('opacity-0');
    }
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', handleSidebarEsc);
}

function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('-translate-x-full');
    if (sidebarBackdrop) {
        sidebarBackdrop.classList.add('opacity-0');
        setTimeout(() => {
            if (sidebarBackdrop) sidebarBackdrop.classList.add('invisible');
        }, 300);
    }
    document.body.style.overflow = '';
    document.removeEventListener('keydown', handleSidebarEsc);
}

function toggleSidebar() {
    if (!sidebar) return;
    const isClosed = sidebar.classList.contains('-translate-x-full');
    if (isClosed) {
        openSidebar();
    } else {
        closeSidebar();
    }
}

function handleSidebarEsc(e) {
    if (e.key === 'Escape') closeSidebar();
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
}

if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeSidebar);
}

window.addEventListener('resize', () => {
    if (!isMobileView() && sidebar && !sidebar.classList.contains('-translate-x-full')) {
        closeSidebar();
    }
});

const logoutBtn = document.getElementById('logoutBtn');
const logoutModal = document.getElementById('logoutModal');
const logoutModalBackdrop = document.getElementById('logoutModalBackdrop');
const logoutModalPanel = document.getElementById('logoutModalPanel');
const logoutCancelBtn = document.getElementById('logoutCancelBtn');
const logoutConfirmBtn = document.getElementById('logoutConfirmBtn');
const logoutForm = document.getElementById('logoutForm');

let modalOpen = false;

function resetLogoutModal() {
    logoutCancelBtn.disabled = false;
    logoutConfirmBtn.disabled = false;
    logoutConfirmBtn.innerHTML = `
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
        Sign out
    `;
}

function openLogoutModal() {
    if (!logoutModal || modalOpen) return;
    resetLogoutModal();
    modalOpen = true;
    logoutModal.classList.remove('hidden');
    logoutModal.classList.add('flex');
    requestAnimationFrame(() => {
        logoutModalBackdrop.classList.remove('opacity-0');
        logoutModalPanel.classList.remove('scale-95', 'opacity-0');
        logoutModalPanel.classList.add('scale-100', 'opacity-100');
    });
    document.addEventListener('keydown', handleLogoutEsc);
}

function closeLogoutModal() {
    if (!logoutModal || !modalOpen) return;
    modalOpen = false;
    logoutModalBackdrop.classList.add('opacity-0');
    logoutModalPanel.classList.remove('scale-100', 'opacity-100');
    logoutModalPanel.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        logoutModal.classList.remove('flex');
        logoutModal.classList.add('hidden');
    }, 200);
    document.removeEventListener('keydown', handleLogoutEsc);
}

function handleLogoutEsc(e) {
    if (e.key === 'Escape') closeLogoutModal();
}

if (logoutBtn) {
    logoutBtn.addEventListener('click', openLogoutModal);
}

if (logoutCancelBtn) {
    logoutCancelBtn.addEventListener('click', closeLogoutModal);
}

if (logoutModalBackdrop) {
    logoutModalBackdrop.addEventListener('click', closeLogoutModal);
}

if (logoutConfirmBtn && logoutForm) {
    logoutConfirmBtn.addEventListener('click', () => {
        logoutConfirmBtn.disabled = true;
        logoutCancelBtn.disabled = true;
        logoutConfirmBtn.innerHTML = `
            <svg class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Signing out...
        `;
        logoutForm.submit();
    });
}