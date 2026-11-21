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

if (window.location.pathname.split('/').includes('master')) {
    window.showToast = function (message, type = 'success') {
        let container = document.getElementById('statusToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'statusToastContainer';
            container.className = 'fixed top-24 right-6 z-50 space-y-2 pointer-events-none';
            document.body.appendChild(container);
        }

        const isError = type === 'error';
        const toast = document.createElement('div');
        toast.className = `pointer-events-auto flex max-w-sm items-center gap-3 rounded-xl border ${isError ? 'border-red-100 bg-red-50' : 'border-emerald-100 bg-emerald-50'} px-4 py-3 shadow-lg ${isError ? '' : 'animate-slide-in-right'}`;
        toast.setAttribute('role', isError ? 'alert' : 'status');

        const iconBox = document.createElement('div');
        iconBox.className = `flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${isError ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600'}`;
        iconBox.innerHTML = `<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="${isError ? 'M6 18L18 6M6 6l12 12' : 'M5 13l4 4L19 7'}"/></svg>`;

        const content = document.createElement('div');
        content.className = 'min-w-0 flex-1';

        const title = document.createElement('p');
        title.className = `text-sm font-semibold ${isError ? 'text-red-800' : 'text-emerald-800'}`;
        title.textContent = isError ? 'Error' : 'Success';

        const text = document.createElement('p');
        text.className = `text-xs break-words ${isError ? 'text-red-700' : 'text-emerald-700'}`;
        text.textContent = String(message ?? '');

        content.append(title, text);
        toast.append(iconBox, content);
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.transition = 'opacity 300ms, transform 300ms';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
        }, 4500);
        setTimeout(() => toast.remove(), 5000);
    };

    window.showStatusToast = window.showToast;
}