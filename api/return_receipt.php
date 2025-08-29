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
        if (!isset($_GET['id'])) {
            throw new Exception('Receipt ID required');
        }

        $id = intval($_GET['id']);
        if ($id <= 0) {
            throw new Exception('Invalid receipt ID');
        }

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

        $stmt = mysqli_prepare($conn, "SELECT id, sale_id, payload, total_refund, cash_refund, card_refund FROM return_receipts WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (!$result || mysqli_num_rows($result) === 0) {
            throw new Exception('Receipt not found');
        }

        $row = mysqli_fetch_assoc($result);
        $payload = json_decode($row['payload'] ?? '{}', true);

        echo json_encode([
            'success' => true,
            'sale_id' => (int)$row['sale_id'],
            'receipt' => array_merge($payload, [
                'total_refund' => (float)$row['total_refund'],
                'cash_refund' => (float)$row['cash_refund'],
                'card_refund' => (float)$row['card_refund']
            ])
        ]);
    }

} catch (Exception $e) {
    error_log("Return receipt error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
