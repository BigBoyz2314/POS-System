<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require login
requireLogin();

header('Content-Type: application/json');

if (!isset($_POST['product_ids']) || !is_array($_POST['product_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Product IDs required']);
    exit;
}

$product_ids = array_map('intval', $_POST['product_ids']);

try {
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = mysqli_prepare($conn, "SELECT id, stock FROM products WHERE id IN ($placeholders)");
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }
    
    // Bind parameters dynamically
    $types = str_repeat('i', count($product_ids));
    mysqli_stmt_bind_param($stmt, $types, ...$product_ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $stock_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $stock_data[$row['id']] = (int)$row['stock'];
    }
    
    echo json_encode(['success' => true, 'stock_data' => $stock_data]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
