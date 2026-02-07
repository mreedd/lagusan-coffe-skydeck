<?php
session_start();
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

// Handle forgot password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = sanitize_input($_POST['email']);

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists in database
        $stmt = $conn->prepare("SELECT id, username, full_name FROM users WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Generate a temporary password reset token
            $reset_token = bin2hex(random_bytes(32));
            $reset_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store reset token in database (you might want to add a password_resets table)
            // For now, we'll store it in session or a temporary way
            $_SESSION['reset_token'] = $reset_token;
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_expiry'] = $reset_expiry;

            // Send email with reset link
            if (send_password_reset_email($email, $user['full_name'], $reset_token)) {
                $success = 'Password reset instructions have been sent to your email.';
            } else {
                $error = 'Failed to send email. Please try again later.';
            }
        } else {
            // Don't reveal if email exists or not for security
            $success = 'If an account with that email exists, password reset instructions have been sent.';
        }
    }
}

// Function to send password reset email
function send_password_reset_email($email, $full_name, $reset_token) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Replace with your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'ireniojhastine@gmail.com'; // Replace with your email
        $mail->Password = 'ylha imrl yffo gknv'; // Replace with your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('noreply@lagusancoffeeskydeck.com', 'Lagusan Coffee Skydeck');
        $mail->addAddress($email, $full_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - Lagusan Coffee Skydeck';

        $reset_link = SITE_URL . '/reset-password.php?token=' . $reset_token;

        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #8B4513; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { background-color: #D2691E; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Lagusan Coffee Skydeck</h1>
                </div>
                <div class='content'>
                    <h2>Password Reset Request</h2>
                    <p>Hello {$full_name},</p>
                    <p>You have requested to reset your password. Click the button below to reset your password:</p>
                    <p><a href='{$reset_link}' class='button'>Reset Password</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this password reset, please ignore this email.</p>
                    <br>
                    <p>Best regards,<br>Lagusan Coffee Skydeck Team</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->AltBody = "Hello {$full_name},\n\nYou have requested to reset your password. Click the link below to reset your password:\n\n{$reset_link}\n\nThis link will expire in 1 hour.\n\nIf you didn't request this password reset, please ignore this email.\n\nBest regards,\nLagusan Coffee Skydeck Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
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

            <h1 class="login-title">Forgot Password</h1>

            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email address">
                </div>

                <button type="submit" name="forgot_password" class="btn btn-primary btn-block">Send Reset Link</button>

                <div class="text-center" style="margin-top: 20px;">
                    <a href="login.php" class="forgot-link">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
