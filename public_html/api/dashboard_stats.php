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
        $stats = [];

        // Total products
        $query = "SELECT COUNT(*) as total_products FROM products";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);
        $stats['products'] = (int)$row['total_products'];

        // Sales today
        $query = "SELECT COUNT(*) as sales_today FROM sales WHERE DATE(date) = CURDATE()";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);
        $stats['sales_today'] = (int)$row['sales_today'];

        // Revenue today
        $query = "SELECT COALESCE(SUM(total_amount), 0) as amount_today FROM sales WHERE DATE(date) = CURDATE()";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);
        $stats['amount_today'] = (float)$row['amount_today'];

        // Low stock items (less than 10 items)
        $query = "SELECT COUNT(*) as low_stock FROM products WHERE stock < 10";
        $result = mysqli_query($conn, $query);
        $row = mysqli_fetch_assoc($result);
        $stats['low_stock'] = (int)$row['low_stock'];

        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    }

} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
