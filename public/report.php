<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Upload.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';

$token = preg_replace('/[^A-Za-z0-9]/', '', $_GET['token'] ?? '');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $error = 'Güvenlik hatası.';
    } elseif (Security::isRateLimited(Security::getIp(), 'report', 5, 60)) {
        $error = 'Çok fazla şikayet gönderdiniz.';
    } else {
        $reportToken  = preg_replace('/[^A-Za-z0-9]/', '', $_POST['token'] ?? '');
        $reason = Security::sanitize($_POST['reason'] ?? '');
        $details = Security::sanitize($_POST['details'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '';

        if (empty($reason)) {
            $error = 'Şikayet sebebi gerekli.';
        } else {
            $upload = Upload::getUpload($reportToken);
            if ($upload) {
                Database::insert(
                    'INSERT INTO reports (upload_token, reason, details, reporter_email, reporter_ip, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                    [$reportToken, $reason, $details, $email, Security::getIp()]
                );
                Security::recordAction(Security::getIp(), 'report', $reportToken);
                $success = 'Şikayetiniz alındı. Teşekkür ederiz.';
            } else {
                $error = 'Geçersiz dosya token.';
            }
        }
    }
}

Settings::load();
$pageTitle = 'Şikayet Et';
require_once dirname(__DIR__) . '/app/includes/header.php';
require_once dirname(__DIR__) . '/app/includes/navbar.php';
?>
<main>
<div style="max-width:480px;margin:60px auto;padding:0 24px;">
    <div class="card fade-in">
        <div class="card-header" style="text-align:center;">
            <div style="font-size:2.5rem;margin-bottom:8px;">🚩</div>
            <h1 style="font-size:1.3rem;font-weight:700;">İçeriği Şikayet Et</h1>
            <p class="text-muted" style="font-size:.88rem;margin-top:4px;">Uygunsuz içerikleri bildirin.</p>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
            <div style="text-align:center;margin-top:16px;"><a href="/" class="btn btn-primary">Ana Sayfaya Dön</a></div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="form-group">
                <label class="form-label">Şikayet Sebebi *</label>
                <select name="reason" class="form-input" required>
                    <option value="">Seçin...</option>
                    <option value="illegal">Yasadışı içerik</option>
                    <option value="malware">Virüs/Zararlı yazılım</option>
                    <option value="copyright">Telif hakkı ihlali</option>
                    <option value="spam">Spam</option>
                    <option value="other">Diğer</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Detaylar</label>
                <textarea name="details" class="form-input" rows="3" placeholder="Ek açıklama..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">E-posta (opsiyonel)</label>
                <input type="email" name="email" class="form-input" placeholder="Geri dönüş için">
            </div>
            <button type="submit" class="btn btn-danger btn-full">Şikayet Gönder</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</main>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
