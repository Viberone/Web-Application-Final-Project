<div class="modal fade" id="delete-modal" tabindex="-1" role="dialog" aria-labelledby="delete-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="delete-modal-title">Delete note</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this note? This cannot be undone.</p>
                <div class="form-group mt-3 mb-0 d-none" id="delete-password-group">
                    <label for="delete-password">This note is locked. Enter its password to delete it.</label>
                    <input type="password" class="form-control" id="delete-password" autocomplete="off">
                </div>
                <p class="text-danger mb-0 mt-2 d-none" id="delete-error" role="alert"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete">Delete</button>
            </div>
        </div>
    </div>
</div>