<?php
// CORS headers for React development
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require login
requireLogin();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle sale creation
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $cart_items = $input['items'] ?? [];
        $subtotal_amount = floatval($input['subtotal_amount'] ?? 0);
        $tax_amount = floatval($input['tax_amount'] ?? 0);
        $total_amount = floatval($input['total'] ?? 0);
        $discount_amount = floatval($input['discount_amount'] ?? 0);
        $payment_method = $input['payment_method'] ?? 'cash';
        $cash_amount = floatval($input['cash_amount'] ?? 0);
        $card_amount = floatval($input['card_amount'] ?? 0);
        
        if (empty($cart_items)) {
            throw new Exception('Cart is empty');
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert sale record
            $stmt = mysqli_prepare($conn, "INSERT INTO sales (total_amount, subtotal_amount, tax_amount, discount_amount, payment_method, cash_amount, card_amount, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare sales insert: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "ddddssdi", $total_amount, $subtotal_amount, $tax_amount, $discount_amount, $payment_method, $cash_amount, $card_amount, $_SESSION['user_id']);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to insert sale: " . mysqli_stmt_error($stmt));
            }
            
            $sale_id = mysqli_insert_id($conn);
            
            // Insert sale items and update stock
            foreach ($cart_items as $item) {
                $item_total = $item['price'] * $item['quantity'];
                $item_subtotal = $item['tax_rate'] > 0 ? ($item_total / (1 + $item['tax_rate'] / 100)) : $item_total;
                $item_tax = $item_total - $item_subtotal;
                
                // Insert sale item
                $stmt = mysqli_prepare($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, price, tax_rate, tax_amount) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iidddd", $sale_id, $item['product_id'], $item['quantity'], $item['price'], $item['tax_rate'], $item_tax);
                mysqli_stmt_execute($stmt);
                
                // Update stock
                $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                mysqli_stmt_bind_param($stmt, "iii", $item['quantity'], $item['product_id'], $item['quantity']);
                mysqli_stmt_execute($stmt);
                
                if (mysqli_affected_rows($conn) == 0) {
                    throw new Exception("Insufficient stock for product ID: " . $item['product_id']);
                }
            }
            
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true,
                'sale_id' => $sale_id,
                'message' => 'Sale completed successfully'
            ]);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            throw $e;
        }
        
    } else {
        // GET request - return recent sales for returns
        $stmt = mysqli_prepare($conn, "
            SELECT s.id, s.date, s.total_amount, s.payment_method, u.username as cashier
            FROM sales s 
            LEFT JOIN users u ON s.user_id = u.id 
            ORDER BY s.date DESC 
            LIMIT 100
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare sales query: " . mysqli_error($conn));
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $sales = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $sales[] = [
                'id' => $row['id'],
                'date' => $row['date'],
                'total_amount' => floatval($row['total_amount']),
                'payment_method' => $row['payment_method'],
                'cashier' => $row['cashier']
            ];
        }
        
        echo json_encode($sales);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
