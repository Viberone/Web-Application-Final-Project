<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

require_login();

$user_id = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['new_password_confirm'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // 1. The current password must be correct
    if (!$user || !password_verify($current, $user['password_hash'])) {
        $errors['current_password'] = 'Your current password is incorrect.';
    }

    // 2. Validate the new password
    if (strlen($new) < 8) {
        $errors['new_password'] = 'Password must be at least 8 characters.';
    } elseif ($current === $new) {
        $errors['new_password'] = 'The new password must be different from the current one.';
    }
    if ($new !== $confirm) {
        $errors['new_password_confirm'] = 'The two passwords do not match.';
    }

    // 3. Save
    if (!$errors) {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $user_id]);

        // Secure the session: a new session ID after a security-sensitive change
        session_regenerate_id(true);

        header('Location: /change-password.php?saved=1');
        exit;
    }
}

$page_title = 'Change password';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <h2 class="mb-4">Change password</h2>

        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success" role="status">Your password has been changed.</div>
        <?php endif; ?>

        <form method="post" action="/change-password.php" novalidate>
            <div class="form-group">
                <label for="current_password">Current password</label>
                <input type="password" class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>"
                    id="current_password" name="current_password" autocomplete="current-password" required>
                <div class="invalid-feedback"><?= e($errors['current_password'] ?? '') ?></div>
            </div>

            <div class="form-group">
                <label for="new_password">New password</label>
                <input type="password" class="form-control <?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                    id="new_password" name="new_password" autocomplete="new-password" required>
                <div class="invalid-feedback"><?= e($errors['new_password'] ?? '') ?></div>
            </div>

            <div class="form-group">
                <label for="new_password_confirm">Confirm new password</label>
                <input type="password" class="form-control <?= isset($errors['new_password_confirm']) ? 'is-invalid' : '' ?>"
                    id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required>
                <div class="invalid-feedback"><?= e($errors['new_password_confirm'] ?? '') ?></div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Change password</button>
        </form>

        <p class="mt-3 text-center"><a href="/profile.php">&larr; Back to my profile</a></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>