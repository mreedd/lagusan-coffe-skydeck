<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    // User confirmed logout
    session_unset();
    session_destroy();
    redirect('login.php?logout=1');
}

// Get username for display
$username = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout - <?php echo SITE_NAME; ?></title>
    <?php
    $stylePath = SITE_URL . '/assets/css/style.css';
    $styleFull = __DIR__ . '/assets/css/style.css';
    $ver = file_exists($styleFull) ? '?v=' . filemtime($styleFull) : '';
    ?>
    <link rel="stylesheet" href="<?php echo $stylePath . $ver; ?>">
    <style>
        /* Logout confirmation modal styles */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #8b6f47 0%, #b5a394 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .logout-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .logout-header {
            background: linear-gradient(135deg, #96715e 0%, #b5a394 100%);
            color: white;
            padding: 30px 24px;
            text-align: center;
        }

        .logout-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .logout-body {
            padding: 32px 24px;
            text-align: center;
        }

        .logout-icon {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        .logout-message {
            color: #333;
            font-size: 16px;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .logout-username {
            color: #96715e;
            font-weight: 600;
            font-size: 18px;
            margin: 16px 0 24px 0;
            word-break: break-word;
        }

        .logout-footer {
            display: flex;
            gap: 12px;
            padding: 0 24px 24px 24px;
            justify-content: center;
        }

        .btn-logout-confirm {
            flex: 1;
            max-width: 150px;
            padding: 12px 24px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-logout-confirm:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .btn-logout-confirm:active {
            transform: translateY(0);
        }

        .btn-logout-cancel {
            flex: 1;
            max-width: 150px;
            padding: 12px 24px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-logout-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }

        .btn-logout-cancel:active {
            transform: translateY(0);
        }

        @media (max-width: 480px) {
            .logout-container {
                padding: 10px;
            }

            .logout-header {
                padding: 24px 16px;
            }

            .logout-header h2 {
                font-size: 20px;
            }

            .logout-body {
                padding: 24px 16px;
            }

            .logout-footer {
                flex-direction: column;
                padding: 0 16px 16px 16px;
            }

            .btn-logout-confirm,
            .btn-logout-cancel {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-modal">
            <div class="logout-header">
                <h2>Confirm Logout</h2>
            </div>
            
            <div class="logout-body">
                <span class="logout-icon"></span>
                <p class="logout-message">Are you sure you want to log out?</p>
                <div class="logout-username">
                    Log out as <strong><?php echo htmlspecialchars($username); ?></strong>?
                </div>
            </div>

            <div class="logout-footer">
                <form method="POST" style="flex: 1; max-width: 150px;">
                    <button type="submit" name="confirm_logout" class="btn-logout-confirm">Log Out</button>
                </form>
                <button type="button" class="btn-logout-cancel" onclick="goBack()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function goBack() {
            // Go back to the previous page, or dashboard if no referrer
            const referrer = document.referrer;
            if (referrer && referrer.includes(window.location.hostname)) {
                window.history.back();
            } else {
                window.location.href = '<?php echo SITE_URL; ?>/dashboard.php';
            }
        }
    </script>
</body>
</html>
