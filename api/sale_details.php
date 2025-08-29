<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sale_id = isset($_GET['sale_id']) ? intval($_GET['sale_id']) : 0;
        
        if ($sale_id <= 0) {
            throw new Exception('Invalid sale ID');
        }

        // Get sale details
        $query = "SELECT s.*, u.name as cashier_name 
                  FROM sales s 
                  LEFT JOIN users u ON s.user_id = u.id 
                  WHERE s.id = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $sale_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            throw new Exception('Sale not found');
        }

        $sale = mysqli_fetch_assoc($result);

        // Get sale items
        $items_query = "SELECT si.*, p.name as product_name 
                       FROM sale_items si 
                       LEFT JOIN products p ON si.product_id = p.id 
                       WHERE si.sale_id = ?";
        
        $stmt = mysqli_prepare($conn, $items_query);
        mysqli_stmt_bind_param($stmt, "i", $sale_id);
        mysqli_stmt_execute($stmt);
        $items_result = mysqli_stmt_get_result($stmt);

        $items = [];
        while ($item = mysqli_fetch_assoc($items_result)) {
            $items[] = [
                'id' => (int)$item['id'],
                'product_id' => (int)$item['product_id'],
                'product_name' => $item['product_name'],
                'quantity' => (int)$item['quantity'],
                'price' => (float)$item['price'],
                'tax_rate' => (float)$item['tax_rate']
            ];
        }

        $sale_data = [
            'id' => (int)$sale['id'],
            'date' => $sale['date'],
            'total_amount' => (float)$sale['total_amount'],
            'discount_amount' => (float)$sale['discount_amount'],
            'tax_amount' => (float)($sale['tax_amount'] ?? 0),
            'subtotal_amount' => (float)($sale['subtotal_amount'] ?? $sale['total_amount']),
            'cash_amount' => (float)($sale['cash_amount'] ?? 0),
            'card_amount' => (float)($sale['card_amount'] ?? 0),
            'payment_method' => $sale['payment_method'],
            'cashier_name' => $sale['cashier_name'],
            'items' => $items
        ];

        echo json_encode([
            'success' => true,
            'sale' => $sale_data
        ]);
    }

} catch (Exception $e) {
    error_log("Sale details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
