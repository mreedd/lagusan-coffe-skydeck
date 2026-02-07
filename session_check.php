<?php
session_start();
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

// Check if session has expired (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    redirect('login.php?timeout=1');
}

$_SESSION['last_activity'] = time();
?>
