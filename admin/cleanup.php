<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Upload.php';
require_once __DIR__ . '/../app/core/Helpers.php';
require_once __DIR__ . '/../app/core/Security.php';

Auth::requireAdmin();
$db  = Database::getInstance();
$msg = '';
$error = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'cleanup_expired') {
        $expired = $db->fetchAll("SELECT token FROM uploads WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        $count   = 0;
        foreach ($expired as $u) {
            Upload::deleteUpload($u['token']);
            $count++;
        }
        $results[] = "✅ $count süresi dolmuş yükleme silindi.";
    }

    if ($action === 'cleanup_orphaned') {
        $uploadPath = UPLOAD_PATH;
        $deleted    = 0;
        if (is_dir($uploadPath)) {
            foreach (scandir($uploadPath) as $dir) {
                if ($dir === '.' || $dir === '..') continue;
                $row = $db->fetch("SELECT id FROM uploads WHERE token=?", [$dir]);
                if (!$row) {
                    // Remove orphaned directory
                    $dirPath = $uploadPath . '/' . $dir;
                    if (is_dir($dirPath)) {
                        array_map('unlink', glob("$dirPath/*"));
                        rmdir($dirPath);
                        $deleted++;
                    }
                }
            }
        }
        $results[] = "✅ $deleted sahipsiz klasör silindi.";
    }

    if ($action === 'cleanup_old_logs') {
        $db->execute("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $db->execute("DELETE FROM download_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $results[] = "✅ 90 günden eski loglar temizlendi.";
    }

    if ($action === 'cleanup_old_resets') {
        $db->execute("DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $results[] = "✅ Süresi dolmuş şifre sıfırlama tokenları temizlendi.";
    }

    if (!empty($results)) {
        $msg = implode('<br>', $results);
    }
}

// Stats for display
$expiredCount   = $db->fetch("SELECT COUNT(*) as c FROM uploads WHERE expires_at IS NOT NULL AND expires_at < NOW()")['c'] ?? 0;
$totalSize      = $db->fetch("SELECT COALESCE(SUM(total_size),0) as c FROM uploads")['c'] ?? 0;
$logCount       = $db->fetch("SELECT COUNT(*) as c FROM activity_logs")['c'] ?? 0;
$downloadCount  = $db->fetch("SELECT COUNT(*) as c FROM download_logs")['c'] ?? 0;
$csrf = Security::csrfToken();

$pageTitle = 'Temizleme';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-content">
  <div class="admin-page-header"><h1>Disk & Veritabanı Temizleme</h1></div>

  <?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

  <div class="cleanup-grid">

    <div class="admin-card cleanup-card">
      <div class="cleanup-icon">⏰</div>
      <h3>Süresi Dolmuş Dosyalar</h3>
      <p>Geçerlilik süresi dolmuş tüm yüklemeleri ve fiziksel dosyaları sil.</p>
      <div class="cleanup-stat">
        <span class="stat-num <?= $expiredCount > 0 ? 'text-danger' : '' ?>"><?= $expiredCount ?></span>
        <span class="stat-lbl">silinecek yükleme</span>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="cleanup_expired">
        <button type="submit" class="btn btn-primary btn-full" <?= $expiredCount === 0 ? 'disabled' : '' ?>>
          Süresi Dolanları Sil
        </button>
      </form>
    </div>

    <div class="admin-card cleanup-card">
      <div class="cleanup-icon">📁</div>
      <h3>Sahipsiz Klasörler</h3>
      <p>Veritabanında kaydı olmayan storage klasörlerini temizle.</p>
      <div class="cleanup-stat">
        <span class="stat-num"><?= formatBytes($totalSize) ?></span>
        <span class="stat-lbl">toplam depolama</span>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="cleanup_orphaned">
        <button type="submit" class="btn btn-primary btn-full">Sahipsiz Klasörleri Sil</button>
      </form>
    </div>

    <div class="admin-card cleanup-card">
      <div class="cleanup-icon">📋</div>
      <h3>Eski Loglar</h3>
      <p>90 günden eski aktivite ve indirme loglarını temizle.</p>
      <div class="cleanup-stat">
        <span class="stat-num"><?= number_format($logCount + $downloadCount) ?></span>
        <span class="stat-lbl">toplam log kaydı</span>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="cleanup_old_logs">
        <button type="submit" class="btn btn-primary btn-full">Eski Logları Temizle</button>
      </form>
    </div>

    <div class="admin-card cleanup-card">
      <div class="cleanup-icon">🔑</div>
      <h3>Şifre Sıfırlama Tokenları</h3>
      <p>24 saatten eski geçersiz şifre sıfırlama tokenlarını sil.</p>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="cleanup_old_resets">
        <button type="submit" class="btn btn-primary btn-full">Eski Tokenları Temizle</button>
      </form>
    </div>

  </div>

  <div class="admin-card" style="margin-top:1.5rem">
    <h3 style="margin:0 0 1rem;font-size:1rem">🕐 Otomatik Cron Ayarı</h3>
    <p style="color:var(--text-secondary);margin-bottom:1rem">Süresi dolan dosyaların otomatik temizlenmesi için cron job ekleyin:</p>
    <div class="code-block">
      # Her gece saat 02:00'de temizle<br>
      0 2 * * * /usr/local/bin/php <?= BASE_PATH ?>/cron/cleanup.php >> <?= BASE_PATH ?>/storage/logs/cron.log 2>&1
    </div>
    <p style="color:var(--text-muted);font-size:.85rem;margin-top:.75rem">Docker içinde bu script supervisord ile otomatik çalışacak şekilde yapılandırılmıştır.</p>
  </div>
</div>

<style>
.cleanup-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px,1fr)); gap:1.5rem; }
.cleanup-card { text-align:center; }
.cleanup-icon { font-size:2.5rem; margin-bottom:.75rem; }
.cleanup-card h3 { margin:0 0 .5rem; font-size:1rem; }
.cleanup-card p { color:var(--text-secondary); font-size:.85rem; margin-bottom:1rem; }
.cleanup-stat { margin-bottom:1rem; }
.stat-num { display:block; font-size:1.75rem; font-weight:700; color:var(--accent-blue); }
.stat-lbl { font-size:.8rem; color:var(--text-muted); }
.text-danger { color:#FF5050 !important; }
.btn-full { width:100%; }
.code-block { background:rgba(0,0,0,.4); border-radius:8px; padding:1rem; font-family:monospace; font-size:.85rem; color:#7B5FFF; white-space:pre-wrap; word-break:break-all; }
</style>

<?php require_once __DIR__ . '/_footer.php'; ?>
