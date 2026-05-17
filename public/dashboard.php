<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Upload.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';

Auth::requireLogin();
$user = Auth::user();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::verifyCsrf()) {
        $error = 'Güvenlik hatası.';
    } elseif ($_POST['action'] === 'delete' && isset($_POST['token'])) {
        $token = preg_replace('/[^A-Za-z0-9]/', '', $_POST['token']);
        $upload = Upload::getUpload($token);
        if ($upload && $upload['user_id'] == $user['id']) {
            Upload::deleteUpload($token);
            $success = 'Dosya başarıyla silindi.';
        }
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = Database::fetch('SELECT COUNT(*) as cnt FROM uploads WHERE user_id = ?', [$user['id']]);
$totalCount = $total['cnt'];
$totalPages = ceil($totalCount / $perPage);

$uploads = Database::fetchAll(
    'SELECT u.*, COUNT(uf.id) as file_count FROM uploads u
     LEFT JOIN upload_files uf ON uf.upload_id = u.id
     WHERE u.user_id = ?
     GROUP BY u.id
     ORDER BY u.created_at DESC LIMIT ? OFFSET ?',
    [$user['id'], $perPage, $offset]
);

$stats = Database::fetch(
    'SELECT COUNT(*) as total_files, SUM(total_size) as total_size, SUM(download_count) as total_downloads FROM uploads WHERE user_id = ?',
    [$user['id']]
);

Settings::load();
$pageTitle = 'Panelim';
require_once dirname(__DIR__) . '/app/includes/header.php';
require_once dirname(__DIR__) . '/app/includes/navbar.php';
?>
<main>
<div class="container" style="padding-top:40px;padding-bottom:60px;">
    <div style="margin-bottom:28px;">
        <h1 style="font-size:1.5rem;font-weight:700;">Merhaba, <?= e($user['username']) ?>! 👋</h1>
        <p class="text-muted" style="font-size:.9rem;margin-top:4px;">Dosya paylaşımlarınızı burada yönetin.</p>
    </div>

    <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="dashboard-grid" style="margin-bottom:32px;">
        <div class="stat-card">
            <div class="stat-card-icon">📁</div>
            <div>
                <div class="stat-card-num"><?= $stats['total_files'] ?? 0 ?></div>
                <div class="stat-card-label">Toplam Paylaşım</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon">⬇️</div>
            <div>
                <div class="stat-card-num"><?= $stats['total_downloads'] ?? 0 ?></div>
                <div class="stat-card-label">Toplam İndirme</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon">💾</div>
            <div>
                <div class="stat-card-num"><?= formatBytes((int)($stats['total_size'] ?? 0)) ?></div>
                <div class="stat-card-label">Kullanılan Alan</div>
            </div>
        </div>
    </div>

    <!-- File List -->
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h2 style="font-size:1.05rem;font-weight:600;">Paylaşımlarım</h2>
            <a href="/" class="btn btn-primary btn-sm">+ Yeni Yükle</a>
        </div>

        <?php if (empty($uploads)): ?>
        <div style="text-align:center;padding:40px 0;color:var(--text-muted);">
            <div style="font-size:3rem;margin-bottom:12px;">📭</div>
            <p>Henüz dosya yüklemediniz.</p>
            <a href="/" class="btn btn-primary btn-sm" style="margin-top:12px;">İlk Dosyanı Yükle</a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="files-table">
                <thead>
                    <tr>
                        <th>Dosya(lar)</th>
                        <th>Boyut</th>
                        <th>İndirme</th>
                        <th>Süre</th>
                        <th>Tarih</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uploads as $up): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span><?= $up['title'] ? e($up['title']) : ($up['file_count'] . ' dosya') ?></span>
                                <?php if ($up['password_hash']): ?><span class="badge badge-yellow" title="Şifreli">🔐</span><?php endif; ?>
                                <?php if (Upload::isExpired($up)): ?>
                                    <span class="badge badge-red">Süresi Doldu</span>
                                <?php elseif (Upload::isLimitReached($up)): ?>
                                    <span class="badge badge-red">Limit Doldu</span>
                                <?php else: ?>
                                    <span class="badge badge-green">Aktif</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= formatBytes($up['total_size']) ?></td>
                        <td><?= $up['download_count'] ?><?= $up['download_limit'] ? '/'.$up['download_limit'] : '' ?></td>
                        <td style="font-size:.82rem;color:var(--text-muted);"><?= timeLeft($up['expires_at']) ?></td>
                        <td style="font-size:.82rem;color:var(--text-muted);"><?= timeAgo($up['created_at']) ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-secondary btn-sm" onclick="copyText('<?= e(APP_URL . '/d/' . $up['token']) ?>', this)" title="Linki Kopyala">📋</button>
                                <a href="/d/<?= e($up['token']) ?>" class="btn btn-secondary btn-sm" target="_blank" title="Görüntüle">👁</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Bu paylaşımı silmek istediğinize emin misiniz?')">
                                    <input type="hidden" name="csrf_token" value="<?= Security::csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="token" value="<?= e($up['token']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Sil">🗑</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</main>
<?php require_once dirname(__DIR__) . '/app/includes/footer.php'; ?>
