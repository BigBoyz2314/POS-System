<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Ensure table exists
$create = "CREATE TABLE IF NOT EXISTS parked_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    cart_json LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create);

$userId = $_SESSION['user_id'];
$stmt = mysqli_prepare($conn, "SELECT id, note, created_at, JSON_LENGTH(cart_json) as items_count FROM parked_sales WHERE user_id = ? ORDER BY created_at DESC");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    // If JSON_LENGTH not supported, approximate item count later on client
    if (!isset($row['items_count'])) {
        $row['items_count'] = null;
    }
    $rows[] = $row;
}

echo json_encode(['success' => true, 'data' => $rows]);
exit;
?>


