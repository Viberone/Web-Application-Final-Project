<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/note-lock.php';

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
$title = mb_substr($_POST['title'] ?? '', 0, 255);
$content = $_POST['content'] ?? '';
$user_id = $_SESSION['user_id'];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT id, lock_hash FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user_id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'Note not found']);
        exit;
    }
    if (!note_is_unlocked($id, $existing['lock_hash'])) {
        http_response_code(403);
        echo json_encode(['error' => 'This note is locked.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE notes SET title = ?, content = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $content, $id, $user_id]);
} else {
    // Create
    $stmt = $pdo->prepare('INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)');
    $stmt->execute([$user_id, $title, $content]);
    $id = (int) $pdo->lastInsertId();
}

echo json_encode(['id' => $id]);