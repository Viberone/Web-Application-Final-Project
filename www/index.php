<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

require_login();

$user_id = $_SESSION['user_id'];

// Search keyword (optional)
$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';

// Selected label ids, sent as "1,2,3" (optional)
$label_ids = [];
if (is_string($_GET['labels'] ?? null) && $_GET['labels'] !== '') {
    $label_ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $_GET['labels'])))));
}

$filtering = ($q !== '' || $label_ids);

$sql = 'SELECT id, title, content, updated_at, pinned_at, lock_hash FROM notes WHERE user_id = ?';
$params = [$user_id];

if ($q !== '') {
    // Escape \ % _ so the user's text is matched literally, then wrap in %...%
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    
    // Locked notes are searched by title only, otherwise search results would leak their content
    $sql .= ' AND (title LIKE ? OR (lock_hash IS NULL AND content LIKE ?))';
    $params[] = $like;
    $params[] = $like;
}

if ($label_ids) {
    // A note matches if it has at least one of the selected labels
    $label_marks = implode(',', array_fill(0, count($label_ids), '?'));
    $sql .= " AND id IN (SELECT note_id FROM note_labels WHERE label_id IN ($label_marks))";
    $params = array_merge($params, $label_ids);
}

$sql .= ' ORDER BY pinned_at IS NULL, pinned_at DESC, updated_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

// Labels of the notes being shown, as [note_id => ['Work', 'Ideas']]
$note_labels = [];
if ($notes) {
    $ids = array_column($notes, 'id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT nl.note_id, l.name
         FROM note_labels nl
         JOIN labels l ON l.id = nl.label_id
         WHERE nl.note_id IN ($marks)
         ORDER BY l.name"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $note_labels[$row['note_id']][] = $row['name'];
    }
}

// All of this user's labels, for the filter buttons
$stmt = $pdo->prepare('SELECT id, name FROM labels WHERE user_id = ? ORDER BY name');
$stmt->execute([$user_id]);
$all_labels = $stmt->fetchAll();

$show_tools = ($notes || $filtering);

$page_title = 'My notes';
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">My notes</h2>
    <div>
        <?php if ($show_tools): ?>
            <div class="btn-group mr-2" role="group" aria-label="Change note layout">
                <button type="button" id="view-grid" class="btn btn-outline-secondary active" aria-pressed="true">Grid</button>
                <button type="button" id="view-list" class="btn btn-outline-secondary" aria-pressed="false">List</button>
            </div>
        <?php endif; ?>
        <a href="/note.php" class="btn btn-primary">New note</a>
    </div>
</div>

<?php if ($show_tools): ?>
    <div class="mb-3">
        <label for="search" class="sr-only">Search notes</label>
        <input type="search" id="search" class="form-control" autocomplete="off"
            placeholder="Search notes by title or content..." value="<?= e($q) ?>">
    </div>

    <?php if ($all_labels): ?>
        <div class="mb-3" id="label-filter" role="group" aria-label="Filter notes by label">
            <?php foreach ($all_labels as $label): ?>
                <?php $on = in_array((int) $label['id'], $label_ids, true); ?>
                <button type="button" class="btn btn-sm btn-outline-secondary label-filter <?= $on ? 'active' : '' ?>"
                    data-label-id="<?= (int) $label['id'] ?>"
                    aria-pressed="<?= $on ? 'true' : 'false' ?>"><?= e($label['name']) ?></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div id="notes-area">
    <?php if (!$notes): ?>
        <div class="text-center text-muted py-5">
            <?php if ($filtering): ?>
                <p class="mb-0">No notes match your search or selected labels.</p>
            <?php else: ?>
                <p class="mb-1">You have no notes yet.</p>
                <p>Click "New note" to create your first one.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($notes as $note): ?>
                <div class="col-12 col-sm-6 col-lg-4 mb-3 note-item">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php if ($note['pinned_at']): ?>
                                    <span class="status-icon" role="img" aria-label="Pinned note">📌</span>
                                <?php endif; ?>
                                <?php if ($note['lock_hash'] !== null): ?>
                                    <span class="status-icon" role="img" aria-label="Password-protected note">🔒</span>
                                <?php endif; ?>
                                <a href="/note.php?id=<?= (int) $note['id'] ?>" class="stretched-link text-dark">
                                    <?= e($note['title'] !== '' ? $note['title'] : 'Untitled') ?>
                                </a>
                            </h5>
                            <?php if ($note['lock_hash'] !== null): ?>
                                <p class="card-text text-muted font-italic">This note is locked.</p>
                            <?php else: ?>
                                <p class="card-text"><?= e(mb_substr($note['content'] ?? '', 0, 150)) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($note_labels[$note['id']])): ?>
                                <div class="mt-2">
                                    <?php foreach ($note_labels[$note['id']] as $label_name): ?>
                                        <span class="badge badge-secondary"><?= e($label_name) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <small class="text-muted">Last modified: <?= e($note['updated_at']) ?></small>
                            <div class="text-nowrap ml-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-pin-note"
                                    data-note-id="<?= (int) $note['id'] ?>"
                                    data-pinned="<?= $note['pinned_at'] ? 1 : 0 ?>"><?= $note['pinned_at'] ? 'Unpin' : 'Pin' ?></button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-note"
                                    data-note-id="<?= (int) $note['id'] ?>"
                                    data-locked="<?= $note['lock_hash'] !== null ? 1 : 0 ?>">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/delete-modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>