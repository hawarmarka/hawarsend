<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Settings.php';

class Upload {
    public static function handleUpload(array $files, array $options = []): array {
        $ip = Security::getIp();

        // Rate limit check
        if (Security::isRateLimited($ip, 'upload', 20, 60)) {
            return ['success' => false, 'message' => 'Çok fazla yükleme yaptınız. Lütfen bekleyin.'];
        }

        // Guest upload check
        if (!ALLOW_GUEST_UPLOAD && !isset($_SESSION['user_id'])) {
            return ['success' => false, 'message' => 'Dosya yüklemek için giriş yapmanız gerekiyor.'];
        }

        $userId   = $_SESSION['user_id'] ?? null;
        $token    = Security::generateFileToken();
        $title    = Security::sanitize($options['title'] ?? '');
        $password = !empty($options['password']) ? password_hash($options['password'], PASSWORD_DEFAULT) : null;
        $expireHours = (int)($options['expire_hours'] ?? DEFAULT_EXPIRE_HOURS);
        $downloadLimit = isset($options['download_limit']) ? (int)$options['download_limit'] : 0;
        $selectedCapGb = max(1, min(30, (int)($options['upload_cap_gb'] ?? 10)));
        $selectedCapBytes = $selectedCapGb * 1024 * 1024 * 1024;
        $effectiveCapBytes = min(Security::effectiveMaxBytes(), $selectedCapBytes);
        $expireAt = $expireHours > 0 ? date('Y-m-d H:i:s', strtotime("+{$expireHours} hours")) : null;

        // Validate all files first
        $fileList = [];
        if (isset($files['name']) && is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                $file = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
                $valid = Security::validateFile($file);
                if (!$valid['valid']) {
                    return ['success' => false, 'message' => $file['name'] . ': ' . $valid['message']];
                }
                $fileList[] = $file;
            }
        } else {
            $valid = Security::validateFile($files);
            if (!$valid['valid']) {
                return ['success' => false, 'message' => $valid['message']];
            }
            $fileList[] = $files;
        }

        if (empty($fileList)) {
            return ['success' => false, 'message' => 'Dosya seçilmedi.'];
        }

        $maxFiles = 20;
        try {
            $maxFiles = max(1, (int) Settings::get('max_files_per_upload', 20));
        } catch (Throwable $e) {}
        if (count($fileList) > $maxFiles) {
            return ['success' => false, 'message' => 'Bir yüklemede en fazla ' . $maxFiles . ' dosya gönderebilirsiniz.'];
        }

        // Create upload record
        $totalSize = array_sum(array_column($fileList, 'size'));
        if ($totalSize > $effectiveCapBytes) {
            return ['success' => false, 'message' => 'Toplam dosya boyutu seçtiğiniz ' . $selectedCapGb . ' GB paylaşım limitini aşıyor.'];
        }

        $uploadId = Database::insert(
            'INSERT INTO uploads (token, user_id, title, password_hash, expires_at, download_limit, download_count, total_size, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())',
            [$token, $userId, $title ?: null, $password, $expireAt, $downloadLimit, $totalSize, $ip]
        );

        // Process each file
        $uploadedFiles = [];
        foreach ($fileList as $file) {
            $result = self::saveFile($file, $uploadId, $token);
            if (!$result['success']) {
                // Rollback: delete the upload record and any saved files
                self::deleteUploadFiles($uploadId);
                Database::execute('DELETE FROM uploads WHERE id = ?', [$uploadId]);
                return ['success' => false, 'message' => $result['message']];
            }
            $uploadedFiles[] = $result['file'];
        }

        Security::recordAction($ip, 'upload', $token);

