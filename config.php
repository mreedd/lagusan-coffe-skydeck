<?php
// Global Configuration (supports environment variables for containerized deployments)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'lagusan_coffee_db');

define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/lagusan-coffee-skydeck');
define('SITE_NAME', 'Lagusan Coffee Skydeck');

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting: enable in development, disable in production by setting ENV SHOW_ERRORS=0
$showErrors = getenv('SHOW_ERRORS');
if ($showErrors === false) {
	$showErrors = '1';
}
if ($showErrors === '1' || $showErrors === 'true') {
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
} else {
	error_reporting(0);
	ini_set('display_errors', 0);
}
?>
