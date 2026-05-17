<?php
require_once __DIR__ . '/Database.php';

class Settings {
    private static array $cache = [];
    private static bool $loaded = false;

    public static function load(): void {
        if (self::$loaded) return;
        try {
            $rows = Database::fetchAll('SELECT `key`, `value` FROM settings');
            foreach ($rows as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
            self::$loaded = true;
        } catch (Exception $e) {
            // DB not ready yet
        }
    }

    public static function get(string $key, mixed $default = null): mixed {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void {
        Database::execute(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?',
            [$key, $value, $value]
        );
        self::$cache[$key] = $value;
    }

    public static function setMultiple(array $settings): void {
        foreach ($settings as $key => $value) {
            self::set($key, $value);
        }
    }

    public static function all(): array {
        self::load();
        return self::$cache;
    }
}