        return [
            'success'  => true,
            'token'    => $token,
            'url'      => APP_URL . '/d/' . $token,
            'files'    => $uploadedFiles,
            'expires'  => $expireAt,
        ];
    }

    private static function saveFile(array $file, int $uploadId, string $token): array {
        $originalName = $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $storedName = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');

        $destDir = UPLOAD_PATH . '/' . $token;
        if (!is_dir($destDir)) {
            mkdir($destDir, 0750, true);
            // Block PHP in this dir
            file_put_contents($destDir . '/.htaccess', "php_flag engine off\nOptions -Indexes\ndeny from all\n");
        }

        $destPath = $destDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'message' => $originalName . ' dosyası kaydedilemedi.'];
        }

        chmod($destPath, 0640);

        $mimeType = mime_content_type($destPath) ?: 'application/octet-stream';
        $fileSize = filesize($destPath);

        Database::insert(
            'INSERT INTO upload_files (upload_id, original_name, stored_name, mime_type, file_size, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$uploadId, $originalName, $storedName, $mimeType, $fileSize]
        );

        return [
            'success'       => true,
            'file' => [
                'original_name' => $originalName,
                'stored_name'   => $storedName,
                'size'          => $fileSize,
                'mime_type'     => $mimeType,
            ]
        ];
    }

    public static function getUpload(string $token): ?array {
        return Database::fetch('SELECT * FROM uploads WHERE token = ? LIMIT 1', [$token]);
    }

    public static function getUploadFiles(int $uploadId): array {
        return Database::fetchAll('SELECT * FROM upload_files WHERE upload_id = ? ORDER BY id ASC', [$uploadId]);
    }

    public static function isExpired(array $upload): bool {
        if (empty($upload['expires_at'])) return false;
        return strtotime($upload['expires_at']) < time();
    }

    public static function isLimitReached(array $upload): bool {
        if (!$upload['download_limit']) return false;
        return $upload['download_count'] >= $upload['download_limit'];
    }

    public static function recordDownload(int $uploadId, string $ip): void {
        Database::execute(
            'INSERT INTO download_logs (upload_id, ip_address, created_at) VALUES (?, ?, NOW())',
            [$uploadId, $ip]
        );
        Database::execute(
            'UPDATE uploads SET download_count = download_count + 1 WHERE id = ?',
            [$uploadId]
        );
    }

    public static function deleteUpload(string $token): bool {
        $upload = self::getUpload($token);
        if (!$upload) return false;

        // Delete files from disk
        $dir = UPLOAD_PATH . '/' . $token;
        if (is_dir($dir)) {
            self::deleteDirectory($dir);
        }

        // Delete DB records
        Database::execute('DELETE FROM upload_files WHERE upload_id = ?', [$upload['id']]);
        Database::execute('DELETE FROM download_logs WHERE upload_id = ?', [$upload['id']]);
        Database::execute('DELETE FROM uploads WHERE id = ?', [$upload['id']]);

        return true;
    }

    private static function getUploadTokenById(int $uploadId): string {
        $row = Database::fetch('SELECT token FROM uploads WHERE id = ? LIMIT 1', [$uploadId]);
        return $row['token'] ?? '';
    }

    private static function deleteUploadFiles(int $uploadId): void {
        $files = self::getUploadFiles($uploadId);
        foreach ($files as $file) {
            $path = UPLOAD_PATH . '/' . self::getUploadTokenById($uploadId) . '/' . $file['stored_name'];
            if (file_exists($path)) unlink($path);
        }
        Database::execute('DELETE FROM upload_files WHERE upload_id = ?', [$uploadId]);
    }

    private static function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) return;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? self::deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public static function formatSize(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 4) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public static function getFileIcon(string $mimeType): string {
        if (str_starts_with($mimeType, 'image/')) return '🖼️';
        if (str_starts_with($mimeType, 'video/')) return '🎬';
        if (str_starts_with($mimeType, 'audio/')) return '🎵';
        if ($mimeType === 'application/pdf') return '📄';
        if (str_contains($mimeType, 'zip') || str_contains($mimeType, 'rar') || str_contains($mimeType, 'archive')) return '🗜️';
        if (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) return '📝';
        if (str_contains($mimeType, 'excel') || str_contains($mimeType, 'spreadsheet')) return '📊';
        if (str_contains($mimeType, 'text/')) return '📃';
        return '📁';
    }

    public static function isPreviewable(string $mimeType): string {
        if (in_array($mimeType, PREVIEWABLE_IMAGES)) return 'image';
        if (in_array($mimeType, PREVIEWABLE_VIDEOS)) return 'video';
        if (in_array($mimeType, PREVIEWABLE_AUDIOS)) return 'audio';
        if ($mimeType === 'application/pdf') return 'pdf';
        return 'none';
    }
}
