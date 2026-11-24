<?php
// The only values a preference may have. Anything else is rejected.
const PREF_FONT_SIZES = ['small', 'medium', 'large'];
const PREF_NOTE_COLORS = ['default', 'yellow', 'green', 'blue', 'pink'];
const PREF_THEMES = ['light', 'dark'];

// Returns the user's preferences, falling back to defaults for anything invalid.
function get_preferences($pdo, $user_id) {
    $prefs = ['font_size' => 'medium', 'note_color' => 'default', 'theme' => 'light'];

    $stmt = $pdo->prepare('SELECT font_size, note_color, theme FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if ($row) {
        if (in_array($row['font_size'], PREF_FONT_SIZES, true)) {
            $prefs['font_size'] = $row['font_size'];
        }
        if (in_array($row['note_color'], PREF_NOTE_COLORS, true)) {
            $prefs['note_color'] = $row['note_color'];
        }
        if (in_array($row['theme'], PREF_THEMES, true)) {
            $prefs['theme'] = $row['theme'];
        }
    }
    return $prefs;
}