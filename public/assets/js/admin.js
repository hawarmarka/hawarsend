// === HawarSend — admin.js ===
(function () {
    'use strict';

    // Sidebar mobile toggle
    const sidebarToggle = document.querySelector('.admin-sidebar-toggle');
    const adminSidebar  = document.querySelector('.admin-sidebar');
    if (sidebarToggle && adminSidebar) {
        sidebarToggle.addEventListener('click', () => adminSidebar.classList.toggle('open'));
        document.addEventListener('click', e => {
            if (!sidebarToggle.contains(e.target) && !adminSidebar.contains(e.target)) {
                adminSidebar.classList.remove('open');
            }
        });
    }

    // Confirm delete dialogs
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm || 'Emin misiniz?')) e.preventDefault();
        });
    });

    // Auto-dismiss admin alerts
    document.querySelectorAll('.admin-alert').forEach(el => {
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity 0.4s'; }, 4000);
        setTimeout(() => el.remove(), 4500);
    });

    // Tab system
    const tabBtns = document.querySelectorAll('[data-tab-btn]');
    const tabPanes = document.querySelectorAll('[data-tab-pane]');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.style.display = 'none');
            btn.classList.add('active');
            const target = document.querySelector('[data-tab-pane="' + btn.dataset.tabBtn + '"]');
            if (target) target.style.display = 'block';
        });
    });
    if (tabPanes.length) {
        tabPanes.forEach((p, i) => p.style.display = i === 0 ? 'block' : 'none');
        if (tabBtns.length) tabBtns[0].classList.add('active');
    }

    // Copy to clipboard
    window.copyText = function(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            if (!btn) return;
            const orig = btn.textContent;
            btn.textContent = 'Kopyalandı!';
            setTimeout(() => btn.textContent = orig, 2000);
        });
    };

    // Cleanup form with loading state
    const cleanupBtn = document.getElementById('cleanupBtn');
    if (cleanupBtn) {
        cleanupBtn.addEventListener('click', function() {
            this.disabled = true;
            this.textContent = 'Temizleniyor...';
        });
    }
})();
