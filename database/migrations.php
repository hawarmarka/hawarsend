#!/usr/bin/env php
<?php
/** HawarSend — Database Migration Runner */

define('RUNNING_MIGRATION', true);
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';

echo "\n🔧 HawarSend — Veritabanı Migration\n";
echo str_repeat('─', 45) . "\n";

try {
    $config = getDatabaseConfig();
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
    echo "✅ Veritabanı bağlantısı başarılı.\n";
} catch (PDOException $e) {
    echo "❌ Veritabanı bağlantısı başarısız: " . $e->getMessage() . "\n";
    exit(1);
}

function splitSqlStatements(string $sql): array {
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    return array_values(array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== ''));
}

$sqlFile = __DIR__ . '/hawarsend.sql';
if (!file_exists($sqlFile)) {
    echo "❌ hawarsend.sql bulunamadı.\n";
    exit(1);
}

$ok = 0; $fail = 0;
foreach (splitSqlStatements(file_get_contents($sqlFile)) as $stmt) {
    try {
        $pdo->exec($stmt);
        $ok++;
    } catch (PDOException $e) {
        $message = $e->getMessage();
        if (str_contains($message, 'already exists') || str_contains($message, 'Duplicate entry')) {
            $ok++;
        } else {
            echo "⚠️  SQL Hata: {$message}\n";
            $fail++;
        }
    }
}


echo "✅ Ana şema kontrol edildi. ($ok başarılı" . ($fail ? ", $fail hata" : '') . ")\n";

// Brand / default content refresh for old installs using default values.
$defaultRefresh = [
    ['site_name', 'HawarSend', 'Send'],
    ['site_description', 'Basit ve güvenli dosya paylaşım platformu', 'Premium ve güvenli dosya paylaşım platformu'],
    ['hero_title', 'Basit ve güvenli dosya paylaşımı', 'Basit ve gizli dosya paylaşımı'],
    ['hero_description', 'HawarSend ile dosyalarınızı hızlı, güvenli ve şifreli şekilde paylaşın. Link oluşturun, paylaşın, süresi dolunca otomatik silinsin.', 'Send ile dosyalarınızı güvenli bir bağlantı üzerinden paylaşın. Şifre ekleyin, süre belirleyin ve saniyeler içinde paylaşım linkinizi oluşturun.'],
    ['footer_text', 'Güvenli ve hızlı dosya paylaşımı.', ''],
    ['max_file_size', '2048', '30720'],
    ['smtp_from_name', 'HawarSend', 'Send'],
];
foreach ($defaultRefresh as [$key, $oldValue, $newValue]) {
    $stmt = $pdo->prepare('UPDATE settings SET value = ? WHERE `key` = ? AND value = ?');
    $stmt->execute([$newValue, $key, $oldValue]);
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}
function addColumn(PDO $pdo, string $table, string $column, string $definition): void {
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "➕ $table.$column eklendi.\n";
    }
}
function indexExists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}
function addIndex(PDO $pdo, string $table, string $indexSql, string $indexName): void {
    if (!indexExists($pdo, $table, $indexName)) {
        $pdo->exec("ALTER TABLE `$table` ADD $indexSql");
        echo "➕ $table.$indexName index eklendi.\n";
    }
}

// Backward-compatible upgrades for old deployments.
addColumn($pdo, 'users', 'status', "ENUM('active','banned','pending') NOT NULL DEFAULT 'active'");
addColumn($pdo, 'users', 'remember_expires', 'DATETIME DEFAULT NULL');
addColumn($pdo, 'users', 'last_login', 'DATETIME DEFAULT NULL');
addColumn($pdo, 'admins', 'last_login', 'DATETIME DEFAULT NULL');
addColumn($pdo, 'uploads', 'password_hash', 'VARCHAR(255) DEFAULT NULL');
addColumn($pdo, 'uploads', 'expires_at', 'DATETIME DEFAULT NULL');
addColumn($pdo, 'uploads', 'ip_address', 'VARCHAR(45) DEFAULT NULL');
addColumn($pdo, 'password_resets', 'expires_at', 'DATETIME NULL');
addColumn($pdo, 'reports', 'reporter_email', 'VARCHAR(255) DEFAULT NULL');

// If an older DB had old upload column names, copy values once.
foreach ([
    ['uploads', 'password', 'password_hash'],
    ['uploads', 'expire_at', 'expires_at'],
    ['uploads', 'ip', 'ip_address'],
] as [$table, $old, $new]) {
    if (columnExists($pdo, $table, $old) && columnExists($pdo, $table, $new)) {
        $pdo->exec("UPDATE `$table` SET `$new` = `$old` WHERE `$new` IS NULL AND `$old` IS NOT NULL");
    }
}

// If an older activity_logs schema had type/ip/detail, migrate into the final names.
addColumn($pdo, 'activity_logs', 'action', 'VARCHAR(80) NULL');
addColumn($pdo, 'activity_logs', 'ip_address', 'VARCHAR(45) DEFAULT NULL');
addColumn($pdo, 'activity_logs', 'details', 'VARCHAR(512) DEFAULT NULL');
foreach ([['type','action'], ['ip','ip_address'], ['detail','details']] as [$old, $new]) {
    if (columnExists($pdo, 'activity_logs', $old) && columnExists($pdo, 'activity_logs', $new)) {
        $pdo->exec("UPDATE `activity_logs` SET `$new` = `$old` WHERE `$new` IS NULL AND `$old` IS NOT NULL");
    }
}
$pdo->exec("UPDATE activity_logs SET action='unknown' WHERE action IS NULL OR action='' ");

addIndex($pdo, 'uploads', 'KEY `idx_uploads_expires` (`expires_at`)', 'idx_uploads_expires');
addIndex($pdo, 'activity_logs', 'KEY `idx_al_action` (`action`)', 'idx_al_action');
addIndex($pdo, 'activity_logs', 'KEY `idx_al_ip` (`ip_address`)', 'idx_al_ip');

// Create / update admin from runtime env.
$adminEmail    = getenv('ADMIN_EMAIL') ?: 'admin@hawarsend.com';
$adminPassword = getenv('ADMIN_PASSWORD') ?: 'admin123';
$exists = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
$exists->execute([$adminEmail]);
if (!$exists->fetch()) {
    $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
    $ins  = $pdo->prepare("INSERT INTO admins (email, password, name) VALUES (?, ?, 'Admin')");
    $ins->execute([$adminEmail, $hash]);
    echo "✅ Admin oluşturuldu: $adminEmail\n";
} else {
    echo "ℹ️  Admin zaten mevcut: $adminEmail\n";
}

echo str_repeat('─', 45) . "\n";
echo "🚀 Migration başarıyla tamamlandı.\n\n";
