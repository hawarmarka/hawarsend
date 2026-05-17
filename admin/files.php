<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Upload.php';
require_once __DIR__ . '/../app/core/Helpers.php';
require_once __DIR__ . '/../app/core/Security.php';

Auth::requireAdmin();
$db = Database::getInstance();

$msg = '';
$error = '';

// Delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası.';
    } else {
        if ($_POST['action'] === 'delete' && !empty($_POST['token'])) {
            $upload = Upload::getUpload($_POST['token']);
            if ($upload) {
                Upload::deleteUpload($upload['token']);
                $msg = 'Dosya silindi.';
            }
        } elseif ($_POST['action'] === 'delete_all_expired') {
            $expired = $db->fetchAll("SELECT token FROM uploads WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            foreach ($expired as $u) Upload::deleteUpload($u['token']);
            $msg = count($expired) . ' süresi dolmuş dosya silindi.';
        }
    }
}

// Filters
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (u.title LIKE :q OR u.token LIKE :q2 OR us.email LIKE :q3)';
    $params[':q'] = "%$search%";
    $params[':q2'] = "%$search%";
    $params[':q3'] = "%$search%";
}

$total = $db->fetch("SELECT COUNT(*) as c FROM uploads u LEFT JOIN users us ON u.user_id=us.id WHERE $where", $params)['c'] ?? 0;
$files = $db->fetchAll("SELECT u.*, us.email as uploader FROM uploads u LEFT JOIN users us ON u.user_id=us.id WHERE $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset", $params);

$pages = ceil($total / $perPage);
$csrf  = Security::csrfToken();

$pageTitle = 'Dosya Yönetimi';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-content">
  <div class="admin-page-header">
    <h1>Dosya Yönetimi</h1>
    <small><?= number_format($total) ?> dosya</small>
  </div>

  <?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <div class="admin-card">
    <div class="admin-card-header">
      <form method="GET" class="search-form">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Başlık, token veya e-posta ara..." class="admin-input">
        <button type="submit" class="btn-admin">Ara</button>
      </form>
      <form method="POST" style="display:inline" onsubmit="return confirm('Tüm süresi dolmuş dosyalar silinecek. Emin misin?')">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="delete_all_expired">
        <button type="submit" class="btn-admin btn-danger">Süresi Dolanları Sil</button>
      </form>
    </div>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Token</th>
          <th>Başlık</th>
          <th>Yükleyen</th>
          <th>Boyut</th>
          <th>İndirme</th>
          <th>Son Kullanma</th>
          <th>Tarih</th>
          <th>İşlem</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($files as $f): ?>
        <?php $expired = $f['expires_at'] && strtotime($f['expires_at']) < time(); ?>
        <tr class="<?= $expired ? 'row-expired' : '' ?>">
          <td><a href="/d/<?= e($f['token']) ?>" target="_blank" class="token-link"><?= e(substr($f['token'],0,10)) ?>…</a></td>
          <td><?= e($f['title'] ?: '—') ?></td>
          <td><?= e($f['uploader'] ?: '<i>Misafir</i>') ?></td>
          <td><?= formatBytes($f['total_size']) ?></td>
          <td><?= $f['download_count'] ?><?= $f['download_limit'] ? '/'.$f['download_limit'] : '' ?></td>
          <td><?= $f['expires_at'] ? date('d.m.y H:i', strtotime($f['expires_at'])) : '∞' ?></td>
          <td><?= date('d.m.y H:i', strtotime($f['created_at'])) ?></td>
          <td>
            <form method="POST" onsubmit="return confirm('Bu dosyayı silmek istediğine emin misin?')">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="token" value="<?= e($f['token']) ?>">
              <button type="submit" class="btn-icon btn-danger" title="Sil">🗑</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($files)): ?>
        <tr><td colspan="8" class="text-muted" style="text-align:center;padding:2rem">Dosya bulunamadı</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <?php if($pages > 1): ?>
    <div class="pagination">
      <?php for($p=1;$p<=$pages;$p++): ?>
        <a href="?page=<?=$p?>&q=<?=urlencode($search)?>" class="page-btn <?=$p==$page?'active':''?>"><?=$p?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<style>
.row-expired { opacity:.55; }
.token-link { font-family: monospace; font-size:.8rem; }
.search-form { display:flex; gap:.5rem; flex:1; }
.search-form .admin-input { flex:1; }
.pagination { display:flex; gap:.25rem; padding:1rem; }
.page-btn { padding:.3rem .7rem; border-radius:6px; background:rgba(255,255,255,.07); color:var(--text-secondary); text-decoration:none; }
.page-btn.active { background:var(--accent-blue); color:#fff; }
.btn-icon { background:none; border:none; cursor:pointer; font-size:1rem; padding:.25rem .4rem; border-radius:4px; }
.btn-icon:hover { background:rgba(255,80,80,.2); }
</style>

<?php require_once __DIR__ . '/_footer.php'; ?>
