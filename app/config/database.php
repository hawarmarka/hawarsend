<?php
require_once __DIR__ . '/config.php';

function getDatabaseConfig(): array {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
    }

    return [
        'host'     => env('DB_HOST', 'db'),
        'port'     => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'hawarsend'),
        'username' => env('DB_USERNAME', 'hawarsend'),
        'password' => env('DB_PASSWORD', ''),
        'charset'  => 'utf8mb4',
        'options'  => $options,
    ];
}
