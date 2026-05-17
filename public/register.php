<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';

if (Auth::check()) redirect('/dashboard.php');

Settings::load();
$registerEnabled = Settings::get('register_enabled', '1') === '1';
if (!$registerEnabled) {
    $pageTitle = 'Kayıt Kapalı';
    require_once dirname(__DIR__) . '/app/includes/header.php';
    require_once dirname(__DIR__) . '/app/includes/navbar.php';
    echo '<div class="error-page"><div class="error-code" style="font-size:3rem;">🔒</div><h1 class="error-title">Kayıt Kapalı</h1><p class="error-desc">Şu anda yeni üye kaydı alınmıyor.</p><a href="/" class="btn btn-primary">Ana Sayfa</a></div>';
    require_once dirname(__DIR__) . '/app/includes/footer.php';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } elseif (Security::isRateLimited(Security::getIp(), 'register', 3, 60)) {
        $error = 'Çok fazla kayıt denemesi. Lütfen bekleyin.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if ($password !== $confirm) {
            $error = 'Şifreler eşleşmiyor.';
        } else {
            $result = Auth::register($username, $email, $password);
            if ($result['success']) {
                Security::recordAction(Security::getIp(), 'register', $email);
                $success = 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.';
            } else {
                $error = $result['message'];
            }
        }
    }
}

$pageTitle = 'Üye Ol';
require_once dirname(__DIR__) . '/app/includes/header.php';
require_once dirname(__DIR__) . '/app/includes/navbar.php';
?>
<main>
<div style="max-width:420px;margin:80px auto;padding:0 24px;">
    <div class="card fade-in">
        <div class="card-header" style="text-align:center;">
            <h1 style="font-size:1.4rem;font-weight:700;">Üye Ol</h1>
            <p class="text-muted" style="font-size:.9rem;margin-top:4px;">Yeni hesap oluşturun</p>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?> <a href="/login.php">Giriş Yap</a></div><?php endif; ?>
        <?php if (!$success): ?>
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
            <div class="form-group">
                <label class="form-label">Kullanıcı Adı</label>
                <input type="text" name="username" class="form-input" value="<?= e($_POST['username'] ?? '') ?>" placeholder="kullaniciadi" required minlength="3" maxlength="30" autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">E-posta</label>
                <input type="email" name="email" class="form-input" value="<?= e($_POST['email'] ?? '') ?>" placeholder="ornek@mail.com" required>
            </div>
            <div class="form-group">
                <label class="form-label">Şifre</label>
                <input type="password" name="password" class="form-input" placeholder="En az 6 karakter" required minlength="6">
            </div>
            <div class="form-group">
                <label class="form-label">Şifre Tekrar</label>
                <input type="password" name="password_confirm" class="form-input" placeholder="Şifrenizi tekrarlayın" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full btn-lg">Üye Ol</button>
        </form>
        <?php endif; ?>
        <p style="text-align:center;margin-top:16px;font-size:.88rem;color:var(--text-secondary);">
            Zaten hesabınız var mı? <a href="/login.php">Giriş Yap</a>
        </p>
    </div>
</div>
</main>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
