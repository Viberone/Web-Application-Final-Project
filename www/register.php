<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

// Already logged in? No need to register.
if (is_logged_in()) {
    header('Location: /');
    exit;
}

$errors = [];
$email = '';
$display_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // 1. Validate input
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($display_name === '' || mb_strlen($display_name) > 100) {
        $errors['display_name'] = 'Display name is required (max 100 characters).';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    if ($password !== $password_confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    // 2. Email must not be used already
    if (!isset($errors['email'])) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered.';
        }
    }

    // 3. No errors: create the account
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(32)); // 64 characters, used later for the activation link

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, display_name, password_hash, activation_token) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$email, $display_name, $hash, $token]);

            // Log the user in automatically
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            $_SESSION['display_name'] = $display_name;

            header('Location: /');
            exit;
        } catch (PDOException $ex) {
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

$page_title = 'Register';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-5">
        <h2 class="mb-4">Create an account</h2>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="post" action="/register.php" novalidate>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                    id="email" name="email" value="<?= e($email) ?>" required>
                <div class="invalid-feedback"><?= e($errors['email'] ?? '') ?></div>
            </div>

            <div class="form-group">
                <label for="display_name">Display name</label>
                <input type="text" class="form-control <?= isset($errors['display_name']) ? 'is-invalid' : '' ?>"
                    id="display_name" name="display_name" value="<?= e($display_name) ?>" required>
                <div class="invalid-feedback"><?= e($errors['display_name'] ?? '') ?></div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                    id="password" name="password" required>
                <div class="invalid-feedback"><?= e($errors['password'] ?? '') ?></div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                    id="password_confirm" name="password_confirm" required>
                <div class="invalid-feedback"><?= e($errors['password_confirm'] ?? '') ?></div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>

        <p class="mt-3 text-center">Already have an account? <a href="/login.php">Log in</a></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>