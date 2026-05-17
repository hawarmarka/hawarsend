<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Upload.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';

$token  = preg_replace('/[^A-Za-z0-9]/', '', $_GET['token'] ?? '');
$fileId = (int)($_GET['file_id'] ?? 0);

if (!$token || !$fileId) { http_response_code(404); exit('Not Found'); }

$upload = Upload::getUpload($token);
if (!$upload || Upload::isExpired($upload) || Upload::isLimitReached($upload)) {
    http_response_code(404); exit('Not Found');
}

// Password check
if ($upload['password_hash'] && empty($_SESSION['unlocked_' . $token])) {
    header('Location: /d/' . $token);
    exit;
}

$file = Database::fetch(
    'SELECT * FROM upload_files WHERE id = ? AND upload_id = ? LIMIT 1',
    [$fileId, $upload['id']]
);
if (!$file) { http_response_code(404); exit('Not Found'); }

$filePath = UPLOAD_PATH . '/' . $token . '/' . $file['stored_name'];
if (!file_exists($filePath)) { http_response_code(404); exit('File not found on disk'); }

$previewType = Upload::isPreviewable($file['mime_type']);
$downloadUrl = '/d/' . $token . '?dl=1&file_id=' . $fileId;

Settings::load();
$pageTitle = 'Önizleme: ' . $file['original_name'];
require_once dirname(__DIR__) . '/app/includes/header.php';
?>
<style>
.preview-wrapper { max-width: 900px; margin: 40px auto; padding: 0 24px; }
.preview-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
.preview-container { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
.preview-container img  { display:block; max-width:100%; max-height:80vh; object-fit:contain; margin:0 auto; }
.preview-container video, .preview-container audio { width:100%; display:block; }
.preview-container iframe { width:100%; height:80vh; border:none; display:block; background:#fff; }
</style>
<?php require_once dirname(__DIR__) . '/app/includes/navbar.php'; ?>
<main>
<div class="preview-wrapper">
    <div class="preview-header">
        <div>
            <h2 style="font-size:1rem;font-weight:600;"><?= e($file['original_name']) ?></h2>
            <p style="font-size:.8rem;color:var(--text-muted);"><?= formatBytes($file['file_size']) ?> · <?= e($file['mime_type']) ?></p>
        </div>
        <a href="<?= $downloadUrl ?>" class="btn btn-primary btn-sm">⬇ İndir</a>
    </div>

    <div class="preview-container">
        <?php if ($previewType === 'image'): ?>
            <img src="<?= $downloadUrl ?>&preview=1" alt="<?= e($file['original_name']) ?>">
        <?php elseif ($previewType === 'video'): ?>
            <video controls autoplay>
                <source src="<?= $downloadUrl ?>&preview=1" type="<?= e($file['mime_type']) ?>">
                Tarayıcınız video oynatmayı desteklemiyor.
            </video>
        <?php elseif ($previewType === 'audio'): ?>
            <div style="padding:40px;text-align:center;">
                <div style="font-size:4rem;margin-bottom:16px;">🎵</div>
                <h3 style="margin-bottom:16px;"><?= e($file['original_name']) ?></h3>
                <audio controls style="width:100%;max-width:400px;">
                    <source src="<?= $downloadUrl ?>&preview=1" type="<?= e($file['mime_type']) ?>">
                </audio>
            </div>
        <?php elseif ($previewType === 'pdf'): ?>
            <iframe src="<?= $downloadUrl ?>&preview=1" title="PDF Önizleme"></iframe>
        <?php else: ?>
            <div style="padding:60px;text-align:center;">
                <div style="font-size:4rem;margin-bottom:16px;"><?= Upload::getFileIcon($file['mime_type']) ?></div>
                <p>Bu dosya türü önizlenemiyor.</p>
                <a href="<?= $downloadUrl ?>" class="btn btn-primary" style="margin-top:16px;">⬇ İndir</a>
            </div>
        <?php endif; ?>
    </div>

    <div style="text-align:center;margin-top:16px;">
        <a href="/d/<?= e($token) ?>" class="btn btn-secondary btn-sm">← Geri Dön</a>
    </div>
</div>
</main>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
