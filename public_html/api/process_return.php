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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $sale_id = intval($input['sale_id'] ?? 0);
        $items = $input['items'] ?? [];
        $refund_method = $input['refund_method'] ?? 'cash';
        $refund_cash = floatval($input['refund_cash'] ?? 0);
        $refund_card = floatval($input['refund_card'] ?? 0);
        
        if (!$sale_id || empty($items)) {
            throw new Exception('Sale ID and items are required');
        }
        
        // Create returns table if not exists
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS returns (
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

        // Create return_receipts table if not exists
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS return_receipts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            payload LONGTEXT NULL,
            total_refund DECIMAL(10,2) NOT NULL,
            cash_refund DECIMAL(10,2) NOT NULL,
            card_refund DECIMAL(10,2) NOT NULL,
            user_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            $total_refund = 0;
            $return_items = [];
            
            // Process each return item
            foreach ($items as $item) {
                $sale_item_id = intval($item['sale_item_id'] ?? 0);
                $product_id = intval($item['product_id'] ?? 0);
                $quantity = floatval($item['quantity'] ?? 0);
                $reason = $item['reason'] ?? '';
                
                if ($quantity <= 0) continue;
                
                // Check if trying to return more than sold
                if ($sale_item_id > 0) {
                    $stmt = mysqli_prepare($conn, "SELECT quantity FROM sale_items WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $sale_item_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $sale_item = mysqli_fetch_assoc($result);
                    
                    if ($sale_item) {
                        $sold_qty = floatval($sale_item['quantity']);
                        
                        // Check already returned quantity
                        $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity), 0) as returned_qty FROM returns WHERE sale_item_id = ?");
                        mysqli_stmt_bind_param($stmt, "i", $sale_item_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        $return_data = mysqli_fetch_assoc($result);
                        $already_returned = floatval($return_data['returned_qty']);
                        
                        $available_to_return = $sold_qty - $already_returned;
                        
                        if ($quantity > $available_to_return) {
                            throw new Exception("Cannot return $quantity items. Only $available_to_return items available to return for this item.");
                        }
                    }
                }
                
                // Get product name
                $stmt = mysqli_prepare($conn, "SELECT name FROM products WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $product = mysqli_fetch_assoc($result);
                $product_name = $product ? $product['name'] : 'Unknown Product';
                
                // Debug: log if product not found
                if (!$product) {
                    error_log("Product not found for ID: $product_id");
                }
                
                // Calculate refund amount based on sale item price
                $price = 0;
                if ($sale_item_id > 0) {
                    $stmt = mysqli_prepare($conn, "SELECT price FROM sale_items WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $sale_item_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $sale_item = mysqli_fetch_assoc($result);
                    if ($sale_item) {
                        $price = floatval($sale_item['price']);
                    }
                }
                
                // If no price found, try to get from products table
                if ($price <= 0) {
                    $stmt = mysqli_prepare($conn, "SELECT price FROM products WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $product_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $product = mysqli_fetch_assoc($result);
                    if ($product) {
                        $price = floatval($product['price']);
                    }
                }
                
                $refund_amount = $price * $quantity;
                $total_refund += $refund_amount;
                
                // Insert return record
                $stmt = mysqli_prepare($conn, "INSERT INTO returns (sale_id, sale_item_id, product_id, quantity, reason, refund_amount, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $user_id = $_SESSION['user_id'] ?? 1;
                mysqli_stmt_bind_param($stmt, "iiidsdi", $sale_id, $sale_item_id, $product_id, $quantity, $reason, $refund_amount, $user_id);
                mysqli_stmt_execute($stmt);
                
                // Update product stock
                $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock + ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "di", $quantity, $product_id);
                mysqli_stmt_execute($stmt);
                
                // Add to return items for receipt
                $return_items[] = [
                    'product_name' => $product_name,
                    'return_qty' => $quantity,
                    'quantity' => $quantity,
                    'price' => $price,
                    'refund_amount' => $refund_amount,
                    'reason' => $reason
                ];
            }
            
            // Validate refund amounts
            if ($refund_method === 'cash' && $refund_cash < $total_refund) {
                throw new Exception('Cash refund amount is insufficient');
            }
            if ($refund_method === 'card' && $refund_card < $total_refund) {
                throw new Exception('Card refund amount is insufficient');
            }
            if ($refund_method === 'mixed' && ($refund_cash + $refund_card) < $total_refund) {
                throw new Exception('Total refund amount is insufficient');
            }
            
            // Create return receipt
            $receipt_payload = json_encode([
                'items' => $return_items
            ]);
            
            $stmt = mysqli_prepare($conn, "INSERT INTO return_receipts (sale_id, payload, total_refund, cash_refund, card_refund, user_id) VALUES (?, ?, ?, ?, ?, ?)");
            $user_id = $_SESSION['user_id'] ?? 1;
            mysqli_stmt_bind_param($stmt, "isdddi", $sale_id, $receipt_payload, $total_refund, $refund_cash, $refund_card, $user_id);
            mysqli_stmt_execute($stmt);
            
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'message' => 'Return processed successfully',
                'total_refund' => $total_refund,
                'receipt_id' => mysqli_insert_id($conn)
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            throw $e;
        }
    } else {
        throw new Exception('Only POST method allowed');
    }
    
} catch (Exception $e) {
    error_log("Process return error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
