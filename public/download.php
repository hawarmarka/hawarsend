<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Upload.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';

// Get token from URL: /d/TOKEN or ?token=TOKEN
$token = $_GET['token'] ?? basename($_SERVER['REQUEST_URI']);
$token = preg_replace('/[^A-Za-z0-9]/', '', $token);

if (!$token) {
    http_response_code(404);
    require_once dirname(__DIR__) . '/app/includes/header.php';
    require_once dirname(__DIR__) . '/app/includes/navbar.php';
    echo '<div class="error-page"><div class="error-code">404</div><h1 class="error-title">Sayfa Bulunamadı</h1><p class="error-desc">Bu link geçerli değil.</p><a href="/" class="btn btn-primary">Ana Sayfa</a></div>';
    require_once dirname(__DIR__) . '/app/includes/footer.php';
    exit;
}

$upload = Upload::getUpload($token);

if (!$upload) {
    http_response_code(404);
    require_once dirname(__DIR__) . '/app/includes/header.php';
    require_once dirname(__DIR__) . '/app/includes/navbar.php';
    echo '<div class="error-page"><div class="error-code">404</div><h1 class="error-title">Dosya Bulunamadı</h1><p class="error-desc">Bu link geçerli değil veya dosya silinmiş.</p><a href="/" class="btn btn-primary">Ana Sayfa</a></div>';
    require_once dirname(__DIR__) . '/app/includes/footer.php';
    exit;
}

// Check expired
if (Upload::isExpired($upload)) {
    http_response_code(410);
    require_once dirname(__DIR__) . '/app/includes/header.php';
    require_once dirname(__DIR__) . '/app/includes/navbar.php';
    echo '<div class="error-page"><div class="error-code" style="font-size:3rem;">⏰</div><h1 class="error-title">Link Süresi Doldu</h1><p class="error-desc">Bu dosyanın paylaşım süresi sona erdi.</p><a href="/" class="btn btn-primary">Yeni Dosya Yükle</a></div>';
    require_once dirname(__DIR__) . '/app/includes/footer.php';
    exit;
}

// Check download limit
if (Upload::isLimitReached($upload)) {
    http_response_code(410);
    require_once dirname(__DIR__) . '/app/includes/header.php';
    require_once dirname(__DIR__) . '/app/includes/navbar.php';
    echo '<div class="error-page"><div class="error-code" style="font-size:3rem;">🚫</div><h1 class="error-title">İndirme Limiti Doldu</h1><p class="error-desc">Bu dosya maksimum indirme limitine ulaştı.</p><a href="/" class="btn btn-primary">Yeni Dosya Yükle</a></div>';
    require_once dirname(__DIR__) . '/app/includes/footer.php';
    exit;
}

// Handle direct file download (AJAX request with file_id)
if (isset($_GET['dl']) && isset($_GET['file_id'])) {
    $fileId = (int)$_GET['file_id'];

    // Password check
    if ($upload['password_hash']) {
        $sessionKey = 'unlocked_' . $token;
        if (empty($_SESSION[$sessionKey])) {
            jsonResponse(['success' => false, 'message' => 'Şifre gerekli.'], 403);
        }
    }

    $file = Database::fetch(
        'SELECT * FROM upload_files WHERE id = ? AND upload_id = ? LIMIT 1',
        [$fileId, $upload['id']]
    );

    if (!$file) {
        http_response_code(404);
        exit('Dosya bulunamadı.');
    }

    $filePath = UPLOAD_PATH . '/' . $token . '/' . $file['stored_name'];
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('Dosya sunucuda bulunamadı.');
    }

    // Record download for first file only (or all files?)
    Upload::recordDownload($upload['id'], Security::getIp());
    Security::recordAction(Security::getIp(), 'download', $token);

    // Send file
    $mimeType = $file['mime_type'] ?: mime_content_type($filePath);
    header('Content-Type: ' . $mimeType);
    $disposition = isset($_GET['preview']) ? 'inline' : 'attachment';
    header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($file['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');

    if (ob_get_level() > 0) { ob_end_clean(); }
    readfile($filePath);
    exit;
}

// Password verification via POST
$passwordError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $upload['password_hash']) {
    if (!Security::verifyCsrf()) {
        $passwordError = 'Güvenlik hatası.';
    } elseif (empty($_POST['file_password'])) {
        $passwordError = 'Şifre boş olamaz.';
    } elseif (!password_verify($_POST['file_password'], $upload['password_hash'])) {
        $passwordError = 'Hatalı şifre.';
    } else {
        $_SESSION['unlocked_' . $token] = true;
    }
}

