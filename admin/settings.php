<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Settings.php';
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
        $tab = $_POST['tab'] ?? 'general';

        $fields = [];

        if ($tab === 'general') {
            $fields = [
                'site_name'        => $_POST['site_name'] ?? '',
                'site_description' => $_POST['site_description'] ?? '',
                'site_keywords'    => $_POST['site_keywords'] ?? '',
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
                'allow_register'   => isset($_POST['allow_register']) ? '1' : '0',
                'allow_guest'      => isset($_POST['allow_guest']) ? '1' : '0',
            ];
        } elseif ($tab === 'upload') {
            $fields = [
                'max_file_size'      => (int)($_POST['max_file_size'] ?? 2048),
                'default_expire'     => (int)($_POST['default_expire'] ?? 24),
                'blocked_extensions' => $_POST['blocked_extensions'] ?? '',
                'max_files_per_upload' => (int)($_POST['max_files_per_upload'] ?? 20),
            ];
        } elseif ($tab === 'appearance') {
            $fields = [
                'hero_title'       => $_POST['hero_title'] ?? '',
                'hero_description' => $_POST['hero_description'] ?? '',
                'custom_css'       => $_POST['custom_css'] ?? '',
                'custom_js'        => $_POST['custom_js'] ?? '',
                'header_code'      => $_POST['header_code'] ?? '',
                'footer_code'      => $_POST['footer_code'] ?? '',
            ];
            // Handle logo upload
            if (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['png','jpg','jpeg','gif','svg','webp'])) {
                    $logoDir = BASE_PATH . '/public/assets/images/';
                    if (!is_dir($logoDir)) mkdir($logoDir, 0755, true);
                    $logoName = 'logo.' . $ext;
                    move_uploaded_file($_FILES['logo']['tmp_name'], $logoDir . $logoName);
                    $fields['logo'] = '/assets/images/' . $logoName;
                }
            }
            if (!empty($_FILES['favicon']['name'])) {
                $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['ico','png','gif'])) {
                    $logoDir = BASE_PATH . '/public/assets/images/';
                    if (!is_dir($logoDir)) mkdir($logoDir, 0755, true);
                    move_uploaded_file($_FILES['favicon']['tmp_name'], $logoDir . 'favicon.' . $ext);
                    $fields['favicon'] = '/assets/images/favicon.' . $ext;
                }
            }
        } elseif ($tab === 'smtp') {
            $fields = [
                'smtp_host'       => $_POST['smtp_host'] ?? '',
                'smtp_port'       => (int)($_POST['smtp_port'] ?? 587),
                'smtp_user'       => $_POST['smtp_user'] ?? '',
                'smtp_pass'       => $_POST['smtp_pass'] ?? '',
                'smtp_from'       => $_POST['smtp_from'] ?? '',
                'smtp_from_name'  => $_POST['smtp_from_name'] ?? '',
            ];
        } elseif ($tab === 'ads') {
            $fields = [
                'ad_top'      => $_POST['ad_top'] ?? '',
                'ad_middle'   => $_POST['ad_middle'] ?? '',
                'ad_download' => $_POST['ad_download'] ?? '',
                'ad_footer'   => $_POST['ad_footer'] ?? '',
                'analytics_code' => $_POST['analytics_code'] ?? '',
            ];
        }

        if (!empty($fields)) {
            Settings::setMultiple($fields);
            $msg = 'Ayarlar kaydedildi.';
        }
    }
}

$activeTab = $_GET['tab'] ?? 'general';
$s = Settings::all();
$csrf = Security::csrfToken();

$pageTitle = 'Site Ayarları';
require_once __DIR__ . '/_header.php';
?>

