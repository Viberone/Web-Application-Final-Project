<?php
function note_is_unlocked($note_id, $lock_hash) {
    if ($lock_hash === null) {
        return true;
    }
    return !empty($_SESSION['unlocked_notes'][(int) $note_id]);
}