$isUnlocked = !$upload['password_hash'] || !empty($_SESSION['unlocked_' . $token]);
$files = $isUnlocked ? Upload::getUploadFiles($upload['id']) : [];

// Page display
Settings::load();
$pageTitle = $upload['title'] ?: 'Dosya İndir';
require_once dirname(__DIR__) . '/app/includes/header.php';
require_once dirname(__DIR__) . '/app/includes/navbar.php';
?>

<main>
<div class="download-page">
    <?php if (!$isUnlocked): ?>
    <!-- Password Gate -->
    <div class="password-gate">
        <div class="card" style="text-align:center;">
            <div style="font-size:3rem;margin-bottom:16px;">🔐</div>
            <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:8px;">Şifreli Dosya</h2>
            <p class="text-muted" style="margin-bottom:24px;font-size:.9rem;">Bu dosyaya erişmek için şifre gerekiyor.</p>
            <?php if ($passwordError): ?>
            <div class="alert alert-error"><?= e($passwordError) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
                <div class="form-group">
                    <input type="password" name="file_password" class="form-input" placeholder="Dosya şifresi" autofocus required>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Şifreyi Doğrula</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Download Page -->
    <div class="download-card card fade-in">
        <div class="download-icon">📦</div>
        <h1 class="download-title"><?= e($upload['title'] ?: 'Dosya İndir') ?></h1>
        <p class="download-meta">
            <?= count($files) ?> dosya · <?= formatBytes($upload['total_size']) ?> · <?= timeAgo($upload['created_at']) ?> yüklendi
        </p>

        <div class="download-stats">
            <div class="stat-item">
                <div class="stat-value"><?= $upload['download_count'] ?></div>
                <div class="stat-label">İndirme</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= $upload['download_limit'] ?: '∞' ?></div>
                <div class="stat-label">Limit</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= timeLeft($upload['expires_at']) ?></div>
                <div class="stat-label">Kalan Süre</div>
            </div>
        </div>

        <div class="download-files">
            <?php foreach ($files as $file): ?>
            <div class="download-file-item">
                <span style="font-size:1.4rem;"><?= Upload::getFileIcon($file['mime_type']) ?></span>
                <span class="file-name"><?= e($file['original_name']) ?></span>
                <span class="file-size"><?= formatBytes($file['file_size']) ?></span>
                <?php $preview = Upload::isPreviewable($file['mime_type']); ?>
                <?php if ($preview !== 'none'): ?>
                <a href="/preview.php?token=<?= urlencode($token) ?>&file_id=<?= $file['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">Önizle</a>
                <?php endif; ?>
                <a href="/d/<?= e($token) ?>?dl=1&file_id=<?= $file['id'] ?>" class="btn btn-primary btn-sm">İndir</a>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($files) > 1): ?>
        <div style="margin-top:16px;text-align:center;">
            <p class="text-muted" style="font-size:.85rem;margin-bottom:12px;">Tüm dosyaları ayrı ayrı indirebilirsiniz.</p>
        </div>
        <?php endif; ?>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
            <p style="font-size:.82rem;color:var(--text-muted);text-align:center;margin-bottom:12px;">Bu linki paylaş</p>
            <div class="share-buttons" style="justify-content:center;">
                <button class="share-btn share-copy" onclick="copyText('<?= e(APP_URL . '/d/' . $token) ?>', this)">
                    📋 Linki Kopyala
                </button>
                <a href="https://wa.me/?text=<?= urlencode(APP_URL . '/d/' . $token) ?>" target="_blank" class="share-btn share-wa">WhatsApp</a>
                <a href="https://t.me/share/url?url=<?= urlencode(APP_URL . '/d/' . $token) ?>" target="_blank" class="share-btn share-tg">Telegram</a>
            </div>
        </div>

        <?php $adCode = Settings::get('ad_download', ''); if ($adCode): ?>
        <div style="margin-top:20px;"><?= $adCode ?></div>
        <?php endif; ?>

        <!-- Report -->
        <div style="text-align:center;margin-top:20px;">
            <a href="/report.php?token=<?= e($token) ?>" style="font-size:.78rem;color:var(--text-muted);">Bu içeriği şikayet et</a>
        </div>
    </div>
    <?php endif; ?>
</div>
</main>

<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
