<?php
/**
 * HawarSend — Web Installer
 * Access: /install/index.php
 * After install, this directory is locked via a LOCK file.
 */

// Block if already installed
$lockFile = __DIR__ . '/install.lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    die('Kurulum tamamlandı. Bu sayfaya erişim engellendi. Güvenlik için /install klasörünü silin veya sunucu erişimini engelleyin.');
}

$step    = (int)($_GET['step'] ?? 1);
$errors  = [];
$success = [];

// ─── Step 2: Test & Install ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $dbHost   = trim($_POST['db_host']     ?? '');
    $dbPort   = trim($_POST['db_port']     ?? '3306');
    $dbName   = trim($_POST['db_name']     ?? '');
    $dbUser   = trim($_POST['db_user']     ?? '');
    $dbPass   = $_POST['db_pass']           ?? '';
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_pass']       ?? '';
    $siteUrl    = rtrim(trim($_POST['site_url'] ?? ''), '/');

    // Validate
    if (!$dbHost)      $errors[] = 'DB Host gerekli.';
    if (!$dbName)      $errors[] = 'DB Adı gerekli.';
    if (!$dbUser)      $errors[] = 'DB Kullanıcı gerekli.';
    if (!$adminEmail)  $errors[] = 'Admin e-postası gerekli.';
    if (strlen($adminPass) < 6) $errors[] = 'Admin şifresi en az 6 karakter olmalı.';
    if (!$siteUrl)     $errors[] = 'Site URL gerekli.';

    if (empty($errors)) {
        // Test DB connection
        try {
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $success[] = '✅ Veritabanı bağlantısı başarılı.';
        } catch (PDOException $e) {
            $errors[] = 'Veritabanı bağlantısı başarısız: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        // Run SQL schema
        $sql = file_get_contents(__DIR__ . '/../database/hawarsend.sql');
        $stmts = array_filter(array_map('trim', explode(';', $sql)), fn($s) => strlen(trim($s)) > 3);
        foreach ($stmts as $stmt) {
            try { $pdo->exec($stmt); } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    $errors[] = 'SQL hata: ' . $e->getMessage();
                    break;
                }
            }
        }
        if (empty($errors)) $success[] = '✅ Tablolar oluşturuldu.';
    }

    if (empty($errors)) {
        // Create admin
        $exists = $pdo->prepare("SELECT id FROM admins WHERE email=?");
        $exists->execute([$adminEmail]);
        if (!$exists->fetch()) {
            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO admins (email,password,name) VALUES (?,?, 'Admin')")->execute([$adminEmail, $hash]);
        }
        $success[] = '✅ Admin hesabı oluşturuldu: ' . htmlspecialchars($adminEmail);
    }

    if (empty($errors)) {
        // Write .env
        $envContent = "APP_NAME=HawarSend
APP_ENV=production
APP_DEBUG=false
APP_URL=$siteUrl

DB_HOST=$dbHost
DB_PORT=$dbPort
DB_DATABASE=$dbName
DB_USERNAME=$dbUser
DB_PASSWORD=$dbPass

UPLOAD_MAX_SIZE=2147483648
DEFAULT_EXPIRE_HOURS=24
ALLOW_GUEST_UPLOAD=true

ADMIN_EMAIL=$adminEmail
ADMIN_PASSWORD=$adminPass

SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_FROM=
";
        $envPath = __DIR__ . '/../.env';
        if (is_writable(dirname($envPath))) {
            file_put_contents($envPath, $envContent);
            $success[] = '✅ .env dosyası oluşturuldu.';
        } else {
            $success[] = '⚠️ .env otomatik oluşturulamadı. Aşağıdaki içeriği manuel olarak root dizinine .env olarak kaydedin.';
        }
    }

    if (empty($errors)) {
        // Check storage dirs
        $dirs = [
            __DIR__ . '/../storage/uploads',
            __DIR__ . '/../storage/temp',
            __DIR__ . '/../storage/logs',
        ];
        foreach ($dirs as $d) {
            if (!is_dir($d)) mkdir($d, 0755, true);
            if (is_writable($d)) {
                $success[] = '✅ ' . basename($d) . ' klasörü yazılabilir.';
            } else {
                $errors[] = '❌ ' . $d . ' klasörü yazılabilir değil. chmod 755 uygulayın.';
            }
        }
    }

    if (empty($errors)) {
        // Lock installer
        file_put_contents($lockFile, date('Y-m-d H:i:s'));
        $success[] = '✅ Kurulum kilitlendi.';
        $step = 3;
    }
}

