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
        // Get recent sales (last 10)
        $query = "SELECT s.id, s.date, s.total_amount, u.name as cashier_name 
                  FROM sales s 
                  LEFT JOIN users u ON s.user_id = u.id 
                  ORDER BY s.date DESC 
                  LIMIT 10";
        
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            throw new Exception("Error fetching recent sales: " . mysqli_error($conn));
        }

        $sales = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $sales[] = [
                'id' => (int)$row['id'],
                'date' => $row['date'],
                'total_amount' => (float)$row['total_amount'],
                'cashier_name' => $row['cashier_name'] ?: 'Unknown'
            ];
        }

        echo json_encode([
            'success' => true,
            'sales' => $sales
        ]);
    }

} catch (Exception $e) {
    error_log("Recent sales error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
