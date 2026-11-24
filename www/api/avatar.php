<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT avatar FROM users WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

$path = ($row && $row['avatar']) ? __DIR__ . '/../uploads/avatars/' . basename($row['avatar']) : '';
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    exit;
}

// Only ever send real image types
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($path);