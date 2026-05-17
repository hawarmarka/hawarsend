<?php
header('Content-Type: application/json');

$status = ['status' => 'ok', 'timestamp' => date('c')];

// DB check
try {
    require_once dirname(__DIR__) . '/app/config/config.php';
    require_once dirname(__DIR__) . '/app/core/Database.php';
    Database::fetch('SELECT 1');
    $status['db'] = 'ok';
} catch (Exception $e) {
    $status['db'] = 'error';
    $status['status'] = 'degraded';
}

// Storage check
$status['storage'] = is_writable(dirname(__DIR__) . '/storage/uploads') ? 'ok' : 'error';
if ($status['storage'] === 'error') $status['status'] = 'degraded';

http_response_code($status['status'] === 'ok' ? 200 : 503);
echo json_encode($status);
