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

$stmt = $pdo->prepare('SELECT lock_hash FROM notes WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user_id]);
$note = $stmt->fetch();

if (!$note) {
    http_response_code(404);
    echo json_encode(['error' => 'Note not found']);
    exit;
}

// A locked note needs its password for deletion, every time
if ($note['lock_hash'] !== null) {
    $password = $_POST['password'] ?? '';
    if (!is_string($password) || $password === '' || !password_verify($password, $note['lock_hash'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Incorrect password.']);
        exit;
    }
}

// Get the stored file names first: the attachment rows disappear with the note (CASCADE)
$stmt = $pdo->prepare('SELECT stored_name FROM attachments WHERE note_id = ?');
$stmt->execute([$id]);
$files = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user_id]);

// The note is gone, now remove its files from disk
foreach ($files as $stored_name) {
    $path = __DIR__ . '/../uploads/' . basename($stored_name);
    if (is_file($path)) {
        unlink($path);
    }
}

unset($_SESSION['unlocked_notes'][$id]);

echo json_encode(['deleted' => true]);