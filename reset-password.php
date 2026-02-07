<?php
session_start();
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

$token = isset($_GET['token']) ? sanitize_input($_GET['token']) : '';

if (empty($token)) {
    $error = 'Invalid reset link.';
} elseif (!isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $token) {
    $error = 'Invalid or expired reset token.';
} elseif (isset($_SESSION['reset_expiry']) && strtotime($_SESSION['reset_expiry']) < time()) {
    $error = 'Reset link has expired. Please request a new one.';
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && empty($error)) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password in database
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $_SESSION['reset_user_id']);

        if ($stmt->execute()) {
            $success = 'Password has been reset successfully. You can now login with your new password.';

            // Clear reset session data
            unset($_SESSION['reset_token']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_expiry']);
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    <?php
    // Use cache-busting helper similar to includes/header.php
    function css_with_mtime($path) {
        $full = __DIR__ . parse_url($path, PHP_URL_PATH);
        if (file_exists($full)) {
            return $path . '?v=' . filemtime($full);
        }
        return $path;
    }
    function img_with_mtime($path) {
        $full = __DIR__ . parse_url($path, PHP_URL_PATH);
        if (file_exists($full)) {
            return $path . '?v=' . filemtime($full);
        }
        return $path;
    }
    $stylePath = SITE_URL . '/assets/css/style.css';
    $styleFull = __DIR__ . '/assets/css/style.css';
    $ver = file_exists($styleFull) ? '?v=' . filemtime($styleFull) : '';
    ?>
    <link rel="stylesheet" href="<?php echo $stylePath . $ver; ?>">
</head>
<body class="login-page">
    <div class="login-background">
        <div class="login-overlay"></div>
    </div>

    <div class="login-container">
        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="login-card">
            <!-- Logo -->
            <div class="login-logo">
                <img src="<?php echo img_with_mtime('images/lagusanskydeck-logo.png'); ?>" alt="Lagusan Coffee Skydeck Logo">
            </div>

            <h1 class="login-title">Reset Password</h1>

            <?php if (empty($error) && empty($success)): ?>
                <form method="POST" action="" class="login-form">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-input-container">
                            <input type="password" id="new_password" name="new_password" required>
                            <button type="button" id="toggle-new-password" class="password-toggle" aria-label="Toggle password visibility">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path id="eye-path-new" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-input-container">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <button type="button" id="toggle-confirm-password" class="password-toggle" aria-label="Toggle password visibility">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path id="eye-path-confirm" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="reset_password" class="btn btn-primary btn-block">Reset Password</button>

                    <div class="text-center" style="margin-top: 20px;">
                        <a href="login.php" class="forgot-link">Back to Login</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Password visibility toggles
        document.getElementById('toggle-new-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('new_password');
            const eyePath = document.getElementById('eye-path-new');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyePath.setAttribute('d', 'M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z M14.707 9.293a1 1 0 010 1.414l-1.414-1.414a1 1 0 011.414 0z M18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1z M7 10a1 1 0 011-1h1a1 1 0 110 2H8a1 1 0 01-1-1z');
            } else {
                passwordInput.type = 'password';
                eyePath.setAttribute('d', 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z');
            }
        });

        document.getElementById('toggle-confirm-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
            const eyePath = document.getElementById('eye-path-confirm');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyePath.setAttribute('d', 'M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z M14.707 9.293a1 1 0 010 1.414l-1.414-1.414a1 1 0 011.414 0z M18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1z M7 10a1 1 0 011-1h1a1 1 0 110 2H8a1 1 0 01-1-1z');
            } else {
                passwordInput.type = 'password';
                eyePath.setAttribute('d', 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z');
            }
        });
    </script>
</body>
</html>
