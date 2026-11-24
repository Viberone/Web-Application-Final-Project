<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/preferences.php';

require_login();

$user_id = $_SESSION['user_id'];
$prefs = get_preferences($pdo, $user_id);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $font_size = $_POST['font_size'] ?? '';
    $note_color = $_POST['note_color'] ?? '';
    $theme = $_POST['theme'] ?? '';

    if (!in_array($font_size, PREF_FONT_SIZES, true)
        || !in_array($note_color, PREF_NOTE_COLORS, true)
        || !in_array($theme, PREF_THEMES, true)) {
        $error = 'Please choose valid options.';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET font_size = ?, note_color = ?, theme = ? WHERE id = ?');
        $stmt->execute([$font_size, $note_color, $theme, $user_id]);

        header('Location: /preferences.php?saved=1');
        exit;
    }
}

$font_labels = ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large'];
$color_labels = ['default' => 'Default', 'yellow' => 'Yellow', 'green' => 'Green', 'blue' => 'Blue', 'pink' => 'Pink'];
$theme_labels = ['light' => 'Light', 'dark' => 'Dark'];

$page_title = 'Preferences';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <h2 class="mb-4">Preferences</h2>

        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success" role="status">Preferences saved.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/preferences.php">
            <div class="form-group">
                <label for="font_size">Note font size</label>
                <select class="custom-select" id="font_size" name="font_size">
                    <?php foreach ($font_labels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $prefs['font_size'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="note_color">Note colour</label>
                <select class="custom-select" id="note_color" name="note_color">
                    <?php foreach ($color_labels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $prefs['note_color'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="theme">Theme</label>
                <select class="custom-select" id="theme" name="theme">
                    <?php foreach ($theme_labels as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $prefs['theme'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Save preferences</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>