?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HawarSend Kurulum</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#07070f;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:16px;width:100%;max-width:560px;overflow:hidden}
.card-header{background:linear-gradient(135deg,#4F9FFF,#7B5FFF);padding:2rem;text-align:center}
.card-header h1{font-size:1.75rem;margin-bottom:.25rem}
.card-header p{opacity:.8;font-size:.9rem}
.card-body{padding:2rem}
.step-indicator{display:flex;gap:.5rem;margin-bottom:2rem;justify-content:center}
.step{width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:600}
.step.active{background:#4F9FFF;color:#fff}
.step.done{background:#00D4AA;color:#fff}
.form-group{margin-bottom:1.25rem}
label{display:block;margin-bottom:.4rem;font-size:.85rem;color:#94a3b8}
input{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:.65rem 1rem;color:#e2e8f0;font-size:.9rem;outline:none;transition:border-color .2s}
input:focus{border-color:#4F9FFF}
.form-row{display:grid;grid-template-columns:2fr 1fr;gap:1rem}
.btn{width:100%;background:linear-gradient(135deg,#4F9FFF,#7B5FFF);border:none;border-radius:10px;padding:.85rem;color:#fff;font-size:1rem;font-weight:600;cursor:pointer;margin-top:.5rem}
.btn:hover{opacity:.9}
.alert{padding:1rem;border-radius:8px;margin-bottom:1rem;font-size:.9rem}
.alert-danger{background:rgba(255,80,80,.15);border:1px solid rgba(255,80,80,.3);color:#ff8080}
.alert-success{background:rgba(0,212,170,.1);border:1px solid rgba(0,212,170,.3);color:#00D4AA}
.success-icon{font-size:4rem;text-align:center;margin-bottom:1rem}
.info-box{background:rgba(79,159,255,.1);border:1px solid rgba(79,159,255,.2);border-radius:8px;padding:1rem;font-size:.85rem;color:#94a3b8;margin-top:1rem}
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <h1>🚀 HawarSend</h1>
    <p>Kurulum Sihirbazı</p>
  </div>
  <div class="card-body">

    <div class="step-indicator">
      <div class="step <?= $step==1?'active':($step>1?'done':'') ?>">1</div>
      <div class="step <?= $step==2?'active':($step>2?'done':'') ?>">2</div>
      <div class="step <?= $step==3?'active done':'' ?>">3</div>
    </div>

    <?php if($step === 1): ?>
      <h2 style="margin-bottom:1rem;font-size:1.1rem">Gereksinimler Kontrolü</h2>
      <?php
      $checks = [
          'PHP 8.0+' => version_compare(PHP_VERSION, '8.0.0', '>='),
          'PDO MySQL' => extension_loaded('pdo_mysql'),
          'mbstring'  => extension_loaded('mbstring'),
          'fileinfo'  => extension_loaded('fileinfo'),
          'zip'       => extension_loaded('zip'),
          'gd'        => extension_loaded('gd'),
          'storage/uploads yazılabilir' => is_writable(__DIR__ . '/../storage') || is_dir(__DIR__ . '/../storage'),
          'database/hawarsend.sql mevcut' => file_exists(__DIR__ . '/../database/hawarsend.sql'),
      ];
      $allOk = true;
      foreach($checks as $label => $ok):
          if(!$ok) $allOk = false;
      ?>
        <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.06)">
          <span style="font-size:.88rem"><?= htmlspecialchars($label) ?></span>
          <span><?= $ok ? '✅' : '❌' ?></span>
        </div>
      <?php endforeach; ?>

      <?php if(!$allOk): ?>
        <div class="alert alert-danger" style="margin-top:1rem">Bazı gereksinimler karşılanmıyor. Lütfen sunucu yapılandırmanızı kontrol edin.</div>
      <?php else: ?>
        <a href="?step=2"><button class="btn" style="margin-top:1.5rem">Devam Et →</button></a>
      <?php endif; ?>

    <?php elseif($step === 2): ?>
      <h2 style="margin-bottom:1.25rem;font-size:1.1rem">Kurulum Bilgileri</h2>

      <?php if(!empty($errors)): ?>
        <div class="alert alert-danger">
          <?php foreach($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if(!empty($success) && !empty($errors)): ?>
        <div class="alert alert-success">
          <?php foreach($success as $s): ?><div><?= htmlspecialchars($s) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="step" value="2">
        <div class="form-row">
          <div class="form-group">
            <label>DB Host</label>
            <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'db') ?>" placeholder="db veya localhost">
          </div>
          <div class="form-group">
            <label>DB Port</label>
            <input type="number" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Veritabanı Adı</label>
          <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? 'hawarsend') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>DB Kullanıcı</label>
            <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>DB Şifre</label>
            <input type="password" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Site URL</label>
          <input type="url" name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? 'https://') ?>" placeholder="https://hawarsend.com">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Admin E-posta</label>
            <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? 'admin@hawarsend.com') ?>">
          </div>
          <div class="form-group">
            <label>Admin Şifre</label>
            <input type="password" name="admin_pass" placeholder="En az 6 karakter">
          </div>
        </div>
        <button type="submit" class="btn">🚀 Kurulumu Başlat</button>
      </form>

    <?php elseif($step === 3): ?>
      <div class="success-icon">🎉</div>
      <h2 style="text-align:center;margin-bottom:1rem">Kurulum Tamamlandı!</h2>

      <?php if(!empty($success)): ?>
        <div class="alert alert-success">
          <?php foreach($success as $s): ?><div><?= htmlspecialchars($s) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="info-box">
        <strong>⚠️ Güvenlik Uyarısı:</strong> Kurulum tamamlandı. Lütfen <code>/install</code> klasörünü sunucunuzdan silin veya erişimi engelleyin.
      </div>

      <div style="display:flex;gap:1rem;margin-top:1.5rem">
        <a href="/" style="flex:1"><button class="btn" style="background:linear-gradient(135deg,#00D4AA,#4F9FFF)">🏠 Siteye Git</button></a>
        <a href="/admin/login.php" style="flex:1"><button class="btn">🔐 Admin Girişi</button></a>
      </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
