<?php
session_start();
require_once 'config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $full_name = sanitize_input($_POST['full_name']);
    $username = sanitize_input($_POST['username']);
    $role = sanitize_input($_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name) || empty($username) || empty($role) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username already exists. Please choose another.';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
            $stmt->bind_param("ssss", $full_name, $username, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $success = 'Account created successfully! You can now login.';
                // Clear form
                $full_name = $username = $role = '';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - <?php echo SITE_NAME; ?></title>
    <?php
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
        <div class="login-card">
            <div class="login-logo">
                <img src="images/lagusan-logo.png" alt="Lagusan Coffee Skydeck Logo">
            </div>
            
            <h1 class="login-title">Create Account</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo isset($full_name) ? $full_name : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($username) ? $username : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="">Select your role</option>
                        <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="cashier" <?php echo (isset($role) && $role === 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                        <option value="staff" <?php echo (isset($role) && $role === 'staff') ? 'selected' : ''; ?>>Staff</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" name="register" class="btn btn-primary btn-block">Sign Up</button>
                
                <div class="signup-link">
                    <span>Already have an account? </span>
                    <a href="login.php">Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
