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
        $purchase_id = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;
        
        if ($purchase_id <= 0) {
            throw new Exception('Invalid purchase ID');
        }

        // Get purchase details
        $query = "SELECT p.*, v.name as vendor_name, u.name as user_name 
                  FROM purchases p 
                  LEFT JOIN vendors v ON p.vendor_id = v.id 
                  LEFT JOIN users u ON p.user_id = u.id 
                  WHERE p.id = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $purchase_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result || mysqli_num_rows($result) === 0) {
            throw new Exception('Purchase not found');
        }

        $purchase = mysqli_fetch_assoc($result);

        // Get purchase items
        $items_query = "SELECT pi.*, p.name as product_name 
                       FROM purchase_items pi 
                       LEFT JOIN products p ON pi.product_id = p.id 
                       WHERE pi.purchase_id = ?";
        
        $stmt = mysqli_prepare($conn, $items_query);
        mysqli_stmt_bind_param($stmt, "i", $purchase_id);
        mysqli_stmt_execute($stmt);
        $items_result = mysqli_stmt_get_result($stmt);

        $items = [];
        while ($item = mysqli_fetch_assoc($items_result)) {
            $items[] = [
                'id' => (int)$item['id'],
                'product_name' => $item['product_name'],
                'quantity' => (int)$item['quantity'],
                'cost_price' => (float)$item['cost_price']
            ];
        }

        $purchase_data = [
            'id' => (int)$purchase['id'],
            'purchase_date' => $purchase['purchase_date'],
            'vendor_name' => $purchase['vendor_name'],
            'total_amount' => (float)$purchase['total_amount'],
            'payment_method' => $purchase['payment_method'],
            'user_name' => $purchase['user_name'],
            'notes' => $purchase['notes'],
            'items' => $items
        ];

        echo json_encode([
            'success' => true,
            'purchase' => $purchase_data
        ]);
    }

} catch (Exception $e) {
    error_log("Purchase details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
