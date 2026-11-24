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
$id = (int) ($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');

if (($action === 'add' || $action === 'rename') && ($name === '' || mb_strlen($name) > 50)) {
    fail(422, 'Label name is required (max 50 characters).');
}

try {
    if ($action === 'add') {
        $stmt = $pdo->prepare('SELECT id FROM labels WHERE user_id = ? AND name = ?');
        $stmt->execute([$user_id, $name]);
        if ($stmt->fetch()) {
            fail(409, 'You already have a label with this name.');
        }

        $stmt = $pdo->prepare('INSERT INTO labels (user_id, name) VALUES (?, ?)');
        $stmt->execute([$user_id, $name]);
        echo json_encode(['id' => (int) $pdo->lastInsertId(), 'name' => $name]);

    } elseif ($action === 'rename') {
        $stmt = $pdo->prepare('SELECT id FROM labels WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user_id]);
        if (!$stmt->fetch()) {
            fail(404, 'Label not found.');
        }

        // Another label (not this one) must not already use the name
        $stmt = $pdo->prepare('SELECT id FROM labels WHERE user_id = ? AND name = ? AND id <> ?');
        $stmt->execute([$user_id, $name, $id]);
        if ($stmt->fetch()) {
            fail(409, 'You already have a label with this name.');
        }

        $stmt = $pdo->prepare('UPDATE labels SET name = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$name, $id, $user_id]);
        echo json_encode(['id' => $id, 'name' => $name]);

    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM labels WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user_id]);
        if ($stmt->rowCount() === 0) {
            fail(404, 'Label not found.');
        }
        echo json_encode(['deleted' => true]);

    } else {
        fail(400, 'Unknown action.');
    }
} catch (PDOException $ex) {
    // 23000 = unique constraint violation (two requests with the same name at once)
    if ($ex->getCode() === '23000') {
        fail(409, 'You already have a label with this name.');
    }
    fail(500, 'Something went wrong. Please try again.');
}