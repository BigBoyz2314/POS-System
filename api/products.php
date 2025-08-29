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

try {
    // Require login
    requireLogin();
    
    // Get all products
    $stmt = mysqli_prepare($conn, "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.name");
    if (!$stmt) {
        throw new Exception("Failed to prepare products query: " . mysqli_error($conn));
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to execute products query: " . mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $products = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'category_name' => $row['category_name'],
            'price' => floatval($row['price']),
            'stock' => intval($row['stock']),
            'tax_rate' => floatval($row['tax_rate'] ?? 0)
        ];
    }
    
    echo json_encode($products);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
