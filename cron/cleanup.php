#!/usr/bin/env php
<?php
/**
 * HawarSend — Otomatik Temizlik (Cron)
 *
 * Crontab:
 *   0 2 * * * /usr/local/bin/php /var/www/html/cron/cleanup.php >> /var/www/html/storage/logs/cron.log 2>&1
 */

define('RUNNING_CRON', true);
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Upload.php';

$db  = Database::getInstance();
$log = [];
$log[] = '[' . date('Y-m-d H:i:s') . '] Cleanup başladı.';

// 1. Süresi dolmuş yüklemeleri sil
$expired = $db->fetchAll("SELECT id, token FROM uploads WHERE expires_at IS NOT NULL AND expires_at < NOW()");
$count   = 0;
foreach ($expired as $u) {
    try {
        Upload::deleteUpload($u['token']);
        $count++;
    } catch (Exception $e) {
        $log[] = "HATA: {$u['token']} — " . $e->getMessage();
    }
}
$log[] = "Süresi dolmuş: $count yükleme silindi.";

// 2. Sahipsiz storage klasörlerini temizle
$uploadPath = UPLOAD_PATH;
$orphaned   = 0;
if (is_dir($uploadPath)) {
    foreach (scandir($uploadPath) as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $row = $db->fetch("SELECT id FROM uploads WHERE token=?", [$dir]);
        if (!$row) {
            $dirPath = $uploadPath . '/' . $dir;
            if (is_dir($dirPath)) {
                $files = glob("$dirPath/*");
                if ($files) array_map('unlink', $files);
                rmdir($dirPath);
                $orphaned++;
            }
        }
    }
}
$log[] = "Sahipsiz klasör: $orphaned silindi.";

// 3. Eski aktivite loglarını temizle (90 gün+)
$db->execute("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
$log[] = "Eski activity_logs temizlendi.";

// 4. Eski download loglarını temizle (90 gün+)
$db->execute("DELETE FROM download_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
$log[] = "Eski download_logs temizlendi.";

// 5. Süresi geçmiş şifre sıfırlama tokenlarını temizle
$db->execute("DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
$log[] = "Eski password_resets temizlendi.";

$log[] = '[' . date('Y-m-d H:i:s') . '] Cleanup tamamlandı.';
$log[] = str_repeat('-', 50);

// Loga yaz
$logFile = BASE_PATH . '/storage/logs/cron.log';
$logDir  = dirname($logFile);
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents($logFile, implode(PHP_EOL, $log) . PHP_EOL, FILE_APPEND | LOCK_EX);

echo implode(PHP_EOL, $log) . PHP_EOL;
