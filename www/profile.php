<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

require_login();

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT email, display_name, avatar FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$errors = [];
$display_name = $user['display_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = trim($_POST['display_name'] ?? '');
    $remove_avatar = isset($_POST['remove_avatar']);
    $new_file = null;

    // 1. Validate the display name
    if ($display_name === '' || mb_strlen($display_name) > 100) {
        $errors['display_name'] = 'Display name is required (max 100 characters).';
    }

    // 2. Validate the avatar, if one was chosen
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['avatar'];

        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            $errors['avatar'] = 'The image is too large (max 2 MB).';
        } elseif ($f['error'] !== UPLOAD_ERR_OK) {
            $errors['avatar'] = 'Upload failed. Please try again.';
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            $errors['avatar'] = 'The image is too large (max 2 MB).';
        } else {
            // Check the real content, never the file name or the browser's claimed type
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowed, true) || @getimagesize($f['tmp_name']) === false) {
                $errors['avatar'] = 'Please choose a JPG, PNG, GIF or WebP image.';
            } else {
                $new_file = $f;
            }
        }
    }

    // 3. Save
    if (!$errors) {
        $avatar = $user['avatar'];

        if ($new_file) {
            $dir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $stored_name = bin2hex(random_bytes(16));
            if (move_uploaded_file($new_file['tmp_name'], $dir . $stored_name)) {
                $avatar = $stored_name;
            } else {
                $errors['avatar'] = 'Could not store the image. Please try again.';
            }
        } elseif ($remove_avatar) {
            $avatar = null;
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('UPDATE users SET display_name = ?, avatar = ? WHERE id = ?');
        $stmt->execute([$display_name, $avatar, $user_id]);

        // Delete the old avatar file if it was replaced or removed
        if ($user['avatar'] && $user['avatar'] !== $avatar) {
            $old = __DIR__ . '/uploads/avatars/' . basename($user['avatar']);
            if (is_file($old)) {
                unlink($old);
            }
        }

        $_SESSION['display_name'] = $display_name; // the navbar reads this
        header('Location: /profile.php?saved=1');
        exit;
    }
}

$page_title = 'Profile';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <h2 class="mb-4">My profile</h2>

        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success" role="status">Profile saved.</div>
        <?php endif; ?>

        <div class="text-center mb-4">
            <?php if ($user['avatar']): ?>
                <img src="/api/avatar.php?id=<?= (int) $user_id ?>&amp;v=<?= e($user['avatar']) ?>"
                    alt="Your avatar" class="avatar">
            <?php else: ?>
                <div class="avatar avatar-default" role="img" aria-label="Default avatar">
                    <?= e(mb_strtoupper(mb_substr($user['display_name'], 0, 1))) ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="post" action="/profile.php" enctype="multipart/form-data" novalidate>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" value="<?= e($user['email']) ?>" readonly>
            </div>

            <div class="form-group">
                <label for="display_name">Display name</label>
                <input type="text" class="form-control <?= isset($errors['display_name']) ? 'is-invalid' : '' ?>"
                    id="display_name" name="display_name" maxlength="100" value="<?= e($display_name) ?>" required>
                <div class="invalid-feedback"><?= e($errors['display_name'] ?? '') ?></div>
            </div>

            <div class="form-group">
                <label for="avatar">Avatar (JPG, PNG, GIF or WebP, max 2 MB)</label>
                <input type="file" class="form-control-file <?= isset($errors['avatar']) ? 'is-invalid' : '' ?>"
                    id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                <div class="invalid-feedback"><?= e($errors['avatar'] ?? '') ?></div>
            </div>

            <?php if ($user['avatar']): ?>
                <div class="custom-control custom-checkbox mb-3">
                    <input type="checkbox" class="custom-control-input" id="remove_avatar" name="remove_avatar">
                    <label class="custom-control-label" for="remove_avatar">Remove my avatar</label>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block">Save profile</button>
            <p class="mt-3 text-center"><a href="/change-password.php">Change my password</a></p>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>