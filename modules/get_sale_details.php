<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require login access (used by Reports and Sales Returns)
requireLogin();

header('Content-Type: application/json');
@ini_set('display_errors', '0');
ob_start();

// Check database connection
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Debug: Log the request
error_log("get_sale_details.php called with sale_id: " . ($_GET['sale_id'] ?? 'not set'));

if (!isset($_GET['sale_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sale ID is required']);
    exit;
}

$sale_id = intval($_GET['sale_id']);

try {
    // Get sale details
    $stmt = mysqli_prepare($conn, "SELECT s.*, u.name as cashier_name 
                                   FROM sales s 
                                   LEFT JOIN users u ON s.user_id = u.id 
                                   WHERE s.id = ?");
    if (!$stmt) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Sale not found']);
        exit;
    }

    $sale = mysqli_fetch_assoc($result);

    // Get sale items
    $stmt = mysqli_prepare($conn, "SELECT si.*, p.name as product_name, si.tax_rate 
                                   FROM sale_items si 
                                   JOIN products p ON si.product_id = p.id 
                                   WHERE si.sale_id = ?");
    if (!$stmt) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    mysqli_stmt_execute($stmt);
    $items_result = mysqli_stmt_get_result($stmt);

    // Prepare statements to compute already returned quantities
    $sum_by_saleitem = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity),0) AS returned_qty FROM returns WHERE sale_id = ? AND sale_item_id = ?");
    $sum_by_product = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity),0) AS returned_qty FROM returns WHERE sale_id = ? AND sale_item_id IS NULL AND product_id = ?");

    $items = [];
    while ($item = mysqli_fetch_assoc($items_result)) {
        $sale_item_id = isset($item['id']) ? intval($item['id']) : 0;
        $product_id = intval($item['product_id']);
        $sold_qty = floatval($item['quantity']);
        $returned_qty = 0.0;
        if ($sale_item_id > 0 && $sum_by_saleitem) {
            mysqli_stmt_bind_param($sum_by_saleitem, 'ii', $sale_id, $sale_item_id);
            mysqli_stmt_execute($sum_by_saleitem);
            $rres = mysqli_stmt_get_result($sum_by_saleitem);
            $rrow = mysqli_fetch_assoc($rres);
            $returned_qty = floatval($rrow['returned_qty'] ?? 0);
        } elseif ($sum_by_product) {
            mysqli_stmt_bind_param($sum_by_product, 'ii', $sale_id, $product_id);
            mysqli_stmt_execute($sum_by_product);
            $rres = mysqli_stmt_get_result($sum_by_product);
            $rrow = mysqli_fetch_assoc($rres);
            $returned_qty = floatval($rrow['returned_qty'] ?? 0);
        }
        $remaining_qty = max(0, $sold_qty - $returned_qty);

        $items[] = [
            'sale_item_id' => $sale_item_id ?: null,
            'product_id' => $product_id,
            'name' => $item['product_name'],
            'quantity' => $sold_qty,
            'returned_qty' => $returned_qty,
            'remaining_qty' => $remaining_qty,
            'price' => floatval($item['price']),
            'tax_rate' => floatval($item['tax_rate'])
        ];
    }

    $sale['items'] = $items;

    ob_clean();
    echo json_encode(['success' => true, 'sale' => $sale]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
