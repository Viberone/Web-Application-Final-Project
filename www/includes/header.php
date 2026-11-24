<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/preferences.php';

if (!isset($page_title)) {
    $page_title = 'Notes';
}

// The user's preferences become CSS classes on <body>
$body_classes = '';
if (is_logged_in()) {
    $prefs = get_preferences($pdo, $_SESSION['user_id']);
    $body_classes = 'font-' . $prefs['font_size'] . ' note-' . $prefs['note_color'] . ' theme-' . $prefs['theme'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> - Notes</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/style.css">
</head>

<body class="<?= e($body_classes) ?>">
    <nav class="navbar navbar-expand-md navbar-dark bg-primary">
        <a class="navbar-brand" href="/">Notes</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ml-auto">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item"><a class="nav-link" href="/labels.php">Labels</a></li>
                    <li class="nav-item"><a class="nav-link" href="/profile.php">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="/preferences.php">Preferences</a></li>
                    <li class="nav-item">
                        <span class="navbar-text mr-md-3"><?= e($_SESSION['display_name'] ?? '') ?></span>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="/logout.php">Log out</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="/login.php">Log in</a></li>
                    <li class="nav-item"><a class="nav-link" href="/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <main class="container my-4">