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
$user_id = $_SESSION['user_id'];

// Get the stored file names first: the attachment rows disappear with the note (CASCADE)
$stmt = $pdo->prepare(
    'SELECT a.stored_name
     FROM attachments a
     JOIN notes n ON n.id = a.note_id
     WHERE n.id = ? AND n.user_id = ?'
);
$stmt->execute([$id, $user_id]);
$files = $stmt->fetchAll(PDO::FETCH_COLUMN);

// The user_id condition makes sure you can only delete your own notes
$stmt = $pdo->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user_id]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Note not found']);
    exit;
}

// The note is gone, now remove its files from disk
foreach ($files as $stored_name) {
    $path = __DIR__ . '/../uploads/' . basename($stored_name);
    if (is_file($path)) {
        unlink($path);
    }
}

echo json_encode(['deleted' => true]);