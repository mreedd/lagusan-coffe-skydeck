<?php
require_once '../session_check.php';
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$inventory_id = $_POST['inventory_id'] ?? null;
$quantity = $_POST['quantity'] ?? null;
$input_unit = $_POST['unit'] ?? null; // e.g., 'g', 'ml', 'oz', 'pcs'
$reason = $_POST['reason'] ?? null;
$notes = $_POST['notes'] ?? '';

// Validate input
if (!$inventory_id || !$quantity || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get current inventory item
    $stmt = $conn->prepare("SELECT quantity, unit FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();

    if (!$item) {
        throw new Exception('Inventory item not found');
    }

    // Convert quantity to inventory unit (assuming input is in base units: g for weight, ml for volume, pcs for count)
    $inventory_quantity = $quantity;
        // Convert quantity to inventory unit using convert_unit (handles oz etc.)
        $from_unit = $input_unit ? $input_unit : null;
        if (!$from_unit) {
            // Fallback: assume base units based on inventory unit
            $inv = strtolower($item['unit']);
            if (in_array($inv, ['kg','g','mg'])) $from_unit = 'g';
            elseif (in_array($inv, ['l','ml','cl'])) $from_unit = 'ml';
            elseif (in_array($inv, ['pcs','pieces'])) $from_unit = 'pcs';
            else $from_unit = $inv;
        }

        $inventory_quantity = (float) convert_unit((float) $quantity, $from_unit, $item['unit']);
    // For 'g', 'ml', 'pcs', no conversion needed

    // Check if quantity is valid
    if ($inventory_quantity > $item['quantity']) {
        throw new Exception('Wastage quantity cannot exceed current stock');
    }

    // Get cost per unit for cost calculation
    $stmt = $conn->prepare("SELECT cost_per_unit FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $cost_result = $stmt->get_result();
    $cost_item = $cost_result->fetch_assoc();
    $cost_per_unit = $cost_item['cost_per_unit'];

    // Calculate total cost of wastage (use inventory_quantity for cost calculation if unit conversion affects cost)
    $total_wastage_cost = $inventory_quantity * $cost_per_unit;

    // Insert wastage record with cost (store original input quantity)
    $stmt = $conn->prepare("
            INSERT INTO wastage_log (inventory_id, quantity, reason, notes, recorded_by, recorded_at, cost_per_unit, total_cost, input_unit) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)
    ");
        $stmt->bind_param("idssidds", $inventory_id, $quantity, $reason, $notes, $_SESSION['user_id'], $cost_per_unit, $total_wastage_cost, $input_unit);
        // Some older schemas may not have input_unit column; if the prepare fails, fallback to previous insert without input_unit
        if ($stmt === false) {
            $stmt2 = $conn->prepare(
                "INSERT INTO wastage_log (inventory_id, quantity, reason, notes, recorded_by, recorded_at, cost_per_unit, total_cost) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)"
            );
            $stmt2->bind_param("idssidd", $inventory_id, $quantity, $reason, $notes, $_SESSION['user_id'], $cost_per_unit, $total_wastage_cost);
            $stmt2->execute();
        } else {
            $stmt->execute();
        }

    // Update inventory quantity
    $new_quantity = $item['quantity'] - $inventory_quantity;
    $stmt = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
    $stmt->bind_param("di", $new_quantity, $inventory_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Wastage recorded successfully']);
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
