<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require admin access
requireAdmin();

header('Content-Type: application/json');

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
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Sale not found']);
        exit;
    }

    $sale = mysqli_fetch_assoc($result);

    // Get sale items
    $stmt = mysqli_prepare($conn, "SELECT si.*, p.name as product_name 
                                   FROM sale_items si 
                                   JOIN products p ON si.product_id = p.id 
                                   WHERE si.sale_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $sale_id);
    mysqli_stmt_execute($stmt);
    $items_result = mysqli_stmt_get_result($stmt);

    $items = [];
    while ($item = mysqli_fetch_assoc($items_result)) {
        $items[] = [
            'name' => $item['product_name'],
            'quantity' => $item['quantity'],
            'price' => $item['price']
        ];
    }

    $sale['items'] = $items;

    echo json_encode(['success' => true, 'sale' => $sale]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
