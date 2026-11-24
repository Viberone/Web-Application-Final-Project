<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);

// Join with notes so we can check that the note belongs to this user
$stmt = $pdo->prepare(
    'SELECT a.original_name, a.stored_name, a.mime_type, a.size
     FROM attachments a
     JOIN notes n ON n.id = a.note_id
     WHERE a.id = ? AND n.user_id = ?'
);
$stmt->execute([$id, $_SESSION['user_id']]);
$att = $stmt->fetch();

$path = $att ? __DIR__ . '/../uploads/' . $att['stored_name'] : '';
if (!$att || !is_file($path)) {
    http_response_code(404);
    exit;
}

// Only safe image and video types are shown inline. Everything else is a download.
$inline_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm'];
$inline = in_array($att['mime_type'], $inline_types, true);

header('Content-Type: ' . ($inline ? $att['mime_type'] : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($att['original_name']));

readfile($path);