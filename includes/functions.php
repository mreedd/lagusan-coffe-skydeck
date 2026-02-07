<?php
require_once __DIR__ . '/utils.php';
// Utility Functions

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper function to redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Helper function to check user role
function has_role($required_role) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    if ($required_role === 'admin' && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    if ($required_role === 'staff' && in_array($_SESSION['role'], ['admin', 'staff'])) {
        return true;
    }
    
    if ($required_role === 'cashier' && in_array($_SESSION['role'], ['admin', 'staff', 'cashier'])) {
        return true;
    }
    
    return false;
}



// Format currency
function format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

// Format date
function format_date($date) {
    return date('M d, Y', strtotime($date));
}

// Format datetime
function format_datetime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

// Generate random string
function generate_random_string($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

// Calculate percentage change
function calculate_percentage_change($old_value, $new_value) {
    if ($old_value == 0) return 0;
    return (($new_value - $old_value) / $old_value) * 100;
}

// Get user full name
function get_user_fullname($user_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    return $user ? $user['full_name'] : 'Unknown';
}

// (Sanitization moved to includes/utils.php)

// Generate unique order number
function generate_order_number() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Unit conversion functions
function convert_to_base_unit($quantity, $unit) {
    // Convert quantity to base unit (grams for weight, ml for volume, pcs for count)
    $u = strtolower($unit);
    // Weight units -> base = grams
    $weight_units = ['kg','g','mg','oz'];
    // Volume units -> base = milliliters
    $volume_units = ['l','ml','cl','oz'];

    if (in_array($u, $weight_units)) {
        switch ($u) {
            case 'kg': return $quantity * 1000.0; // kg to g
            case 'mg': return $quantity / 1000.0; // mg to g
            case 'oz': return $quantity * 28.349523125; // oz to g
            case 'g':
            default: return $quantity;
        }
    }

    if (in_array($u, $volume_units)) {
        switch ($u) {
            case 'l': return $quantity * 1000.0; // l to ml
            case 'cl': return $quantity * 10.0; // cl to ml
            case 'oz': return $quantity * 29.5735295625; // fl oz to ml
            case 'ml':
            default: return $quantity;
        }
    }

    // Count or unknown units
    if (in_array($u, ['pcs','pieces'])) {
        return $quantity;
    }

    // Unknown unit: return as-is
    return $quantity;
}

function convert_from_base_unit($quantity, $unit) {
    $u = strtolower($unit);
    // Weight units
    if (in_array($u, ['kg','g','mg','oz'])) {
        switch ($u) {
            case 'kg': return $quantity / 1000.0; // g to kg
            case 'mg': return $quantity * 1000.0; // g to mg
            case 'oz': return $quantity / 28.349523125; // g to oz
            case 'g':
            default: return $quantity;
        }
    }

    // Volume units
    if (in_array($u, ['l','ml','cl','oz'])) {
        switch ($u) {
            case 'l': return $quantity / 1000.0; // ml to l
            case 'cl': return $quantity / 10.0; // ml to cl
            case 'oz': return $quantity / 29.5735295625; // ml to fl oz
            case 'ml':
            default: return $quantity;
        }
    }

    if (in_array($u, ['pcs','pieces'])) {
        return $quantity;
    }

    return $quantity;
}

function convert_unit($quantity, $from_unit, $to_unit) {
    // Convert quantity from one unit to another
    $from = strtolower($from_unit);
    $to = strtolower($to_unit);

    // If units are identical or empty, return original
    if ($from === $to || $from === '' || $to === '') {
        return $quantity;
    }

    // Determine whether to treat 'oz' as weight or volume based on from/to units
    $weight_set = ['kg','g','mg','oz'];
    $volume_set = ['l','ml','cl','oz'];

    $category = null;
    if (in_array($from, $volume_set) || in_array($to, $volume_set)) {
        $category = 'volume';
    } elseif (in_array($from, $weight_set) || in_array($to, $weight_set)) {
        $category = 'weight';
    } elseif (in_array($from, ['pcs','pieces']) || in_array($to, ['pcs','pieces'])) {
        $category = 'count';
    }

    // Convert using appropriate base mapping
    if ($category === 'weight') {
        // Convert from from_unit to grams, then grams to to_unit
        // Use convert_to_base_unit (interprets oz as weight) and convert_from_base_unit
        $base = convert_to_base_unit($quantity, $from);
        return convert_from_base_unit($base, $to);
    }

    if ($category === 'volume') {
        // Convert using volume mapping (interpret oz as fl oz)
        // Temporarily map units to volume-friendly values by calling convert_to_base_unit
        $base = convert_to_base_unit($quantity, $from);
        return convert_from_base_unit($base, $to);
    }

    // Fallback: try direct conversion
    $base = convert_to_base_unit($quantity, $from);
    return convert_from_base_unit($base, $to);
}
?>
