<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

// Join with notes to be sure the attachment belongs to one of this user's notes
$stmt = $pdo->prepare(
    'SELECT a.stored_name
     FROM attachments a
     JOIN notes n ON n.id = a.note_id
     WHERE a.id = ? AND n.user_id = ?'
);
$stmt->execute([$id, $_SESSION['user_id']]);
$att = $stmt->fetch();

if (!$att) {
    http_response_code(404);
    echo json_encode(['error' => 'Attachment not found']);
    exit;
}

$stmt = $pdo->prepare('DELETE FROM attachments WHERE id = ?');
$stmt->execute([$id]);

// Remove the real file too (basename() is a safety net against odd values)
$path = __DIR__ . '/../uploads/' . basename($att['stored_name']);
if (is_file($path)) {
    unlink($path);
}

echo json_encode(['deleted' => true]);