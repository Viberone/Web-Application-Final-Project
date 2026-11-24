<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

require_login();

$note = ['id' => 0, 'title' => '', 'content' => ''];

// Editing an existing note? Load it, but only if it belongs to this user.
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT id, title, content FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['id'], $_SESSION['user_id']]);
    $found = $stmt->fetch();

    if (!$found) {
        http_response_code(404);
        $page_title = 'Note not found';
        require __DIR__ . '/includes/header.php';
        echo '<div class="alert alert-warning">Note not found.</div>';
        echo '<a href="/">Back to my notes</a>';
        require __DIR__ . '/includes/footer.php';
        exit;
    }
    $note = $found;
}

// All labels of this user, and which of them are on this note
$stmt = $pdo->prepare('SELECT id, name FROM labels WHERE user_id = ? ORDER BY name');
$stmt->execute([$_SESSION['user_id']]);
$all_labels = $stmt->fetchAll();

$attached_ids = [];
if ($note['id']) {
    $stmt = $pdo->prepare('SELECT label_id FROM note_labels WHERE note_id = ?');
    $stmt->execute([$note['id']]);
    $attached_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$page_title = $note['id'] ? 'Edit note' : 'New note';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="/">&larr; Back to my notes</a>
    <div>
        <span id="save-status" class="text-muted small" aria-live="polite"><?= $note['id'] ? 'Saved' : '' ?></span>
        <button type="button" id="delete-btn"
            class="btn btn-sm btn-outline-danger ml-2 btn-delete-note <?= $note['id'] ? '' : 'd-none' ?>"
            data-note-id="<?= (int) $note['id'] ?>">Delete</button>
    </div>
</div>

<form id="note-form" data-note-id="<?= (int) $note['id'] ?>">
    <div class="form-group">
        <label for="title" class="sr-only">Title</label>
        <input type="text" class="form-control form-control-lg" id="title" name="title"
            placeholder="Title" maxlength="255" value="<?= e($note['title']) ?>">
    </div>
    <div class="form-group">
        <label for="content" class="sr-only">Content</label>
        <textarea class="form-control" id="content" name="content" rows="12"
            placeholder="Write your note here..."><?= e($note['content']) ?></textarea>
    </div>
    <div class="mt-4" id="note-labels">
    <h6>Labels</h6>
        <?php if (!$all_labels): ?>
            <p class="text-muted small mb-0">You have no labels yet. <a href="/labels.php">Create some</a> first.</p>
        <?php else: ?>
            <?php foreach ($all_labels as $label): ?>
                <div class="custom-control custom-checkbox custom-control-inline">
                    <input type="checkbox" class="custom-control-input note-label-check"
                        id="label-<?= (int) $label['id'] ?>" value="<?= (int) $label['id'] ?>"
                        <?= in_array((int) $label['id'], $attached_ids, true) ? 'checked' : '' ?>
                        <?= $note['id'] ? '' : 'disabled' ?>>
                    <label class="custom-control-label" for="label-<?= (int) $label['id'] ?>"><?= e($label['name']) ?></label>
                </div>
            <?php endforeach; ?>
            <p id="label-hint" class="text-muted small mt-2 mb-0 <?= $note['id'] ? 'd-none' : '' ?>">
                Start writing first. Labels can be added once the note is saved.
            </p>
        <?php endif; ?>
    </div>
</form>

<?php require __DIR__ . '/includes/delete-modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>