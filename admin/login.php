<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';

if (Auth::adminCheck()) redirect('/admin/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $error = 'Güvenlik hatası.';
    } elseif (Security::isAdminLoginBlocked(Security::getIp())) {
        $error = 'Çok fazla başarısız deneme. 30 dakika bekleyin.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (Auth::adminLogin($email, $password)) {
            redirect('/admin/dashboard.php');
        } else {
            $error = 'E-posta veya şifre hatalı.';
        }
    }
}

Settings::load();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Giriş — <?= e(Settings::get('site_name', APP_NAME)) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-body" style="display:flex;align-items:center;justify-content:center;min-height:100vh;">
<div style="width:100%;max-width:380px;padding:24px;">
    <div style="text-align:center;margin-bottom:28px;">
        <div style="font-size:2.5rem;margin-bottom:8px;">🛡️</div>
        <h1 style="font-size:1.3rem;font-weight:700;background:linear-gradient(135deg,#4F9FFF,#7B5FFF);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Admin Paneli</h1>
        <p style="font-size:.85rem;color:rgba(255,255,255,.4);margin-top:4px;"><?= e(Settings::get('site_name', APP_NAME)) ?></p>
    </div>

    <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:24px;">
        <?php if ($error): ?>
        <div class="admin-alert admin-alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
            <div class="admin-form-group" style="margin-bottom:14px;">
                <label class="admin-label">E-posta</label>
                <input type="email" name="email" class="admin-input" style="width:100%;" value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@hawarsend.com" required autofocus>
            </div>
            <div class="admin-form-group" style="margin-bottom:20px;">
                <label class="admin-label">Şifre</label>
                <input type="password" name="password" class="admin-input" style="width:100%;" placeholder="••••••••" required>
            </div>
            <button type="submit" class="admin-btn admin-btn-primary" style="width:100%;justify-content:center;padding:11px;">Giriş Yap</button>
        </form>
    </div>
    <p style="text-align:center;margin-top:16px;font-size:.82rem;"><a href="/" style="color:rgba(255,255,255,.4);">← Ana Sayfaya Dön</a></p>
</div>
<script src="/assets/js/app.js"></script>
</body>
</html>
