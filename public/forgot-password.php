<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';
require_once dirname(__DIR__) . '/app/core/Mailer.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $error = 'Güvenlik hatası.';
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $error = 'Geçerli bir e-posta adresi girin.';
        } else {
            $user = Database::fetch('SELECT id FROM users WHERE email = ? AND status = "active" LIMIT 1', [$email]);
            // Always show success to prevent email enumeration
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                Database::execute('DELETE FROM password_resets WHERE email = ?', [$email]);
                Database::insert('INSERT INTO password_resets (email, token, expires_at, created_at) VALUES (?, ?, ?, NOW())', [$email, $token, $expires]);
                $resetUrl = APP_URL . '/reset-password.php?token=' . $token;
                $subject = 'HawarSend Şifre Sıfırlama';
                $html = '<p>Merhaba,</p><p>Şifrenizi sıfırlamak için aşağıdaki bağlantıya tıklayın:</p><p><a href="' . e($resetUrl) . '">' . e($resetUrl) . '</a></p><p>Bu bağlantı 1 saat geçerlidir.</p>';
                Mailer::send($email, $subject, $html, "Şifre sıfırlama bağlantınız: $resetUrl");
            }
            $success = 'Eğer bu e-posta kayıtlıysa, şifre sıfırlama bağlantısı gönderildi.';
        }
    }
}

Settings::load();
$pageTitle = 'Şifremi Unuttum';
require_once dirname(__DIR__) . '/app/includes/header.php';
require_once dirname(__DIR__) . '/app/includes/navbar.php';
?>
<main>
<div style="max-width:400px;margin:80px auto;padding:0 24px;">
    <div class="card fade-in">
        <div class="card-header" style="text-align:center;">
            <h1 style="font-size:1.3rem;font-weight:700;">Şifremi Unuttum</h1>
            <p class="text-muted" style="font-size:.88rem;margin-top:4px;">E-postanıza sıfırlama bağlantısı gönderilecek.</p>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if (!$success): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
            <div class="form-group">
                <label class="form-label">E-posta Adresi</label>
                <input type="email" name="email" class="form-input" placeholder="ornek@mail.com" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Sıfırlama Bağlantısı Gönder</button>
        </form>
        <?php endif; ?>
        <p style="text-align:center;margin-top:16px;font-size:.88rem;color:var(--text-secondary);">
            <a href="/login.php">← Giriş Yap</a>
        </p>
    </div>
</div>
</main>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
