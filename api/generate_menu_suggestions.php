<?php
require_once '../config.php';
require_once '../includes/db_connect.php';
require_once '../session_check.php';

header('Content-Type: application/json');

// Check if user is admin
if (!has_role('admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get and validate inputs
$period = intval($_GET['period'] ?? 30);
$category = $_GET['category'] ?? 'all';

// Optional: whitelist categories if you have a known set, otherwise keep 'all' or pass through validation

// Get top performers
// Build base query and bind parameters dynamically
$base_query = "
    SELECT p.name, SUM(si.quantity) as total_sales
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    AND s.status = 'completed'
";

$params = [];
$types = '';
$types .= 'i'; // for period
$params[] = $period;

if ($category !== 'all') {
    $base_query .= " AND p.category = ?";
    $types .= 's';
    $params[] = $category;
}

$base_query .= " GROUP BY p.id ORDER BY total_sales DESC LIMIT 5";

$stmt = $conn->prepare($base_query);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    exit;
}

if (!empty($params)) {
    // Use call_user_func_array to bind params dynamically
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

$stmt->execute();
$top_performers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get underperformers
// Underperformers query (similar dynamic binding)
$base_query = "
    SELECT p.name, COALESCE(SUM(si.quantity), 0) as total_sales
    FROM products p
    LEFT JOIN sale_items si ON p.id = si.product_id
    LEFT JOIN sales s ON si.sale_id = s.id AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    WHERE p.status = 'available'
";

$params = [];
$types = '';
$types .= 'i';
$params[] = $period;

if ($category !== 'all') {
    $base_query .= " AND p.category = ?";
    $types .= 's';
    $params[] = $category;
}

$base_query .= " GROUP BY p.id ORDER BY total_sales ASC LIMIT 5";

$stmt = $conn->prepare($base_query);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
    exit;
}

if (!empty($params)) {
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bindU' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

$stmt->execute();
$underperformers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Generate AI suggestions
$suggestions = [];

// Suggestion 1: Promote top performers
if (count($top_performers) > 0) {
    $suggestions[] = [
        'title' => 'Feature Your Best Sellers',
        'description' => 'Your top-selling items (' . implode(', ', array_column(array_slice($top_performers, 0, 3), 'name')) . ') are performing exceptionally well. Consider creating combo deals or highlighting them in your menu.',
        'priority' => 'High',
        'impact' => 'Revenue Boost'
    ];
}

// Suggestion 2: Address underperformers
if (count($underperformers) > 0) {
    $suggestions[] = [
        'title' => 'Review Low-Performing Items',
        'description' => 'Items like ' . implode(', ', array_column(array_slice($underperformers, 0, 3), 'name')) . ' have low sales. Consider promotional pricing, recipe improvements, or menu optimization.',
        'priority' => 'Medium',
        'impact' => 'Cost Reduction'
    ];
}

// Suggestion 3: Inventory-based suggestions
$inventory_query = "
    SELECT DISTINCT i.item_name, i.quantity, p.name as product_name, p.category
    FROM inventory i
    LEFT JOIN product_ingredients pi ON i.id = pi.inventory_id
    LEFT JOIN products p ON pi.product_id = p.id AND p.status = 'available'
    WHERE i.quantity > 0
    ORDER BY i.quantity DESC
    LIMIT 10
";

$inventory_result = $conn->query($inventory_query);
$inventory_items = $inventory_result->fetch_all(MYSQLI_ASSOC);

$ingredient_suggestions = [];
$product_promotions = [];

foreach ($inventory_items as $item) {
    if (!empty($item['product_name'])) {
        // Promote products that use this ingredient
        $product_promotions[] = $item['product_name'] . ' (uses ' . $item['item_name'] . ')';
    } else {
        // Suggest new products based on ingredient
        $ingredient_suggestions[] = $item['item_name'];
    }
}

if (!empty($product_promotions)) {
    $highlighted_products = array_map(function($item) {
        // Extract product name before (uses ...)
        $parts = explode(' (uses ', $item);
        $product_name = $parts[0];
        $ingredient = str_replace(')', '', $parts[1] ?? '');
        return '<strong>' . htmlspecialchars($product_name) . '</strong> (uses ' . htmlspecialchars($ingredient) . ')';
    }, array_unique($product_promotions));
    $suggestions[] = [
        'title' => 'Promote Inventory-Based Products',
        'description' => 'Highlight products that utilize your available ingredients: ' . implode(', ', $highlighted_products) . '. These items can be featured prominently to utilize current stock.',
        'priority' => 'High',
        'impact' => 'Inventory Utilization'
    ];
}

if (!empty($ingredient_suggestions)) {
    $highlighted_ingredients = array_map(function($ingredient) {
        return '<strong>' . htmlspecialchars($ingredient) . '</strong>';
    }, array_unique($ingredient_suggestions));
    $suggestions[] = [
        'title' => 'Create New Menu Items',
        'description' => 'Consider creating new products using available ingredients like ' . implode(', ', $highlighted_ingredients) . '. For example, if you have <strong>matcha powder</strong>, create a matcha latte or matcha coffee.',
        'priority' => 'Medium',
        'impact' => 'Menu Expansion'
    ];
}

// Suggestion 4: Seasonal recommendations
$current_month = date('n');
$seasonal_suggestion = '';

if ($current_month >= 12 || $current_month <= 2) {
    $seasonal_suggestion = 'Winter season: Consider adding hot chocolate variations, warm pastries, and comfort food items.';
} elseif ($current_month >= 3 && $current_month <= 5) {
    $seasonal_suggestion = 'Spring season: Fresh fruit beverages, light salads, and refreshing iced drinks could attract customers.';
} elseif ($current_month >= 6 && $current_month <= 8) {
    $seasonal_suggestion = 'Summer season: Focus on cold beverages, iced coffee variations, and light meals.';
} else {
    $seasonal_suggestion = 'Fall season: Pumpkin spice items, warm beverages, and hearty meals are popular choices.';
}

$suggestions[] = [
    'title' => 'Seasonal Menu Optimization',
    'description' => $seasonal_suggestion,
    'priority' => 'Medium',
    'impact' => 'Customer Satisfaction'
];

// Suggestion 5: Combo deals
$suggestions[] = [
    'title' => 'Create Value Combos',
    'description' => 'Bundle popular items together at a slight discount. For example, pair your best-selling coffee with a pastry to increase average transaction value.',
    'priority' => 'High',
    'impact' => 'Revenue Boost'
];

echo json_encode([
    'success' => true,
    'insights' => [
        'top_performers' => array_map(function($item) {
            return ['name' => $item['name'], 'sales' => $item['total_sales']];
        }, $top_performers),
        'underperformers' => array_map(function($item) {
            return ['name' => $item['name'], 'sales' => $item['total_sales']];
        }, $underperformers),
        'combinations' => '<p>Customers who buy coffee often purchase pastries. Consider creating a "Coffee & Pastry" combo.</p>',
        'seasonal' => '<p>' . $seasonal_suggestion . '</p>'
    ],
    'suggestions' => $suggestions
]);
