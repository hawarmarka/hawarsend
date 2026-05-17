<nav class="navbar">
    <div class="navbar-inner">
        <a href="/" class="navbar-brand">
            <?php $logo = Settings::get('logo', ''); ?>
            <?php if ($logo): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($siteName) ?>" class="brand-icon" style="object-fit:contain;border-radius:10px;">
            <?php else: ?>
                <svg viewBox="0 0 32 32" fill="none" class="brand-icon">
                    <path d="M9 22.5a5 5 0 0 1 1.8-9.66A7 7 0 0 1 24.4 14.6 4.4 4.4 0 0 1 23 23H9Z" stroke="url(#g1)" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16 10v9m0 0-3-3m3 3 3-3" stroke="url(#g1)" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"/>
                    <defs>
                        <linearGradient id="g1" x1="8" y1="7" x2="26" y2="24" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#3DA9FF"/>
                            <stop offset="1" stop-color="#76B8FF"/>
                        </linearGradient>
                    </defs>
                </svg>
            <?php endif; ?>
            <span class="brand-name"><?= e($siteName) ?></span>
        </a>

        <div class="navbar-links">
            <?php if ($isLoggedIn): ?>
                <a href="/dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">Panelim</a>
                <a href="/logout.php" class="nav-link">Çıkış</a>
                <span class="nav-user"><?= e($currentUser['username'] ?? '') ?></span>
            <?php else: ?>
                <a href="/login.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'login.php' ? 'active' : '' ?>">Giriş Yap</a>
                <a href="/register.php" class="nav-btn">Üye Ol</a>
            <?php endif; ?>
        </div>

        <button class="navbar-toggle" id="navToggle" aria-label="Menü">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>
