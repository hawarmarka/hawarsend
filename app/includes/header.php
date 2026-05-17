<?php
require_once dirname(__DIR__, 2) . '/app/config/config.php';
require_once dirname(__DIR__, 2) . '/app/core/Database.php';
require_once dirname(__DIR__, 2) . '/app/core/Settings.php';
require_once dirname(__DIR__, 2) . '/app/core/Security.php';
require_once dirname(__DIR__, 2) . '/app/core/Auth.php';
require_once dirname(__DIR__, 2) . '/app/core/Upload.php';
require_once dirname(__DIR__, 2) . '/app/core/Helpers.php';

Settings::load();
$siteName = Settings::get('site_name', APP_NAME ?: 'Send');
$customCss = Settings::get('custom_css', '');
$customJs  = Settings::get('custom_js', '');
$headerCode = Settings::get('header_code', '');
$maintenanceMode = Settings::get('maintenance_mode', '0');
$analyticsCode = Settings::get('analytics_code', '');
$favicon = Settings::get('favicon', '') ?: '/assets/images/favicon.svg';

// Maintenance mode
if ($maintenanceMode === '1' && !Auth::adminCheck()) {
    http_response_code(503);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Bakım Modu</title><style>body{background:#0a0a0f;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;text-align:center}h1{font-size:2rem;margin-bottom:1rem}p{color:#aaa}</style></head><body><div><h1>🔧 Bakım Modu</h1><p>Site şu anda bakımda. Lütfen daha sonra tekrar deneyin.</p></div></body></html>';
    exit;
}

$pageTitle = $pageTitle ?? $siteName;
$isLoggedIn = Auth::check();
$currentUser = $isLoggedIn ? Auth::user() : null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e($siteName) ?></title>
    <meta name="description" content="<?= e(Settings::get('meta_description', 'Send ile dosyalarınızı modern ve güvenli şekilde paylaşın.')) ?>">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="icon" href="<?= e($favicon) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <?php if ($analyticsCode): ?>
    <?= $analyticsCode ?>
    <?php endif; ?>
    <?php if ($customCss): ?><style><?= $customCss ?></style><?php endif; ?>
    <?php echo $headerCode; ?>
</head>
<body>
<div class="site-bg" aria-hidden="true">
    <div class="site-bg-grid"></div>
    <div class="site-bg-glow site-bg-glow-1"></div>
    <div class="site-bg-glow site-bg-glow-2"></div>
    <div class="site-bg-particles" id="siteParticles"></div>
</div>
<div class="page-wrapper">
