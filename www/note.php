<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

require_once __DIR__ . '/includes/note-lock.php';

require_login();

$note = ['id' => 0, 'title' => '', 'content' => '', 'lock_hash' => null];

// Editing an existing note? Load it, but only if it belongs to this user.
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT id, title, content, lock_hash FROM notes WHERE id = ? AND user_id = ?');
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

    // Locked note: nothing is shown until the password is entered
    if (!note_is_unlocked($note['id'], $note['lock_hash'])) {
        $unlock_error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $typed = $_POST['note_password'] ?? '';
            if (is_string($typed) && password_verify($typed, $note['lock_hash'])) {
                $_SESSION['unlocked_notes'][(int) $note['id']] = true;
                header('Location: /note.php?id=' . (int) $note['id']);
                exit;
            }
            $unlock_error = 'Incorrect password.';
        }

        $page_title = 'Locked note';
        require __DIR__ . '/includes/header.php';
        ?>
        <div class="modal fade" id="unlock-modal" tabindex="-1" role="dialog" aria-labelledby="unlock-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form method="post" action="/note.php?id=<?= (int) $note['id'] ?>" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="unlock-title">This note is locked</h5>
                    </div>
                    <div class="modal-body">
                        <p>Enter the password to open "<?= e($note['title'] !== '' ? $note['title'] : 'Untitled') ?>".</p>
                        <div class="form-group mb-0">
                            <label for="note_password" class="sr-only">Password</label>
                            <input type="password" id="note_password" name="note_password" autocomplete="off" required
                                class="form-control <?= $unlock_error !== '' ? 'is-invalid' : '' ?>">
                            <div class="invalid-feedback"><?= e($unlock_error) ?></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="/" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Unlock</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        require __DIR__ . '/includes/footer.php';
        exit;
    }
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

// Attachments of this note
$attachments = [];
if ($note['id']) {
    $stmt = $pdo->prepare('SELECT id, original_name, mime_type, size FROM attachments WHERE note_id = ? ORDER BY id');
    $stmt->execute([$note['id']]);
    $attachments = $stmt->fetchAll();
}
$image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$video_types = ['video/mp4', 'video/webm'];

$page_title = $note['id'] ? 'Edit note' : 'New note';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <a href="/">&larr; Back to my notes</a>
    <div>
        <span id="save-status" class="text-muted small" aria-live="polite"><?= $note['id'] ? 'Saved' : '' ?></span>
        <?php if ($note['lock_hash'] !== null): ?>
            <span class="badge badge-warning ml-2">Locked</span>
            <button type="button" class="btn btn-sm btn-outline-secondary ml-2 mt-1 mt-sm-0"
                data-toggle="modal" data-target="#change-lock-modal">Change password</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ml-1 mt-1 mt-sm-0"
                data-toggle="modal" data-target="#disable-lock-modal">Remove password</button>
        <?php else: ?>
            <button type="button" id="share-btn"
                class="btn btn-sm btn-outline-secondary ml-2 mt-1 mt-sm-0 <?= $note['id'] ? '' : 'd-none' ?>"
                data-toggle="modal" data-target="#share-modal">Share</button>
            <button type="button" id="lock-btn"
                class="btn btn-sm btn-outline-secondary ml-2 <?= $note['id'] ? '' : 'd-none' ?>"
                data-toggle="modal" data-target="#lock-modal">Lock</button>
        <?php endif; ?>
        <button type="button" id="delete-btn"
            class="btn btn-sm btn-outline-danger ml-2 btn-delete-note <?= $note['id'] ? '' : 'd-none' ?>"
            data-note-id="<?= (int) $note['id'] ?>"
            data-locked="<?= $note['lock_hash'] !== null ? 1 : 0 ?>">Delete</button>
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
    <div class="mt-4" id="note-attachments">
        <h6>Attachments</h6>
        <div id="attachment-list">
            <?php foreach ($attachments as $att): ?>
                <?php $url = '/api/file.php?id=' . (int) $att['id']; ?>
                <div class="attachment-item mb-2">
                    <?php if (in_array($att['mime_type'], $image_types, true)): ?>
                        <a href="<?= e($url) ?>" target="_blank" rel="noopener">
                            <img src="<?= e($url) ?>" alt="<?= e($att['original_name']) ?>" class="img-fluid attachment-img">
                        </a>
                    <?php elseif (in_array($att['mime_type'], $video_types, true)): ?>
                        <video controls preload="metadata" class="attachment-video" src="<?= e($url) ?>"></video>
                    <?php else: ?>
                        <a href="<?= e($url) ?>"><?= e($att['original_name']) ?></a>
                        <small class="text-muted">(<?= e(round($att['size'] / 1024)) ?> KB)</small>
                     <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-link text-danger btn-remove-attachment"
                        data-attachment-id="<?= (int) $att['id'] ?>">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>

        <label for="attachment-input" class="sr-only">Add attachments</label>
        <input type="file" id="attachment-input" class="form-control-file" multiple
            <?= $note['id'] ? '' : 'disabled' ?>>
        <p id="attachment-hint" class="text-muted small mt-2 mb-0 <?= $note['id'] ? 'd-none' : '' ?>">
            Start writing first. Attachments can be added once the note is saved.
        </p>
        <div id="attachment-error" class="text-danger small mt-2 d-none" role="alert"></div>
    </div>
