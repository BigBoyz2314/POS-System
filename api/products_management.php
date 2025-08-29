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

    // Handle POST requests (add, edit, delete)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['action'])) {
            throw new Exception('Action is required');
        }

        switch ($input['action']) {
            case 'add':
                $name = trim($input['name']);
                $sku = trim($input['sku']);
                $price = floatval($input['price']);
                $cost_price = floatval($input['cost_price']);
                $tax_rate = floatval($input['tax_rate']);
                $stock = intval($input['stock']);
                $category_id = intval($input['category_id']);
                
                if (empty($name) || empty($sku) || $price <= 0) {
                    throw new Exception('Please fill all required fields correctly.');
                }
                
                $stmt = mysqli_prepare($conn, "INSERT INTO products (name, sku, price, cost_price, tax_rate, stock, category_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssddddii", $name, $sku, $price, $cost_price, $tax_rate, $stock, $category_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Product added successfully.']);
                } else {
                    throw new Exception('Error adding product: ' . mysqli_error($conn));
                }
                break;
                
            case 'edit':
                $id = intval($input['id']);
                $name = trim($input['name']);
                $sku = trim($input['sku']);
                $price = floatval($input['price']);
                $tax_rate = floatval($input['tax_rate']);
                $category_id = intval($input['category_id']);
                
                if (empty($name) || empty($sku) || $price <= 0) {
                    throw new Exception('Please fill all required fields correctly.');
                }
                
                $stmt = mysqli_prepare($conn, "UPDATE products SET name = ?, sku = ?, price = ?, tax_rate = ?, category_id = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ssddii", $name, $sku, $price, $tax_rate, $category_id, $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Product updated successfully.']);
                } else {
                    throw new Exception('Error updating product: ' . mysqli_error($conn));
                }
                break;
                
            case 'delete':
                $id = intval($input['id']);
                $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
                } else {
                    throw new Exception('Error deleting product: ' . mysqli_error($conn));
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    }
    
    // Handle GET requests (fetch products)
    else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get products with category names and calculate moving average cost price
        $query = "SELECT p.*, c.name as category_name,
                  COALESCE(
                      (SELECT AVG(pi.cost_price) 
                       FROM purchase_items pi 
                       JOIN purchases pu ON pi.purchase_id = pu.id 
                       WHERE pi.product_id = p.id 
                       AND pu.purchase_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                      ), p.cost_price
                  ) as avg_cost_price
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  ORDER BY p.name";
        
        $result = mysqli_query($conn, $query);
        if (!$result) {
            throw new Exception("Error fetching products: " . mysqli_error($conn));
        }

        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'sku' => $row['sku'],
                'category_name' => $row['category_name'],
                'price' => (float)$row['price'],
                'tax_rate' => (float)$row['tax_rate'],
                'avg_cost_price' => (float)$row['avg_cost_price'],
                'stock' => (int)$row['stock'],
                'category_id' => (int)$row['category_id']
            ];
        }

        echo json_encode([
            'success' => true,
            'products' => $products
        ]);
    }

} catch (Exception $e) {
    error_log("Products management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
