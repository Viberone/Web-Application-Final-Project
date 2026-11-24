<?php
require_once __DIR__ . '/auth.php';
if (!isset($page_title)) {
    $page_title = 'Notes';
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

<body>
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