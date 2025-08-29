<?php
session_start();
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
        // Create returns table if not exists
        $create_returns = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS returns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            sale_item_id INT NULL,
            product_id INT NOT NULL,
            quantity DECIMAL(10,2) NOT NULL,
            reason VARCHAR(255) NOT NULL,
            refund_amount DECIMAL(10,2) NOT NULL,
            user_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        if (!$create_returns) {
            throw new Exception("Error creating returns table: " . mysqli_error($conn));
        }

        // Create return_receipts table if not exists
        $create_receipts = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS return_receipts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            payload LONGTEXT NULL,
            total_refund DECIMAL(10,2) NOT NULL,
            cash_refund DECIMAL(10,2) NOT NULL,
            card_refund DECIMAL(10,2) NOT NULL,
            user_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        if (!$create_receipts) {
            throw new Exception("Error creating return_receipts table: " . mysqli_error($conn));
        }

        $where = '';
        if (isset($_GET['sale_id']) && intval($_GET['sale_id']) > 0) {
            $sale_id = intval($_GET['sale_id']);
            $where = 'WHERE r.sale_id = ' . $sale_id;
        }

        // Simplified query without sale_items dependency
        $sql = "SELECT r.id, r.sale_id, r.product_id, p.name AS product_name, r.quantity, r.reason, r.refund_amount, r.created_at
                FROM returns r 
                LEFT JOIN products p ON r.product_id = p.id
                $where
                ORDER BY r.sale_id DESC, r.id DESC LIMIT 100";
        
        $result = mysqli_query($conn, $sql);
        
        if (!$result) {
            throw new Exception("Error fetching returns: " . mysqli_error($conn));
        }

        $returns = [];
        $sales = [];
        
        // Check if there are any returns
        if (mysqli_num_rows($result) === 0) {
            echo json_encode([
                'success' => true,
                'returns' => [],
                'sales' => [],
                'receipts' => []
            ]);
            exit();
        }
        
        while ($row = mysqli_fetch_assoc($result)) {
            $return_item = [
                'id' => (int)$row['id'],
                'sale_id' => (int)$row['sale_id'],
                'product_id' => (int)$row['product_id'],
                'product_name' => $row['product_name'] ?? 'Unknown Product',
                'quantity' => (float)$row['quantity'],
                'reason' => $row['reason'],
                'refund_amount' => (float)$row['refund_amount'],
                'price' => 0, // Default to 0 since we don't have sale_items
                'total_price' => 0, // Default to 0 since we don't have sale_items
                'created_at' => $row['created_at']
            ];
            
            $returns[] = $return_item;
            
            // Group by sale_id
            $sale_id = (int)$row['sale_id'];
            if (!isset($sales[$sale_id])) {
                $sales[$sale_id] = [
                    'sale_id' => $sale_id,
                    'total_refund' => 0,
                    'items' => [],
                    'created_at' => $row['created_at']
                ];
            }
            $sales[$sale_id]['items'][] = $return_item;
            $sales[$sale_id]['total_refund'] += (float)$row['refund_amount'];
        }
        
        // Convert sales array to indexed array
        $sales_list = array_values($sales);

        // Fetch receipt IDs for reprint mapping
        $receipts = [];
        $receipt_result = mysqli_query($conn, "SELECT id, sale_id FROM return_receipts ORDER BY id DESC LIMIT 500");
        if ($receipt_result) {
            while ($receipt_row = mysqli_fetch_assoc($receipt_result)) {
                $receipts[(int)$receipt_row['sale_id']] = (int)$receipt_row['id'];
            }
        }

        echo json_encode([
            'success' => true,
            'returns' => $returns,
            'sales' => $sales_list,
            'receipts' => $receipts
        ]);
    }

} catch (Exception $e) {
    error_log("List returns error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
