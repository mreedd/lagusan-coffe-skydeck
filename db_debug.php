<?php
require_once 'config.php';
require_once 'includes/db_connect.php';

// Simple DB debug page to inspect inventory, products, and sale_items relations
// Usage:
//  - db_debug.php?inventory_id=123
//  - db_debug.php?inventory_name=Sugar
//  - db_debug.php?product_id=45
// Outputs results in plain HTML for quick diagnosis.

function print_section($title) {
    echo "<h2>$title</h2>\n";
}

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$inventory_id = isset($_GET['inventory_id']) ? intval($_GET['inventory_id']) : null;
$inventory_name = isset($_GET['inventory_name']) ? $_GET['inventory_name'] : null;
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;

echo "<meta charset=\"utf-8\"><title>DB Debug</title><body style=\"font-family:Segoe UI,Arial;line-height:1.4\">";

if ($inventory_id || $inventory_name) {
    if ($inventory_id) {
        $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->bind_param('i', $inventory_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM inventory WHERE item_name LIKE ? LIMIT 10");
        $like = "%" . $inventory_name . "%";
        $stmt->bind_param('s', $like);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    print_section('Inventory matches');
    if ($res->num_rows === 0) {
        echo "<p>No inventory rows found.</p>";
    } else {
        echo "<table border=1 cellpadding=6 cellspacing=0>";
        echo "<tr><th>id</th><th>item_name</th><th>quantity</th><th>unit</th><th>reorder_level</th><th>updated_at</th></tr>";
        while ($row = $res->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . safe($row['id']) . "</td>";
            echo "<td>" . safe($row['item_name']) . "</td>";
            echo "<td>" . safe($row['quantity']) . "</td>";
            echo "<td>" . safe($row['unit']) . "</td>";
            echo "<td>" . safe($row['reorder_level']) . "</td>";
            echo "<td>" . safe($row['updated_at']) . "</td>";
            echo "</tr>";
            $found_id = $row['id'];
        }
        echo "</table>";

        // Find products that use this inventory via product_ingredients
        if (isset($found_id)) {
            $stmt2 = $conn->prepare("SELECT pi.*, p.id as product_id, p.name as product_name, p.status FROM product_ingredients pi JOIN products p ON pi.product_id = p.id WHERE pi.inventory_id = ?");
            $stmt2->bind_param('i', $found_id);
            $stmt2->execute();
            $r2 = $stmt2->get_result();

            print_section('Products using this inventory (via product_ingredients)');
            if ($r2->num_rows === 0) {
                echo "<p>No products reference this inventory through product_ingredients.</p>";
            } else {
                echo "<table border=1 cellpadding=6 cellspacing=0>";
                echo "<tr><th>product_id</th><th>product_name</th><th>status</th><th>quantity_used</th></tr>";
                while ($p = $r2->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . safe($p['product_id']) . "</td>";
                    echo "<td>" . safe($p['product_name']) . "</td>";
                    echo "<td>" . safe($p['status']) . "</td>";
                    echo "<td>" . safe($p['quantity_used']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

            // Also list products with the same name as inventory (possible data duplication)
            $like = "%" . $row['item_name'] . "%";
            $stmt3 = $conn->prepare("SELECT * FROM products WHERE name LIKE ? LIMIT 50");
            $stmt3->bind_param('s', $like);
            $stmt3->execute();
            $r3 = $stmt3->get_result();

            print_section('Products with similar name');
            if ($r3->num_rows === 0) {
                echo "<p>No products with similar name.</p>";
            } else {
                echo "<table border=1 cellpadding=6 cellspacing=0>";
                echo "<tr><th>id</th><th>name</th><th>category</th><th>price</th><th>status</th></tr>";
                while ($p = $r3->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . safe($p['id']) . "</td>";
                    echo "<td>" . safe($p['name']) . "</td>";
                    echo "<td>" . safe($p['category']) . "</td>";
                    echo "<td>" . safe($p['price']) . "</td>";
                    echo "<td>" . safe($p['status']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
    }
}

if ($product_id) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $res = $stmt->get_result();

    print_section('Product');
    if ($res->num_rows === 0) {
        echo "<p>Product not found.</p>";
    } else {
        $p = $res->fetch_assoc();
        echo "<table border=1 cellpadding=6 cellspacing=0>";
        echo "<tr><th>id</th><th>name</th><th>category</th><th>price</th><th>status</th></tr>";
        echo "<tr>";
        echo "<td>" . safe($p['id']) . "</td>";
        echo "<td>" . safe($p['name']) . "</td>";
        echo "<td>" . safe($p['category']) . "</td>";
        echo "<td>" . safe($p['price']) . "</td>";
        echo "<td>" . safe($p['status']) . "</td>";
        echo "</tr>";
        echo "</table>";

        // show if this product exists in sale_items
        $stmt2 = $conn->prepare("SELECT si.*, s.order_number, s.created_at FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.product_id = ? ORDER BY s.created_at DESC LIMIT 50");
        $stmt2->bind_param('i', $product_id);
        $stmt2->execute();
        $r2 = $stmt2->get_result();

        print_section('Recent sale_items for this product_id');
        if ($r2->num_rows === 0) {
            echo "<p>No sale_items referencing this product_id.</p>";
        } else {
            echo "<table border=1 cellpadding=6 cellspacing=0>";
            echo "<tr><th>sale_id</th><th>product_id</th><th>product_name</th><th>qty</th><th>price</th><th>order_number</th><th>created_at</th></tr>";
            while ($r = $r2->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . safe($r['sale_id']) . "</td>";
                echo "<td>" . safe($r['product_id']) . "</td>";
                echo "<td>" . safe($r['product_name']) . "</td>";
                echo "<td>" . safe($r['quantity']) . "</td>";
                echo "<td>" . safe($r['price']) . "</td>";
                echo "<td>" . safe($r['order_number']) . "</td>";
                echo "<td>" . safe($r['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
}

print_section('Notes');
echo "<ul>";
echo "<li>POS reads from <code>products</code> table (only products with status 'available' are shown).</li>";
echo "<li>Inventory items live in <code>inventory</code> table and are ingredients — they don't automatically appear in POS unless there is a corresponding <code>products</code> entry.</li>";
echo "<li>If you expect inventory to show in POS, create a product entry in <code>products</code> and (optionally) link ingredients in <code>product_ingredients</code>.</li>";
echo "</ul>";

echo "</body>";

?>