<?php
require_once dirname(__DIR__) . '/app/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Security.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';
require_once dirname(__DIR__) . '/app/core/Upload.php';
require_once dirname(__DIR__) . '/app/core/Settings.php';
require_once dirname(__DIR__) . '/app/core/Helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Geçersiz istek.'], 405);
}

if (!isAjax()) {
    jsonResponse(['success' => false, 'message' => 'AJAX isteği gerekli.'], 400);
}

if (!Security::verifyCsrf()) {
    jsonResponse(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], 403);
}

// Guest upload check
Settings::load();
$allowGuest = Settings::get('allow_guest', ALLOW_GUEST_UPLOAD ? '1' : '0') === '1';
if (!$allowGuest && !Auth::check()) {
    jsonResponse(['success' => false, 'message' => 'Dosya yüklemek için giriş yapmanız gerekiyor.'], 401);
}

if (empty($_FILES['files'])) {
    jsonResponse(['success' => false, 'message' => 'Dosya seçilmedi.'], 400);
}

$options = [
    'title'          => $_POST['title'] ?? '',
    'password'       => $_POST['password'] ?? '',
    'expire_hours'   => $_POST['expire_hours'] ?? DEFAULT_EXPIRE_HOURS,
    'download_limit' => $_POST['download_limit'] ?? 0,
    'upload_cap_gb'  => $_POST['upload_cap_gb'] ?? 10,
];


$result = Upload::handleUpload($_FILES['files'], $options);
jsonResponse($result, $result['success'] ? 200 : 400);
