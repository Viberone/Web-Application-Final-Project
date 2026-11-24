<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

require_login();

$stmt = $pdo->prepare('SELECT id, name FROM labels WHERE user_id = ? ORDER BY name');
$stmt->execute([$_SESSION['user_id']]);
$labels = $stmt->fetchAll();

$page_title = 'Labels';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-10 col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Labels</h2>
            <a href="/">&larr; Back to my notes</a>
        </div>

        <div id="label-alert" class="alert alert-danger d-none" role="alert"></div>

        <form id="add-label-form" class="mb-4">
            <div class="input-group">
                <input type="text" id="new-label" class="form-control" placeholder="New label name"
                    maxlength="50" aria-label="New label name" required>
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">Add label</button>
                </div>
            </div>
        </form>

        <?php if (!$labels): ?>
            <div class="text-center text-muted py-4">
                <p class="mb-0">You have no labels yet. Add your first one above.</p>
            </div>
        <?php else: ?>
            <ul class="list-group" id="label-list">
                <?php foreach ($labels as $label): ?>
                    <li class="list-group-item label-row" data-label-id="<?= (int) $label['id'] ?>">
                        <div class="input-group">
                            <input type="text" class="form-control label-name" maxlength="50"
                                aria-label="Label name" value="<?= e($label['name']) ?>">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary btn-rename-label">Rename</button>
                                <button type="button" class="btn btn-outline-danger btn-delete-label">Delete</button>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="label-delete-modal" tabindex="-1" role="dialog" aria-labelledby="label-delete-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="label-delete-title">Delete label</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete the label "<strong id="label-delete-name"></strong>"?
                    Your notes will not be deleted. They just lose this label.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-label-delete">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>