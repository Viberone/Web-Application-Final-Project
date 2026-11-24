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

$note_id = (int) ($_POST['note_id'] ?? 0);
$label_id = (int) ($_POST['label_id'] ?? 0);
$attached = (int) ($_POST['attached'] ?? 0) === 1;
$user_id = $_SESSION['user_id'];

// Both the note AND the label must belong to this user
$stmt = $pdo->prepare('SELECT id FROM notes WHERE id = ? AND user_id = ?');
$stmt->execute([$note_id, $user_id]);
$note_ok = $stmt->fetch();

$stmt = $pdo->prepare('SELECT id FROM labels WHERE id = ? AND user_id = ?');
$stmt->execute([$label_id, $user_id]);
$label_ok = $stmt->fetch();

if (!$note_ok || !$label_ok) {
    http_response_code(404);
    echo json_encode(['error' => 'Note or label not found']);
    exit;
}

if ($attached) {
    // INSERT IGNORE: if the pair already exists, do nothing instead of failing
    $stmt = $pdo->prepare('INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (?, ?)');
} else {
    $stmt = $pdo->prepare('DELETE FROM note_labels WHERE note_id = ? AND label_id = ?');
}
$stmt->execute([$note_id, $label_id]);

echo json_encode(['attached' => $attached]);