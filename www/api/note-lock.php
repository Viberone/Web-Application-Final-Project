<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

function fail($code, $message) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

if (!is_logged_in()) {
    fail(401, 'Not logged in');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'POST required');
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$note_id = (int) ($_POST['note_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, lock_hash FROM notes WHERE id = ? AND user_id = ?');
$stmt->execute([$note_id, $user_id]);
$note = $stmt->fetch();
if (!$note) {
    fail(404, 'Note not found');
}

if ($action === 'enable') {
    if ($note['lock_hash'] !== null) {
        fail(409, 'This note already has a password.');
    }

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if (!is_string($password) || !is_string($confirm) || strlen($password) < 8) {
        fail(422, 'Password must be at least 8 characters.');
    }
    if ($password !== $confirm) {
        fail(422, 'The two passwords do not match.');
    }

    // updated_at = updated_at: locking is not editing, so "last modified" stays the same
    $stmt = $pdo->prepare('UPDATE notes SET lock_hash = ?, updated_at = updated_at WHERE id = ? AND user_id = ?');
    $stmt->execute([password_hash($password, PASSWORD_BCRYPT), $note_id, $user_id]);

    // The person who just set the password does not need to type it again
    $_SESSION['unlocked_notes'][$note_id] = true;

    echo json_encode(['locked' => true]);
} else {
    fail(400, 'Unknown action.');
}