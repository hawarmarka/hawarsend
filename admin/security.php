<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Helpers.php';
require_once __DIR__ . '/../app/core/Security.php';

Auth::requireAdmin();
$db = Database::getInstance();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
    if (isset($_POST['clear_logs'])) {
        $db->execute("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $msg = '30 günden eski loglar temizlendi.';
    }
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$type    = $_GET['type'] ?? '';
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
if ($type) {
    $where .= ' AND action = :t';
    $params[':t'] = $type;
}

$total = $db->fetch("SELECT COUNT(*) as c FROM activity_logs WHERE $where", $params)['c'] ?? 0;
$logs  = $db->fetchAll("SELECT * FROM activity_logs WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
$pages = ceil($total / $perPage);

// Count blocked IPs (failed logins in last hour)
$blocked = $db->fetchAll("SELECT ip_address, COUNT(*) as attempts FROM activity_logs WHERE action='login_fail' AND created_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE) GROUP BY ip_address HAVING attempts >= 5");

$csrf = Security::csrfToken();
$pageTitle = 'Güvenlik & Loglar';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-content">
  <div class="admin-page-header"><h1>Güvenlik & Aktivite Logları</h1></div>

  <?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <?php if($blocked): ?>
  <div class="admin-card" style="margin-bottom:1.5rem">
    <div class="admin-card-header"><h2>⚠️ Şu An Bloke IP'ler (Son 1 saat, 5+ başarısız giriş)</h2></div>
    <table class="admin-table">
      <thead><tr><th>IP Adresi</th><th>Başarısız Deneme</th></tr></thead>
      <tbody>
      <?php foreach($blocked as $b): ?>
        <tr><td><?= e($b['ip_address']) ?></td><td><span class="badge badge-danger"><?= $b['attempts'] ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="admin-card">
    <div class="admin-card-header">
      <div class="tab-nav" style="margin:0">
        <a href="?type=" class="tab-btn <?= $type===''?'active':'' ?>">Tümü</a>
        <a href="?type=login_fail" class="tab-btn <?= $type==='login_fail'?'active':'' ?>">Başarısız Giriş</a>
        <a href="?type=upload" class="tab-btn <?= $type==='upload'?'active':'' ?>">Yükleme</a>
        <a href="?type=download" class="tab-btn <?= $type==='download'?'active':'' ?>">İndirme</a>
      </div>
      <form method="POST" onsubmit="return confirm('30 günden eski loglar silinecek.')">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="clear_logs" value="1">
        <button type="submit" class="btn-admin btn-danger">Eski Logları Temizle</button>
      </form>
    </div>
    <table class="admin-table">
      <thead><tr><th>Tarih</th><th>Aksiyon</th><th>IP Adresi</th><th>Detay</th></tr></thead>
      <tbody>
      <?php foreach($logs as $l): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('d.m.y H:i:s', strtotime($l['created_at'])) ?></td>
          <td>
            <?php
            $badge = match($l['action']) {
                'login_fail'     => 'badge-danger',
                'upload'         => 'badge-success',
                'download'       => 'badge-info',
                'admin_login_fail' => 'badge-warning',
                default          => ''
            };
            ?>
            <span class="badge <?= $badge ?>"><?= e($l['action']) ?></span>
          </td>
          <td><?= e($l['ip_address'] ?? '—') ?></td>
          <td style="font-size:.82rem;color:var(--text-secondary)"><?= e($l['details'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($logs)): ?>
        <tr><td colspan="4" class="text-muted" style="text-align:center;padding:2rem">Log bulunamadı</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <?php if($pages > 1): ?>
    <div class="pagination">
      <?php for($p=1;$p<=$pages;$p++): ?>
        <a href="?type=<?=$type?>&page=<?=$p?>" class="page-btn <?=$p==$page?'active':''?>"><?=$p?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<style>
.tab-nav { display:flex; gap:.4rem; flex-wrap:wrap; }
.tab-btn { padding:.4rem 1rem; border-radius:7px; background:rgba(255,255,255,.06); color:var(--text-secondary); text-decoration:none; font-size:.85rem; }
.tab-btn.active { background:var(--accent-blue); color:#fff; }
.badge-info { background:rgba(79,159,255,.15); color:#4F9FFF; }
.pagination { display:flex; gap:.25rem; padding:1rem; flex-wrap:wrap; }
.page-btn { padding:.3rem .7rem; border-radius:6px; background:rgba(255,255,255,.07); color:var(--text-secondary); text-decoration:none; }
.page-btn.active { background:var(--accent-blue); color:#fff; }
</style>

<?php require_once __DIR__ . '/_footer.php'; ?>