<div class="admin-content">
  <div class="admin-page-header"><h1>Site Ayarları</h1></div>

  <?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <div class="settings-layout">
    <nav class="settings-nav">
      <?php
      $tabs = [
        'general'    => ['icon' => '⚙️', 'label' => 'Genel'],
        'upload'     => ['icon' => '📤', 'label' => 'Yükleme'],
        'appearance' => ['icon' => '🎨', 'label' => 'Görünüm'],
        'smtp'       => ['icon' => '📧', 'label' => 'E-posta'],
        'ads'        => ['icon' => '💰', 'label' => 'Reklam'],
      ];
      foreach($tabs as $key => $t): ?>
        <a href="?tab=<?= $key ?>" class="settings-nav-item <?= $activeTab===$key?'active':'' ?>">
          <?= $t['icon'] ?> <?= $t['label'] ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="settings-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="tab" value="<?= $activeTab ?>">

        <?php if($activeTab === 'general'): ?>
        <div class="admin-card">
          <h3 class="settings-section-title">Genel Ayarlar</h3>
          <div class="form-group">
            <label>Site Adı</label>
            <input type="text" name="site_name" class="admin-input" value="<?= e($s['site_name'] ?? 'HawarSend') ?>">
          </div>
          <div class="form-group">
            <label>Site Açıklaması</label>
            <input type="text" name="site_description" class="admin-input" value="<?= e($s['site_description'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Anahtar Kelimeler (SEO)</label>
            <input type="text" name="site_keywords" class="admin-input" value="<?= e($s['site_keywords'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="toggle-label">
              <span>Bakım Modu</span>
              <div class="toggle-switch">
                <input type="checkbox" name="maintenance_mode" <?= ($s['maintenance_mode'] ?? '0')==='1'?'checked':'' ?>>
                <span class="toggle-slider"></span>
              </div>
            </label>
            <small class="form-hint">Aktifken yalnızca adminler siteye erişebilir.</small>
          </div>
          <div class="form-group">
            <label class="toggle-label">
              <span>Kayıt Sistemi</span>
              <div class="toggle-switch">
                <input type="checkbox" name="allow_register" <?= ($s['allow_register'] ?? '1')==='1'?'checked':'' ?>>
                <span class="toggle-slider"></span>
              </div>
            </label>
          </div>
          <div class="form-group">
            <label class="toggle-label">
              <span>Misafir Yükleme</span>
              <div class="toggle-switch">
                <input type="checkbox" name="allow_guest" <?= ($s['allow_guest'] ?? '1')==='1'?'checked':'' ?>>
                <span class="toggle-slider"></span>
              </div>
            </label>
          </div>
        </div>

        <?php elseif($activeTab === 'upload'): ?>
        <div class="admin-card">
          <h3 class="settings-section-title">Yükleme Ayarları</h3>
          <div class="form-group">
            <label>Maksimum Dosya Boyutu (MB)</label>
            <input type="number" name="max_file_size" class="admin-input" value="<?= (int)($s['max_file_size'] ?? 2048) ?>" min="1">
          </div>
          <div class="form-group">
            <label>Varsayılan Saklama Süresi (saat, 0=süresiz)</label>
            <input type="number" name="default_expire" class="admin-input" value="<?= (int)($s['default_expire'] ?? 24) ?>" min="0">
          </div>
          <div class="form-group">
            <label>Yükleme Başına Max Dosya</label>
            <input type="number" name="max_files_per_upload" class="admin-input" value="<?= (int)($s['max_files_per_upload'] ?? 20) ?>" min="1">
          </div>
          <div class="form-group">
            <label>Yasaklı Uzantılar (virgülle ayır)</label>
            <textarea name="blocked_extensions" class="admin-input admin-textarea" rows="3"><?= e($s['blocked_extensions'] ?? 'php,phtml,phar,exe,sh,bat,cmd,com,vbs,ps1,jar') ?></textarea>
          </div>
        </div>

        <?php elseif($activeTab === 'appearance'): ?>
        <div class="admin-card">
          <h3 class="settings-section-title">Görünüm Ayarları</h3>
          <div class="form-row">
            <div class="form-group">
              <label>Logo</label>
              <?php if(!empty($s['logo'])): ?>
                <img src="<?= e($s['logo']) ?>" alt="Logo" style="height:40px;margin-bottom:.5rem;display:block">
              <?php endif; ?>
              <input type="file" name="logo" class="admin-input" accept="image/*">
            </div>
            <div class="form-group">
              <label>Favicon</label>
              <input type="file" name="favicon" class="admin-input" accept="image/*,.ico">
            </div>
          </div>
          <div class="form-group">
            <label>Ana Sayfa Başlığı</label>
            <input type="text" name="hero_title" class="admin-input" value="<?= e($s['hero_title'] ?? 'Basit ve güvenli dosya paylaşımı') ?>">
          </div>
          <div class="form-group">
            <label>Ana Sayfa Açıklaması</label>
            <textarea name="hero_description" class="admin-input admin-textarea" rows="3"><?= e($s['hero_description'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Özel CSS</label>
            <textarea name="custom_css" class="admin-input admin-textarea code-area" rows="6"><?= e($s['custom_css'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Özel JavaScript</label>
            <textarea name="custom_js" class="admin-input admin-textarea code-area" rows="6"><?= e($s['custom_js'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>&lt;head&gt; Kodları</label>
            <textarea name="header_code" class="admin-input admin-textarea code-area" rows="4"><?= e($s['header_code'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Footer Kodları</label>
            <textarea name="footer_code" class="admin-input admin-textarea code-area" rows="4"><?= e($s['footer_code'] ?? '') ?></textarea>
          </div>
        </div>

        <?php elseif($activeTab === 'smtp'): ?>
        <div class="admin-card">
          <h3 class="settings-section-title">E-posta (SMTP) Ayarları</h3>
          <div class="form-row">
            <div class="form-group">
              <label>SMTP Host</label>
              <input type="text" name="smtp_host" class="admin-input" value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="form-group">
              <label>SMTP Port</label>
              <input type="number" name="smtp_port" class="admin-input" value="<?= (int)($s['smtp_port'] ?? 587) ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>SMTP Kullanıcı</label>
              <input type="text" name="smtp_user" class="admin-input" value="<?= e($s['smtp_user'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>SMTP Şifre</label>
              <input type="password" name="smtp_pass" class="admin-input" value="<?= e($s['smtp_pass'] ?? '') ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Gönderici E-posta</label>
              <input type="email" name="smtp_from" class="admin-input" value="<?= e($s['smtp_from'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Gönderici Adı</label>
              <input type="text" name="smtp_from_name" class="admin-input" value="<?= e($s['smtp_from_name'] ?? 'HawarSend') ?>">
            </div>
          </div>
        </div>

        <?php elseif($activeTab === 'ads'): ?>
        <div class="admin-card">
          <h3 class="settings-section-title">Reklam & Analytics</h3>
          <div class="form-group">
            <label>Google Analytics / GTM Kodu</label>
            <textarea name="analytics_code" class="admin-input admin-textarea code-area" rows="4"><?= e($s['analytics_code'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Üst Reklam Kodu</label>
            <textarea name="ad_top" class="admin-input admin-textarea code-area" rows="4"><?= e($s['ad_top'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Orta Reklam Kodu</label>
            <textarea name="ad_middle" class="admin-input admin-textarea code-area" rows="4"><?= e($s['ad_middle'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>İndirme Sayfası Reklam</label>
            <textarea name="ad_download" class="admin-input admin-textarea code-area" rows="4"><?= e($s['ad_download'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label>Footer Reklam Kodu</label>
            <textarea name="ad_footer" class="admin-input admin-textarea code-area" rows="4"><?= e($s['ad_footer'] ?? '') ?></textarea>
          </div>
        </div>
        <?php endif; ?>

        <div style="margin-top:1rem">
          <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.settings-layout { display:flex; gap:1.5rem; align-items:flex-start; }
.settings-nav { width:200px; flex-shrink:0; background:rgba(255,255,255,.04); border-radius:12px; padding:.5rem; }
.settings-nav-item { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; color:var(--text-secondary); text-decoration:none; font-size:.9rem; transition:all .2s; }
.settings-nav-item:hover { background:rgba(255,255,255,.06); color:var(--text-primary); }
.settings-nav-item.active { background:var(--accent-blue); color:#fff; }
.settings-body { flex:1; }
.settings-section-title { font-size:1rem; font-weight:600; color:var(--text-primary); margin:0 0 1.5rem; padding-bottom:.75rem; border-bottom:1px solid rgba(255,255,255,.08); }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.admin-textarea { min-height:80px; resize:vertical; }
.code-area { font-family:monospace; font-size:.82rem; }
.form-hint { color:var(--text-muted); font-size:.8rem; margin-top:.25rem; display:block; }
.toggle-label { display:flex; align-items:center; justify-content:space-between; cursor:pointer; }
.toggle-switch { position:relative; display:inline-block; width:44px; height:24px; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; inset:0; background:rgba(255,255,255,.12); border-radius:24px; transition:.3s; cursor:pointer; }
.toggle-slider::before { content:''; position:absolute; height:18px; width:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.3s; }
.toggle-switch input:checked + .toggle-slider { background:var(--accent-blue); }
.toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }
@media(max-width:768px){.settings-layout{flex-direction:column}.settings-nav{width:100%}.form-row{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__ . '/_footer.php'; ?>
