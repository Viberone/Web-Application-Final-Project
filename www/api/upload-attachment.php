<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/note-lock.php';

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
$note_id = (int) ($_POST['note_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id, lock_hash FROM notes WHERE id = ? AND user_id = ?');
$stmt->execute([$note_id, $user_id]);
$note = $stmt->fetch();
if (!$note) {
    fail(404, 'Note not found');
}
if (!note_is_unlocked($note_id, $note['lock_hash'])) {
    fail(403, 'This note is locked.');
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        fail(413, 'The file is too large.');
    }
    fail(400, 'Upload failed. Please try again.');
}

$file = $_FILES['file'];
$max_size = 10 * 1024 * 1024; // 10 MB
if ($file['size'] > $max_size) {
    fail(413, 'The file is too large (max 10 MB).');
}
if (!is_uploaded_file($file['tmp_name'])) {
    fail(400, 'Invalid upload.');
}

// Detect the real type from the file content, never trust the browser's value
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

// Random name without extension, so the server can never run an uploaded file as code
$stored_name = bin2hex(random_bytes(16));
$dir = __DIR__ . '/../uploads/';
if (!is_dir($dir) || !move_uploaded_file($file['tmp_name'], $dir . $stored_name)) {
    fail(500, 'Could not store the file.');
}

// Clean the original name: no path, no control characters, max 255 chars
$original = basename(str_replace('\\', '/', $file['name']));
$original = preg_replace('/[\x00-\x1F\x7F]/u', '', $original);
$original = mb_substr($original, 0, 255);
if ($original === '') {
    $original = 'file';
}

$stmt = $pdo->prepare(
    'INSERT INTO attachments (note_id, original_name, stored_name, mime_type, size) VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$note_id, $original, $stored_name, $mime, (int) $file['size']]);
$id = (int) $pdo->lastInsertId();

$image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$video_types = ['video/mp4', 'video/webm'];
$kind = in_array($mime, $image_types, true) ? 'image' : (in_array($mime, $video_types, true) ? 'video' : 'file');

echo json_encode([
    'id' => $id,
    'name' => $original,
    'kind' => $kind,
    'size' => (int) $file['size'],
    'url' => '/api/file.php?id=' . $id,
]);