<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Helpers.php';
require_once __DIR__ . '/../app/core/Security.php';

Auth::requireAdmin();
$db  = Database::getInstance();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $id     = (int)($_POST['report_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($id && $action === 'resolve') {
        $db->execute("UPDATE reports SET status='resolved', resolved_at=NOW() WHERE id=?", [$id]);
        $msg = 'Rapor çözümlendi.';
    } elseif ($id && $action === 'delete_file') {
        $r = $db->fetch("SELECT upload_token FROM reports WHERE id=?", [$id]);
        if ($r) {
            $up = $db->fetch("SELECT id FROM uploads WHERE token=?", [$r['upload_token']]);
            if ($up) {
                require_once __DIR__ . '/../app/core/Upload.php';
                Upload::deleteUpload($r['upload_token']);
            }
            $db->execute("UPDATE reports SET status='resolved', resolved_at=NOW() WHERE id=?", [$id]);
        }
        $msg = 'Dosya silindi ve rapor kapatıldı.';
    }
}

$status  = $_GET['status'] ?? 'open';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$total   = $db->fetch("SELECT COUNT(*) as c FROM reports WHERE status=?", [$status])['c'] ?? 0;
$reports = $db->fetchAll("SELECT r.*, u.title as file_title FROM reports r LEFT JOIN uploads u ON r.upload_token=u.token WHERE r.status=? ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset", [$status]);
$pages   = ceil($total / $perPage);
$csrf    = Security::csrfToken();

$pageTitle = 'Rapor Yönetimi';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-content">
  <div class="admin-page-header">
    <h1>Rapor Yönetimi</h1>
  </div>

  <?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <div class="tab-nav">
    <a href="?status=open"     class="tab-btn <?= $status==='open'     ?'active':'' ?>">Açık</a>
    <a href="?status=resolved" class="tab-btn <?= $status==='resolved' ?'active':'' ?>">Çözümlendi</a>
  </div>

  <div class="admin-card">
    <table class="admin-table">
      <thead><tr><th>ID</th><th>Token</th><th>Dosya</th><th>Neden</th><th>Detay</th><th>IP</th><th>Tarih</th><th>İşlem</th></tr></thead>
      <tbody>
      <?php foreach($reports as $r): ?>
        <tr>
          <td>#<?= $r['id'] ?></td>
          <td><a href="/d/<?= e($r['upload_token']) ?>" target="_blank" style="font-family:monospace;font-size:.8rem"><?= e(substr($r['upload_token'],0,10)) ?>…</a></td>
          <td><?= e($r['file_title'] ?: '—') ?></td>
          <td><?= e($r['reason']) ?></td>
          <td style="max-width:200px;word-break:break-word"><?= e(substr($r['details'] ?? '',0,80)) ?></td>
          <td><?= e($r['reporter_ip']) ?></td>
          <td><?= date('d.m.y H:i', strtotime($r['created_at'])) ?></td>
          <td>
            <?php if($r['status'] === 'open'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="resolve">
              <button type="submit" class="btn-sm" style="color:#00D4AA">Kapat</button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Dosya silinecek, emin misin?')">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="action" value="delete_file">
              <button type="submit" class="btn-sm btn-danger">Sil</button>
            </form>
            <?php else: ?>
              <span class="badge badge-success">Çözümlendi</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($reports)): ?>
        <tr><td colspan="8" class="text-muted" style="text-align:center;padding:2rem">Rapor yok</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <?php if($pages > 1): ?>
    <div class="pagination">
      <?php for($p=1;$p<=$pages;$p++): ?>
        <a href="?status=<?=$status?>&page=<?=$p?>" class="page-btn <?=$p==$page?'active':''?>"><?=$p?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<style>
.tab-nav { display:flex; gap:.5rem; margin-bottom:1.5rem; }
.tab-btn { padding:.5rem 1.25rem; border-radius:8px; background:rgba(255,255,255,.06); color:var(--text-secondary); text-decoration:none; }
.tab-btn.active { background:var(--accent-blue); color:#fff; }
.pagination { display:flex; gap:.25rem; padding:1rem; }
.page-btn { padding:.3rem .7rem; border-radius:6px; background:rgba(255,255,255,.07); color:var(--text-secondary); text-decoration:none; }
.page-btn.active { background:var(--accent-blue); color:#fff; }
</style>

<?php require_once __DIR__ . '/_footer.php'; ?>
