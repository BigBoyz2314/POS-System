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

// Throw mysqli errors as exceptions so our try/catch works reliably
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    // Handle POST requests (add, delete)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['action'])) {
            throw new Exception('Action is required');
        }

        switch ($input['action']) {
            case 'add':
                $vendor_id = intval($input['vendor_id']);
                $purchase_date = $input['purchase_date'];
                $total_amount = floatval($input['total_amount']);
                $payment_method = $input['payment_method'];
                $notes = trim($input['notes']);
                $items = $input['items'];
                
                if ($vendor_id <= 0 || empty($purchase_date) || $total_amount <= 0) {
                    throw new Exception('Please fill all required fields correctly.');
                }
                
                if (!is_array($items) || empty($items)) {
                    throw new Exception('No items provided for purchase.');
                }

                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Insert purchase record
                    $stmt = mysqli_prepare($conn, "INSERT INTO purchases (vendor_id, purchase_date, total_amount, payment_method, notes, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "isdsis", $vendor_id, $purchase_date, $total_amount, $payment_method, $notes, $_SESSION['user_id']);
                    mysqli_stmt_execute($stmt);
                    $purchase_id = mysqli_insert_id($conn);
                    
                    // Process purchase items
                    foreach ($items as $item) {
                        $product_id = isset($item['id']) ? intval($item['id']) : 0;
                        $quantity = isset($item['quantity']) ? intval($item['quantity']) : 0;
                        $cost_price = isset($item['cost_price']) ? floatval($item['cost_price']) : 0.0;

                        if ($product_id <= 0 || $quantity <= 0 || $cost_price < 0) {
                            throw new Exception('Invalid item data in purchase.');
                        }

                        // Insert purchase item
                        $stmt = mysqli_prepare($conn, "INSERT INTO purchase_items (purchase_id, product_id, quantity, cost_price) VALUES (?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "iiid", $purchase_id, $product_id, $quantity, $cost_price);
                        mysqli_stmt_execute($stmt);

                        // Update product stock and cost price
                        $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock + ?, cost_price = ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "idi", $quantity, $cost_price, $product_id);
                        mysqli_stmt_execute($stmt);
                    }
                    
                    mysqli_commit($conn);
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Purchase added successfully! Purchase ID: ' . $purchase_id,
                        'purchase_id' => $purchase_id
                    ]);
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    throw new Exception('Error processing purchase: ' . $e->getMessage());
                }
                break;
                
            case 'delete':
                $id = intval($input['id']);
                
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Get purchase items to reverse stock
                    $stmt = mysqli_prepare($conn, "SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    while ($item = mysqli_fetch_assoc($result)) {
                        // Reverse stock
                        $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "ii", $item['quantity'], $item['product_id']);
                        mysqli_stmt_execute($stmt);
                    }
                    
                    // Delete purchase items
                    $stmt = mysqli_prepare($conn, "DELETE FROM purchase_items WHERE purchase_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    
                    // Delete purchase
                    $stmt = mysqli_prepare($conn, "DELETE FROM purchases WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    
                    mysqli_commit($conn);
                    echo json_encode(['success' => true, 'message' => 'Purchase deleted successfully.']);
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    throw new Exception('Error deleting purchase: ' . $e->getMessage());
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    }
    
    // Handle GET requests (fetch purchases)
    else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = "SELECT p.*, v.name as vendor_name, u.name as user_name 
                  FROM purchases p 
                  LEFT JOIN vendors v ON p.vendor_id = v.id 
                  LEFT JOIN users u ON p.user_id = u.id 
                  ORDER BY p.purchase_date DESC";
        
        $result = mysqli_query($conn, $query);
        if (!$result) {
            throw new Exception("Error fetching purchases: " . mysqli_error($conn));
        }

        $purchases = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $purchases[] = [
                'id' => (int)$row['id'],
                'purchase_date' => $row['purchase_date'],
                'vendor_name' => $row['vendor_name'],
                'total_amount' => (float)$row['total_amount'],
                'payment_method' => $row['payment_method'],
                'user_name' => $row['user_name'],
                'notes' => $row['notes']
            ];
        }

        echo json_encode([
            'success' => true,
            'purchases' => $purchases
        ]);
    }

} catch (Exception $e) {
    error_log("Purchases management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
