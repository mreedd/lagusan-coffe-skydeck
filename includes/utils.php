<?php
// Utility functions shared across includes

// Generic sanitize for output/HTML contexts
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// DB-aware sanitizer: uses mysqli real_escape_string if $conn is available
function db_sanitize($data) {
    $data = sanitize_input($data);
    if (isset($GLOBALS['conn']) && is_object($GLOBALS['conn'])) {
        return $GLOBALS['conn']->real_escape_string($data);
    }
    return $data;
}

// Safe HTML escape that tolerates nulls/non-strings
function safe_html($value) {
    if ($value === null) return '';
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
