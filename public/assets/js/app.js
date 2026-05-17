(function () {
    'use strict';

    const navToggle = document.getElementById('navToggle');
    const navLinks = document.querySelector('.navbar-links');
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', () => navLinks.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (!navToggle.contains(e.target) && !navLinks.contains(e.target)) {
                navLinks.classList.remove('open');
            }
        });
    }

    window.copyText = function (text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            if (!btn) return;
            const orig = btn.innerHTML;
            btn.innerHTML = '✓ Kopyalandı';
            btn.classList.add('btn-success');
            setTimeout(() => {
                btn.innerHTML = orig;
                btn.classList.remove('btn-success');
            }, 2000);
        }).catch(() => {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        });
    };

    document.querySelectorAll('.alert[data-autohide]').forEach(el => {
        setTimeout(() => el.style.opacity = '0', 4000);
        setTimeout(() => el.remove(), 4500);
    });

    const sidebarToggle = document.querySelector('.admin-sidebar-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    if (sidebarToggle && adminSidebar) {
        sidebarToggle.addEventListener('click', () => adminSidebar.classList.toggle('open'));
    }

    const particleContainer = document.getElementById('siteParticles');
    if (particleContainer) {
        const isMobile = window.innerWidth < 768;
        const count = isMobile ? 14 : 28;
        for (let i = 0; i < count; i++) {
            const span = document.createElement('span');
            const size = Math.random() * 3 + 1;
            span.className = 'bg-particle';
            span.style.width = size + 'px';
            span.style.height = size + 'px';
            span.style.left = Math.random() * 100 + '%';
            span.style.top = Math.random() * 100 + '%';
            span.style.animationDelay = (Math.random() * 8) + 's';
            span.style.animationDuration = (10 + Math.random() * 14) + 's';
            particleContainer.appendChild(span);
        }
    }
})();
