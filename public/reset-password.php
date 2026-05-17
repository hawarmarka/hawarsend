<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Validate token
$reset = $token ? Database::fetch(
    'SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1',
    [$token]
) : null;

if (!$reset && $token) {
    $error = 'Bu bağlantı geçersiz veya süresi dolmuş.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    if (!Security::verifyCsrf()) {
        $error = 'Güvenlik hatası.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        if (strlen($password) < 6) {
            $error = 'Şifre en az 6 karakter olmalıdır.';
        } elseif ($password !== $confirm) {
            $error = 'Şifreler eşleşmiyor.';
        } else {
            Database::execute(
                'UPDATE users SET password = ? WHERE email = ?',
                [password_hash($password, PASSWORD_DEFAULT), $reset['email']]
            );
            Database::execute('DELETE FROM password_resets WHERE email = ?', [$reset['email']]);
            $success = 'Şifreniz başarıyla güncellendi.';
        }
    }
}

Settings::load();
$pageTitle = 'Şifre Sıfırla';
require_once dirname(__DIR__) . '/app/includes/header.php';
require_once dirname(__DIR__) . '/app/includes/navbar.php';
?>
<main>
<div style="max-width:400px;margin:80px auto;padding:0 24px;">
    <div class="card fade-in">
        <div class="card-header" style="text-align:center;">
            <h1 style="font-size:1.3rem;font-weight:700;">Yeni Şifre Belirle</h1>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
            <div style="text-align:center;margin-top:12px;"><a href="/login.php" class="btn btn-primary">Giriş Yap</a></div>
        <?php elseif ($reset): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
            <div class="form-group">
                <label class="form-label">Yeni Şifre</label>
                <input type="password" name="password" class="form-input" placeholder="En az 6 karakter" required minlength="6" autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Şifre Tekrar</label>
                <input type="password" name="password_confirm" class="form-input" placeholder="Şifrenizi tekrarlayın" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Şifreyi Güncelle</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</main>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
