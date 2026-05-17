<?php
// Load .env file
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes
        $value = trim($value, '"\'');
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

loadEnv(dirname(__DIR__, 2) . '/.env');

function env(string $key, mixed $default = null): mixed {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false) return $default;
    if ($val === 'true') return true;
    if ($val === 'false') return false;
    if ($val === 'null') return null;
    return $val;
}

define('APP_NAME',    env('APP_NAME', 'Send'));
define('APP_ENV',     env('APP_ENV', 'production'));
define('APP_DEBUG',   env('APP_DEBUG', false));
define('APP_URL',     rtrim(env('APP_URL', 'http://localhost'), '/'));
define('BASE_PATH',   dirname(__DIR__, 2));
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('TEMP_PATH',   STORAGE_PATH . '/temp');
define('LOG_PATH',    STORAGE_PATH . '/logs');

define('UPLOAD_MAX_SIZE',       (int) env('UPLOAD_MAX_SIZE', 32212254720));
define('DEFAULT_EXPIRE_HOURS',  (int) env('DEFAULT_EXPIRE_HOURS', 24));
define('ALLOW_GUEST_UPLOAD',    env('ALLOW_GUEST_UPLOAD', true) === true || env('ALLOW_GUEST_UPLOAD', 'true') === 'true');

// Blocked extensions
define('BLOCKED_EXTENSIONS', [
    'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
    'exe', 'bat', 'cmd', 'sh', 'bash', 'ps1', 'psm1',
    'msi', 'com', 'scr', 'vbs', 'vbe', 'js', 'jse',
    'wsf', 'wsh', 'hta', 'cpl', 'msc', 'jar', 'jsp',
    'asp', 'aspx', 'cfm', 'cgi', 'pl', 'py', 'rb',
]);

// Allowed MIME preview types
define('PREVIEWABLE_IMAGES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']);
define('PREVIEWABLE_VIDEOS', ['video/mp4', 'video/webm', 'video/ogg']);
define('PREVIEWABLE_AUDIOS', ['audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/webm']);

// Error logging
if (!APP_DEBUG) {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

ini_set('error_log', LOG_PATH . '/php_errors.log');
ini_set('log_errors', '1');

// Session security
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
if (APP_ENV === 'production' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_name('HAWARSEND_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
