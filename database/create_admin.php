#!/usr/bin/env php
<?php
/** HawarSend — Admin oluşturma scripti */
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

$email = getenv('ADMIN_EMAIL') ?: 'admin@hawarsend.com';
$pass  = getenv('ADMIN_PASSWORD') ?: 'admin123';

$exists = Database::fetch('SELECT id FROM admins WHERE email = ?', [$email]);
if ($exists) {
    echo "ℹ️  Admin zaten mevcut: $email\n";
    exit(0);
}

$hash = password_hash($pass, PASSWORD_BCRYPT);
Database::insert('INSERT INTO admins (email, password, name) VALUES (?, ?, ?)', [$email, $hash, 'Admin']);
echo "✅ Admin oluşturuldu: $email\n";