</form>

<?php if ($note['lock_hash'] === null): ?>
<div class="modal fade" id="lock-modal" tabindex="-1" role="dialog" aria-labelledby="lock-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="lock-form" class="modal-content" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="lock-modal-title">Lock this note</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Choose a password for this note. You will need it to open, edit or delete the note. If you forget it, it cannot be recovered.</p>
                <div class="form-group">
                    <label for="lock-password">Password</label>
                    <input type="password" class="form-control" id="lock-password" autocomplete="new-password" required>
                </div>
                <div class="form-group mb-0">
                    <label for="lock-password-confirm">Confirm password</label>
                    <input type="password" class="form-control" id="lock-password-confirm" autocomplete="new-password" required>
                </div>
                <p id="lock-error" class="text-danger small mt-2 mb-0 d-none" role="alert"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Lock note</button>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="modal fade" id="change-lock-modal" tabindex="-1" role="dialog" aria-labelledby="change-lock-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="change-lock-form" class="modal-content" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="change-lock-title">Change note password</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="change-current">Current password</label>
                    <input type="password" class="form-control" id="change-current" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label for="change-new">New password</label>
                    <input type="password" class="form-control" id="change-new" autocomplete="new-password" required>
                </div>
                <div class="form-group mb-0">
                    <label for="change-confirm">Confirm new password</label>
                    <input type="password" class="form-control" id="change-confirm" autocomplete="new-password" required>
                </div>
                <p class="text-danger small mt-2 mb-0 d-none lock-form-error" role="alert"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Change password</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="disable-lock-modal" tabindex="-1" role="dialog" aria-labelledby="disable-lock-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="disable-lock-form" class="modal-content" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="disable-lock-title">Remove note password</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">The note will no longer be protected. Enter the current password to confirm.</p>
                <div class="form-group mb-0">
                    <label for="disable-current">Current password</label>
                    <input type="password" class="form-control" id="disable-current" autocomplete="off" required>
                </div>
                <p class="text-danger small mt-2 mb-0 d-none lock-form-error" role="alert"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Remove password</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($note['lock_hash'] === null): ?>
<div class="modal fade" id="share-modal" tabindex="-1" role="dialog" aria-labelledby="share-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="share-modal-title">Share this note</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="share-form" class="mb-3" novalidate>
                    <label for="share-email">Email of a registered user</label>
                    <div class="input-group">
                        <input type="email" class="form-control" id="share-email" placeholder="name@example.com" required>
                        <select class="custom-select" id="share-new-permission" aria-label="Permission" style="max-width: 9rem;">
                            <option value="read">Read only</option>
                            <option value="edit">Can edit</option>
                        </select>
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">Share</button>
                        </div>
                    </div>
                </form>

                <p id="share-error" class="text-danger small d-none" role="alert"></p>

                <h6>People with access</h6>
                <p id="share-empty" class="text-muted small">This note is not shared with anyone yet.</p>
                <ul id="share-list" class="list-group"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/delete-modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>