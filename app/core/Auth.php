<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';

class Auth {
    public static function login(string $email, string $password, bool $remember = false): bool {
        $ip = Security::getIp();
        if (Security::isLoginBlocked($ip, $email)) return false;

        $user = Database::fetch('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1', [$email]);
        if (!$user || !password_verify($password, $user['password'])) {
            Security::recordFailedLogin($ip, $email);
            return false;
        }

        Security::clearFailedLogins($ip, $email);
        self::setUserSession($user);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (30 * 24 * 3600);
            Database::execute('UPDATE users SET remember_token = ?, remember_expires = ? WHERE id = ?', [$token, date('Y-m-d H:i:s', $expires), $user['id']]);
            setcookie('remember_token', $token, [
                'expires' => $expires,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        Database::execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
        return true;
    }

    public static function adminLogin(string $email, string $password): bool {
        $ip = Security::getIp();
        if (Security::isAdminLoginBlocked($ip)) return false;

        $admin = Database::fetch('SELECT * FROM admins WHERE email = ? LIMIT 1', [$email]);
        if (!$admin || !password_verify($password, $admin['password'])) {
            Security::recordAdminFailedLogin($ip);
            return false;
        }

        Security::clearAdminFailedLogins($ip);
        session_regenerate_id(true);
        $_SESSION['admin_id']    = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name']  = $admin['name'] ?: 'Admin';
        $_SESSION['admin_token'] = bin2hex(random_bytes(32));

        Database::execute('UPDATE admins SET last_login = NOW() WHERE id = ?', [$admin['id']]);
        return true;
    }

    private static function setUserSession(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = $user['username'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void {
        if (isset($_SESSION['user_id'])) {
            Database::execute('UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE id = ?', [$_SESSION['user_id']]);
        }
        setcookie('remember_token', '', time() - 3600, '/');
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    public static function adminLogout(): void {
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    public static function check(): bool {
        if (isset($_SESSION['user_id'])) return true;
        if (isset($_COOKIE['remember_token'])) {
            $user = Database::fetch('SELECT * FROM users WHERE remember_token = ? AND remember_expires > NOW() AND status = "active" LIMIT 1', [$_COOKIE['remember_token']]);
            if ($user) {
                self::setUserSession($user);
                return true;
            }
        }
        return false;
    }

    public static function adminCheck(): bool {
        return isset($_SESSION['admin_id']);
    }

    public static function user(): ?array {
        if (!self::check()) return null;
        return Database::fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$_SESSION['user_id']]);
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    public static function requireAdmin(): void {
        if (!self::adminCheck()) {
            header('Location: /admin/login.php');
            exit;
        }
    }

    public static function register(string $username, string $email, string $password): array {
        if (strlen($username) < 3 || strlen($username) > 30) return ['success' => false, 'message' => 'Kullanıcı adı 3-30 karakter olmalıdır.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'Geçerli bir e-posta adresi girin.'];
        if (strlen($password) < 6) return ['success' => false, 'message' => 'Şifre en az 6 karakter olmalıdır.'];

        $existing = Database::fetch('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1', [$email, $username]);
        if ($existing) return ['success' => false, 'message' => 'Bu e-posta veya kullanıcı adı zaten kullanılıyor.'];

        $id = Database::insert('INSERT INTO users (username, email, password, status, created_at) VALUES (?, ?, ?, "active", NOW())', [$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        return ['success' => true, 'user_id' => $id];
    }
}
