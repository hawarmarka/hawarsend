<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Helpers.php';
require_once __DIR__ . '/../app/core/Security.php';

Auth::requireAdmin();
$db  = Database::getInstance();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası.';
    } else {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($uid && $action === 'ban') {
            $db->execute("UPDATE users SET status='banned' WHERE id=?", [$uid]);
            $msg = 'Kullanıcı engellendi.';
        } elseif ($uid && $action === 'activate') {
            $db->execute("UPDATE users SET status='active' WHERE id=?", [$uid]);
            $msg = 'Kullanıcı aktif edildi.';
        } elseif ($uid && $action === 'delete') {
            $db->execute("DELETE FROM users WHERE id=?", [$uid]);
            $msg = 'Kullanıcı silindi.';
        }
    }
}

$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (email LIKE :q OR username LIKE :q2)';
    $params[':q']  = "%$search%";
    $params[':q2'] = "%$search%";
}

$total = $db->fetch("SELECT COUNT(*) as c FROM users WHERE $where", $params)['c'] ?? 0;
$users = $db->fetchAll("SELECT u.*, (SELECT COUNT(*) FROM uploads WHERE user_id=u.id) as file_count FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset", $params);
$pages = ceil($total / $perPage);
$csrf  = Security::csrfToken();

$pageTitle = 'Kullanıcı Yönetimi';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-content">
  <div class="admin-page-header">
    <h1>Kullanıcı Yönetimi</h1>
    <small><?= number_format($total) ?> kullanıcı</small>
  </div>

  <?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <div class="admin-card">
    <div class="admin-card-header">
      <form method="GET" class="search-form">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="E-posta veya kullanıcı adı ara..." class="admin-input">
        <button type="submit" class="btn-admin">Ara</button>
      </form>
    </div>

    <table class="admin-table">
      <thead>
        <tr><th>ID</th><th>E-posta</th><th>Kullanıcı Adı</th><th>Dosyalar</th><th>Durum</th><th>Kayıt</th><th>İşlem</th></tr>
      </thead>
      <tbody>
      <?php foreach($users as $u): ?>
        <tr>
          <td>#<?= $u['id'] ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['username'] ?: '—') ?></td>
          <td><?= $u['file_count'] ?></td>
          <td>
            <?php if($u['status'] === 'active'): ?>
              <span class="badge badge-success">Aktif</span>
            <?php elseif($u['status'] === 'banned'): ?>
              <span class="badge badge-danger">Engelli</span>
            <?php else: ?>
              <span class="badge badge-warning"><?= e($u['status']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= date('d.m.y', strtotime($u['created_at'])) ?></td>
          <td class="action-cell">
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <?php if($u['status'] === 'banned'): ?>
                <input type="hidden" name="action" value="activate">
                <button type="submit" class="btn-sm" style="color:#00D4AA">Aktif Et</button>
              <?php else: ?>
                <input type="hidden" name="action" value="ban">
                <button type="submit" class="btn-sm btn-danger">Engelle</button>
              <?php endif; ?>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Bu kullanıcıyı silmek istediğine emin misin?')">
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn-sm btn-danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(empty($users)): ?>
        <tr><td colspan="7" class="text-muted" style="text-align:center;padding:2rem">Kullanıcı bulunamadı</td></tr>
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
.search-form { display:flex; gap:.5rem; flex:1; }
.search-form .admin-input { flex:1; }
.action-cell { white-space:nowrap; }
.pagination { display:flex; gap:.25rem; padding:1rem; }
.page-btn { padding:.3rem .7rem; border-radius:6px; background:rgba(255,255,255,.07); color:var(--text-secondary); text-decoration:none; }
.page-btn.active { background:var(--accent-blue); color:#fff; }
</style>

<?php require_once __DIR__ . '/_footer.php'; ?>
