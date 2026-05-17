<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';

if (Auth::check()) redirect('/dashboard.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $error = 'Güvenlik doğrulaması başarısız.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (empty($email) || empty($password)) {
            $error = 'E-posta ve şifre gerekli.';
        } elseif (Security::isLoginBlocked(Security::getIp(), $email)) {
            $error = 'Çok fazla başarısız deneme. 15 dakika bekleyin.';
        } elseif (Auth::login($email, $password, $remember)) {
            $redirect = $_GET['redirect'] ?? '/dashboard.php';
            redirect($redirect);
        } else {
            $error = 'E-posta veya şifre hatalı.';
        }
    }
}

Settings::load();
$pageTitle = 'Giriş Yap';
require_once dirname(__DIR__) . '/app/includes/header.php';
require_once dirname(__DIR__) . '/app/includes/navbar.php';
?>
<main>
<div style="max-width:420px;margin:80px auto;padding:0 24px;">
    <div class="card fade-in">
        <div class="card-header" style="text-align:center;">
            <h1 style="font-size:1.4rem;font-weight:700;">Giriş Yap</h1>
            <p class="text-muted" style="font-size:.9rem;margin-top:4px;">Hesabınıza erişin</p>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
            <div class="form-group">
                <label class="form-label">E-posta</label>
                <input type="email" name="email" class="form-input" value="<?= e($_POST['email'] ?? '') ?>" placeholder="ornek@mail.com" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" style="display:flex;justify-content:space-between;">
                    Şifre
                    <a href="/forgot-password.php" style="font-size:.8rem;">Şifremi unuttum</a>
                </label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                <input type="checkbox" name="remember" id="remember" style="accent-color:var(--accent-blue);">
                <label for="remember" style="font-size:.85rem;color:var(--text-secondary);">Beni hatırla</label>
            </div>
            <button type="submit" class="btn btn-primary btn-full btn-lg">Giriş Yap</button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:.88rem;color:var(--text-secondary);">
            Hesabınız yok mu? <a href="/register.php">Üye Ol</a>
        </p>
    </div>
</div>
</main>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
