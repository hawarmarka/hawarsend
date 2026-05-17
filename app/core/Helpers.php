<?php
function redirect(string $url, int $code = 302): never {
    http_response_code($code);
    header('Location: ' . $url);
    exit;
}

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 4) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'Az önce';
    if ($diff < 3600)    return floor($diff/60) . ' dakika önce';
    if ($diff < 86400)   return floor($diff/3600) . ' saat önce';
    if ($diff < 604800)  return floor($diff/86400) . ' gün önce';
    return date('d.m.Y', strtotime($datetime));
}

function timeLeft(?string $expireAt): string {
    if (!$expireAt) return 'Süresiz';
    $diff = strtotime($expireAt) - time();
    if ($diff <= 0) return 'Süresi doldu';
    if ($diff < 3600)    return floor($diff/60) . ' dakika';
    if ($diff < 86400)   return floor($diff/3600) . ' saat';
    return floor($diff/86400) . ' gün';
}

function isAjax(): bool {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function truncate(string $str, int $length = 50): string {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($str, 'UTF-8') <= $length) return $str;
        return mb_substr($str, 0, $length, 'UTF-8') . '...';
    }

    if (strlen($str) <= $length) return $str;
    return substr($str, 0, $length) . '...';
}

function generateQrUrl(string $url): string {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($url);
}
