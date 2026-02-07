<?php
session_start();
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

// Check for timeout message
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please login again.';
}

// Check for logout message
if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact administrator.';
            } elseif (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    redirect('dashboard.php');
                } elseif ($user['role'] === 'cashier') {
                    redirect('pos.php');
                } elseif ($user['role'] === 'staff') {
                    redirect('staff-dashboard.php');
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
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
        <div class="login-alerts">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php elseif ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
        </div>

        <div class="login-card">
            <!-- Updated logo to use local images folder -->
            <div class="login-logo">
                <img src="<?php echo img_with_mtime('images/lagusanskydeck-logo.png'); ?>" alt="Lagusan Coffee Skydeck Logo">
            </div>
            
            <h1 class="login-title">Login Here</h1>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" required>
                        <button type="button" id="toggle-password" class="password-toggle" aria-label="Toggle password visibility">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path id="eye-path" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                
                <button type="submit" name="login" class="btn btn-primary btn-block">Login</button>

            </form>
        </div>
    </div>

    <script>
        // Password visibility toggle
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyePath = document.getElementById('eye-path');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyePath.setAttribute('d', 'M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z M14.707 9.293a1 1 0 010 1.414l-1.414-1.414a1 1 0 011.414 0z M18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1z M7 10a1 1 0 011-1h1a1 1 0 110 2H8a1 1 0 01-1-1z');
            } else {
                passwordInput.type = 'password';
                eyePath.setAttribute('d', 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z');
            }
        });
        
        // Auto-dismiss login alerts after 15 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.login-alerts .alert');
            if (!alerts || alerts.length === 0) return;
     
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('fade-out');
                    // remove from DOM after transition
                    setTimeout(() => {
                        if (alert && alert.parentNode) alert.parentNode.removeChild(alert);
                    }, 650);
                }, 5000);
                // allow click to dismiss immediately
                alert.addEventListener('click', () => {
                    alert.classList.add('fade-out');
                    setTimeout(() => { if (alert.parentNode) alert.parentNode.removeChild(alert); }, 350);
                });
            });
        });
    </script>
</body>
</html>
