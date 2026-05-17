<?php
// Admin layout header — include at top of each admin page
// Expects: $pageTitle, $activeMenu
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';
require_once dirname(__DIR__) . '/app/core/Upload.php';
Auth::requireAdmin();
Settings::load();

$siteName    = Settings::get('site_name', APP_NAME);
$activeMenu  = $activeMenu ?? '';
$adminName   = $_SESSION['admin_name'] ?? 'Admin';
$openReports = Database::fetch('SELECT COUNT(*) as cnt FROM reports WHERE status = "open"')['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin') ?> — <?= e($siteName) ?> Admin</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-body">
<div class="admin-layout">

<!-- Sidebar -->
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <span class="sidebar-brand"><?= e($siteName) ?></span>
        <span class="sidebar-badge">Admin</span>
    </div>
    <nav class="sidebar-nav">
        <span class="sidebar-section-title">Genel</span>
        <a href="/admin/dashboard.php" class="sidebar-link <?= $activeMenu==='dashboard'?'active':'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
            Dashboard
        </a>

        <span class="sidebar-section-title">Yönetim</span>
        <a href="/admin/files.php" class="sidebar-link <?= $activeMenu==='files'?'active':'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
            Dosyalar
        </a>
        <a href="/admin/users.php" class="sidebar-link <?= $activeMenu==='users'?'active':'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
            Kullanıcılar
        </a>
        <a href="/admin/reports.php" class="sidebar-link <?= $activeMenu==='reports'?'active':'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            Şikayetler
            <?php if ($openReports > 0): ?><span class="badge-count"><?= $openReports ?></span><?php endif; ?>
        </a>

        <span class="sidebar-section-title">Site</span>
        <a href="/admin/settings.php" class="sidebar-link <?= $activeMenu==='settings'?'active':'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
            Site Ayarları
        </a>
        <a href="/admin/ads.php" class="sidebar-link <?= $activeMenu==='ads'?'active':'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
            Reklam Kodları
        </a>
        <a href="/admin/security.php" class="sidebar-link <?= $activeMenu==='security'?'active':'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            Güvenlik
        </a>
        <a href="/admin/cleanup.php" class="sidebar-link <?= $activeMenu==='cleanup'?'active':'' ?>">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            Temizlik
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="/admin/logout.php">
            <svg viewBox="0 0 20 20" fill="currentColor" style="width:16px;height:16px;"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
            Çıkış Yap
        </a>
    </div>
</aside>

<div class="admin-main">
    <header class="admin-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="admin-sidebar-toggle" aria-label="Menü">
                <svg viewBox="0 0 20 20" fill="currentColor" style="width:20px;height:20px;"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
            </button>
            <span class="admin-page-title"><?= e($pageTitle ?? 'Admin') ?></span>
        </div>
        <div class="admin-header-actions">
            <a href="/" target="_blank" class="admin-btn admin-btn-secondary admin-btn-sm">Siteyi Gör</a>
            <div class="admin-avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
        </div>
    </header>
    <div class="admin-content">
