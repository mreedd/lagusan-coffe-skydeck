<aside class="sidebar">
    <div class="sidebar-menu">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="<?php echo SITE_URL; ?>/dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <span>📊</span> Dashboard
            </a>
            <a href="<?php echo SITE_URL; ?>/pos.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pos.php' ? 'active' : ''; ?>">
                <span>🛒</span> POS
            </a>
            <a href="<?php echo SITE_URL; ?>/orders.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">
                <span>📋</span> Orders
            </a>
            <a href="<?php echo SITE_URL; ?>/inventory.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">
                <span>📦</span> Inventory
            </a>

            <a href="<?php echo SITE_URL; ?>/products.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : ''; ?>">
                <span>☕</span> Products
            </a>
              <a href="<?php echo SITE_URL; ?>/users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <span>👥</span> Users
            </a>
            <a href="<?php echo SITE_URL; ?>/reports.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                <span>📈</span> Reports
            </a>
            <a href="<?php echo SITE_URL; ?>/forecast.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'forecast.php' ? 'active' : ''; ?>">
                <span>📈</span> Forecast
            </a>
            <a href="<?php echo SITE_URL; ?>/menu_suggestions.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'menu_suggestions.php' ? 'active' : ''; ?>">
                <span>🤖</span> Menu Generation
            </a>


        <?php elseif ($_SESSION['role'] === 'staff'): ?>
            <a href="<?php echo SITE_URL; ?>/staff-dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'staff-dashboard.php' ? 'active' : ''; ?>">
                <span>📊</span> Dashboard
            </a>
            <a href="<?php echo SITE_URL; ?>/inventory.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">
                <span>📦</span> Inventory
            </a>
            <a href="<?php echo SITE_URL; ?>/inventory.php?tab=low-stock" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) === 'inventory.php' && ($_GET['tab'] ?? '') === 'low-stock') ? 'active' : ''; ?>">
                <span>⚠️</span> Low Stock Alerts
            </a>
            <a href="<?php echo SITE_URL; ?>/inventory.php?tab=reorder" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) === 'inventory.php' && ($_GET['tab'] ?? '') === 'reorder') ? 'active' : ''; ?>">
                <span>🔄</span> Reorder List
            </a>
        <?php elseif ($_SESSION['role'] === 'cashier'): ?>
            <a href="<?php echo SITE_URL; ?>/pos.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pos.php' ? 'active' : ''; ?>">
                <span>🛒</span> POS
            </a>
            <a href="<?php echo SITE_URL; ?>/current_orders.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'current_orders.php' ? 'active' : ''; ?>">
                <span>📋</span> Order History
            </a>
        <?php endif; ?>
    </div>
</aside>
