<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <?php
    // Cache-busting using file modification time; falls back to no query if file not found.
    function css_with_mtime($path) {
        $full = __DIR__ . '/..' . parse_url($path, PHP_URL_PATH);
        if (file_exists($full)) {
            return $path . '?v=' . filemtime($full);
        }
        return $path;
    }
    function img_with_mtime($path) {
        $full = __DIR__ . '/..' . parse_url($path, PHP_URL_PATH);
        if (file_exists($full)) {
            return $path . '?v=' . filemtime($full);
        }
        return $path;
    }
    ?>
    <link rel="stylesheet" href="<?php echo css_with_mtime(SITE_URL . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo css_with_mtime(SITE_URL . '/assets/css/dashboard.css'); ?>">
    <link rel="stylesheet" href="<?php echo css_with_mtime(SITE_URL . '/assets/css/pos.css'); ?>">
    <!-- Added inventory CSS -->
    <link rel="stylesheet" href="<?php echo css_with_mtime(SITE_URL . '/assets/css/inventory.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Inline fallback for navbar logo sizing (applies immediately even if external CSS is cached) -->
    <style>
        .navbar-logo img { display: block; height: 100px; width: auto; max-width: 220px; padding-bottom: 60px; }
        .brand-link { height: 60px; display: flex; align-items: center; }
        .top-navbar { overflow: hidden; }
        .navbar-user {
  display: flex;
  align-items: center;
  gap: 12px;
  color: var(--white);
  font-size: 14px;
}
.top-navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: #b5a394;
  border-bottom: 1px solid var(--primary);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  z-index: 100;
  overflow: hidden; /* ensure any oversized logo doesn't spill out */
}
    </style>
</head>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<body>
    <div class="dashboard-wrapper">
        <nav class="top-navbar">
            <div class="navbar-brand">
                <a href="<?php echo SITE_URL; ?>/" class="brand-link" aria-label="<?php echo SITE_NAME; ?>">
                    <div class="navbar-logo">
                        <img src="<?php echo img_with_mtime(SITE_URL . '/images/lagusanskydeck-logo.png'); ?>" alt="<?php echo SITE_NAME; ?>">
                    </div>
                </a>
            </div>
            <div class="navbar-user">
                <span>Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <span class="user-role">(<?php echo ucfirst($_SESSION['role']); ?>)</span>
                <a href="<?php echo SITE_URL; ?>/logout.php" class="btn btn-sm btn-logout">Logout</a>
            </div>
        </nav>

    
</body>