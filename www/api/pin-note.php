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
$pinned = (int) ($_POST['pinned'] ?? 0) === 1;
$user_id = $_SESSION['user_id'];

// The note must exist and belong to this user
$stmt = $pdo->prepare('SELECT id FROM notes WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Note not found']);
    exit;
}

// updated_at = updated_at stops MySQL from changing the "last modified" time,
// because pinning a note is not editing it.
if ($pinned) {
    $sql = 'UPDATE notes SET pinned_at = NOW(), updated_at = updated_at WHERE id = ? AND user_id = ?';
} else {
    $sql = 'UPDATE notes SET pinned_at = NULL, updated_at = updated_at WHERE id = ? AND user_id = ?';
}
$stmt = $pdo->prepare($sql);
$stmt->execute([$id, $user_id]);

echo json_encode(['pinned' => $pinned]);