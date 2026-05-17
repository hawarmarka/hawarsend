<?php
require_once __DIR__ . '/Database.php';

class Security {
    public static function getIp(): string {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token = null): bool {
        $token = $token ?? ($_POST['csrf_token'] ?? '');
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function isLoginBlocked(string $ip, string $email): bool {
        $attempts = Database::fetch(
            'SELECT COUNT(*) as cnt FROM activity_logs WHERE action = "login_fail" AND (ip_address = ? OR details = ?) AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
            [$ip, $email]
        );
        return ($attempts['cnt'] ?? 0) >= 5;
    }

    public static function isAdminLoginBlocked(string $ip): bool {
        $attempts = Database::fetch(
            'SELECT COUNT(*) as cnt FROM activity_logs WHERE action = "admin_login_fail" AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)',
            [$ip]
        );
        return ($attempts['cnt'] ?? 0) >= 5;
    }

    public static function recordFailedLogin(string $ip, string $email): void {
        Database::execute(
            'INSERT INTO activity_logs (action, ip_address, details, created_at) VALUES ("login_fail", ?, ?, NOW())',
            [$ip, $email]
        );
    }

    public static function recordAdminFailedLogin(string $ip): void {
        Database::execute(
            'INSERT INTO activity_logs (action, ip_address, details, created_at) VALUES ("admin_login_fail", ?, "", NOW())',
            [$ip]
        );
    }

    public static function clearFailedLogins(string $ip, string $email): void {
        Database::execute(
            'DELETE FROM activity_logs WHERE action = "login_fail" AND (ip_address = ? OR details = ?)',
            [$ip, $email]
        );
    }

    public static function clearAdminFailedLogins(string $ip): void {
        Database::execute(
            'DELETE FROM activity_logs WHERE action = "admin_login_fail" AND ip_address = ?',
            [$ip]
        );
    }

    public static function isRateLimited(string $ip, string $action = 'upload', int $limit = 10, int $minutes = 60): bool {
        $cnt = Database::fetch(
            'SELECT COUNT(*) as cnt FROM activity_logs WHERE action = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$action, $ip, $minutes]
        );
        return ($cnt['cnt'] ?? 0) >= $limit;
    }

    public static function recordAction(string $ip, string $action, string $detail = ''): void {
        Database::execute(
            'INSERT INTO activity_logs (action, ip_address, details, created_at) VALUES (?, ?, ?, NOW())',
            [$action, $ip, $detail]
        );
    }

    public static function sanitize(string $input): string {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function isBlockedExtension(string $filename): bool {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, BLOCKED_EXTENSIONS, true);
    }

    public static function effectiveMaxBytes(): int {
        $envMax = UPLOAD_MAX_SIZE;
        try {
            $row = Database::fetch('SELECT value FROM settings WHERE `key` = "max_file_size" LIMIT 1');
            $mb = (int)($row['value'] ?? 0);
            if ($mb > 0) return min($envMax, $mb * 1024 * 1024);
        } catch (Throwable $e) {}
        return $envMax;
    }

    public static function validateFile(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE   => 'Dosya boyutu PHP limitini aşıyor.',
                UPLOAD_ERR_FORM_SIZE  => 'Dosya boyutu form limitini aşıyor.',
                UPLOAD_ERR_PARTIAL    => 'Dosya kısmen yüklendi.',
                UPLOAD_ERR_NO_FILE    => 'Dosya seçilmedi.',
                UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
                UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı.',
                UPLOAD_ERR_EXTENSION  => 'PHP uzantısı dosyayı engelledi.',
            ];
            return ['valid' => false, 'message' => $errors[$file['error']] ?? 'Bilinmeyen yükleme hatası.'];
        }

        if (($file['size'] ?? 0) > self::effectiveMaxBytes()) {
            return ['valid' => false, 'message' => 'Dosya boyutu maksimum limiti aşıyor.'];
        }

        if (self::isBlockedExtension($file['name'] ?? '')) {
            return ['valid' => false, 'message' => 'Bu dosya türü yasaklıdır.'];
        }

        try {
            $setting = Database::fetch('SELECT value FROM settings WHERE `key` = "blocked_extensions" LIMIT 1');
            if ($setting) {
                $customBlocked = array_filter(array_map(fn($x) => strtolower(trim($x)), explode(',', $setting['value'])));
                $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
                if ($ext && in_array($ext, $customBlocked, true)) {
                    return ['valid' => false, 'message' => 'Bu dosya türü yasaklıdır.'];
                }
            }
        } catch (Throwable $e) {}

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'Geçersiz yükleme.'];
        }

        return ['valid' => true];
    }

    public static function generateToken(int $length = 12): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $token = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[random_int(0, $max)];
        }
        return $token;
    }

    public static function generateFileToken(): string {
        do {
            $token = self::generateToken(12);
            $exists = Database::fetch('SELECT id FROM uploads WHERE token = ? LIMIT 1', [$token]);
        } while ($exists);
        return $token;
    }
}
