<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php';

// If already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    if (has_role('admin')) {
        redirect('dashboard.php');
    } elseif (has_role('cashier')) {
        redirect('pos.php');
    } elseif (has_role('staff')) {
        redirect('staff-dashboard.php');
    }
} else {
    redirect('login.php');
}
?>
