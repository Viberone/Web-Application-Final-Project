<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

function fail($code, $message) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// Everyone this note is shared with
function load_shares($pdo, $note_id) {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.permission, s.shared_at, u.email, u.display_name
         FROM note_shares s
         JOIN users u ON u.id = s.recipient_id
         WHERE s.note_id = ?
         ORDER BY u.email'
    );
    $stmt->execute([$note_id]);
    $shares = $stmt->fetchAll();
    foreach ($shares as &$share) {
        $share['id'] = (int) $share['id'];
    }
    return $shares;
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

// Only the owner of the note can manage its sharing
$stmt = $pdo->prepare('SELECT id, lock_hash FROM notes WHERE id = ? AND user_id = ?');
$stmt->execute([$note_id, $user_id]);
$note = $stmt->fetch();
if (!$note) {
    fail(404, 'Note not found');
}

if ($action === 'list') {
    echo json_encode(['shares' => load_shares($pdo, $note_id)]);

} elseif ($action === 'add') {
    $email = $_POST['email'] ?? '';
    $permission = $_POST['permission'] ?? '';

    if (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
        fail(422, 'Please enter a valid email address.');
    }
    $email = trim($email);
    if (!is_string($permission) || !in_array($permission, ['read', 'edit'], true)) {
        fail(422, 'Please choose a permission.');
    }
    if ($note['lock_hash'] !== null) {
        fail(403, 'Remove the note\'s password before sharing it.');
    }

    // The email must belong to a registered user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $recipient = $stmt->fetch();
    if (!$recipient) {
        fail(404, 'No registered user has this email address.');
    }
    if ((int) $recipient['id'] === (int) $user_id) {
        fail(422, 'You cannot share a note with yourself.');
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO note_shares (note_id, recipient_id, permission) VALUES (?, ?, ?)');
        $stmt->execute([$note_id, $recipient['id'], $permission]);
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') { // unique key: already shared with this user
            fail(409, 'This note is already shared with that user.');
        }
        fail(500, 'Something went wrong. Please try again.');
    }

    echo json_encode(['shares' => load_shares($pdo, $note_id)]);

} elseif ($action === 'update') {
    $share_id = (int) ($_POST['share_id'] ?? 0);
    $permission = $_POST['permission'] ?? '';

    if (!is_string($permission) || !in_array($permission, ['read', 'edit'], true)) {
        fail(422, 'Please choose a permission.');
    }

    $stmt = $pdo->prepare('SELECT id FROM note_shares WHERE id = ? AND note_id = ?');
    $stmt->execute([$share_id, $note_id]);
    if (!$stmt->fetch()) {
        fail(404, 'Share not found.');
    }

    $stmt = $pdo->prepare('UPDATE note_shares SET permission = ? WHERE id = ? AND note_id = ?');
    $stmt->execute([$permission, $share_id, $note_id]);

    echo json_encode(['shares' => load_shares($pdo, $note_id)]);

} elseif ($action === 'revoke') {
    $share_id = (int) ($_POST['share_id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM note_shares WHERE id = ? AND note_id = ?');
    $stmt->execute([$share_id, $note_id]);
    if ($stmt->rowCount() === 0) {
        fail(404, 'Share not found.');
    }

    echo json_encode(['shares' => load_shares($pdo, $note_id)]);

} else {
    fail(400, 'Unknown action.');
}