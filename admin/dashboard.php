<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Settings.php';
require_once __DIR__ . '/../app/core/Helpers.php';

Auth::requireAdmin();
$db = Database::getInstance();

// Stats
$totalFiles    = $db->fetch("SELECT COUNT(*) as c FROM uploads")['c'] ?? 0;
$totalUsers    = $db->fetch("SELECT COUNT(*) as c FROM users")['c'] ?? 0;
$totalDownloads= $db->fetch("SELECT COALESCE(SUM(download_count),0) as c FROM uploads")['c'] ?? 0;
$diskUsed      = $db->fetch("SELECT COALESCE(SUM(total_size),0) as c FROM uploads")['c'] ?? 0;
$todayFiles    = $db->fetch("SELECT COUNT(*) as c FROM uploads WHERE DATE(created_at)=CURDATE()")['c'] ?? 0;
$openReports   = $db->fetch("SELECT COUNT(*) as c FROM reports WHERE status='open'")['c'] ?? 0;

$recentFiles   = $db->fetchAll("SELECT u.*, us.email as uploader FROM uploads u LEFT JOIN users us ON u.user_id=us.id ORDER BY u.created_at DESC LIMIT 10");
$topFiles      = $db->fetchAll("SELECT * FROM uploads ORDER BY download_count DESC LIMIT 10");

$pageTitle = 'Dashboard';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-content">
  <div class="admin-page-header">
    <h1>Dashboard</h1>
    <span class="admin-date"><?= date('d M Y, H:i') ?></span>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(79,159,255,.15);color:#4F9FFF">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= number_format($totalFiles) ?></div>
        <div class="stat-label">Toplam Dosya</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(123,95,255,.15);color:#7B5FFF">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">Toplam Kullanıcı</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(0,212,170,.15);color:#00D4AA">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= number_format($totalDownloads) ?></div>
        <div class="stat-label">Toplam İndirme</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(255,165,0,.15);color:#FFA500">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= $todayFiles ?></div>
        <div class="stat-label">Bugün Yüklenen</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(255,80,80,.15);color:#FF5050">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= $openReports ?></div>
        <div class="stat-label">Açık Rapor</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(79,159,255,.15);color:#4F9FFF">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-value"><?= formatBytes($diskUsed) ?></div>
        <div class="stat-label">Disk Kullanımı</div>
      </div>
    </div>
  </div>

  <div class="admin-two-col">
    <div class="admin-card">
      <div class="admin-card-header">
        <h2>Son Yüklenen Dosyalar</h2>
        <a href="/admin/files.php" class="btn-sm">Tümü</a>
      </div>
      <table class="admin-table">
        <thead><tr><th>Başlık</th><th>Boyut</th><th>İndirme</th><th>Tarih</th></tr></thead>
        <tbody>
        <?php foreach($recentFiles as $f): ?>
        <tr>
          <td><a href="/d/<?= e($f['token']) ?>" target="_blank"><?= e($f['title'] ?: 'İsimsiz') ?></a></td>
          <td><?= formatBytes($f['total_size']) ?></td>
          <td><?= $f['download_count'] ?></td>
          <td><?= date('d.m.y H:i', strtotime($f['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($recentFiles)): ?><tr><td colspan="4" class="text-muted">Henüz dosya yok</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="admin-card">
      <div class="admin-card-header">
        <h2>En Çok İndirilen</h2>
        <a href="/admin/files.php" class="btn-sm">Tümü</a>
      </div>
      <table class="admin-table">
        <thead><tr><th>Başlık</th><th>Boyut</th><th>İndirme</th></tr></thead>
        <tbody>
        <?php foreach($topFiles as $f): ?>
        <tr>
          <td><a href="/d/<?= e($f['token']) ?>" target="_blank"><?= e($f['title'] ?: 'İsimsiz') ?></a></td>
          <td><?= formatBytes($f['total_size']) ?></td>
          <td><strong style="color:#4F9FFF"><?= $f['download_count'] ?></strong></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($topFiles)): ?><tr><td colspan="3" class="text-muted">Henüz veri yok</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
