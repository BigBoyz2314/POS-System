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

$cart = isset($_POST['cart']) ? $_POST['cart'] : '';
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

if ($cart === '') {
    echo json_encode(['success' => false, 'message' => 'Empty cart']);
    exit;
}

$stmt = mysqli_prepare($conn, "INSERT INTO parked_sales (user_id, note, cart_json) VALUES (?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB error']);
    exit;
}
$userId = $_SESSION['user_id'];
mysqli_stmt_bind_param($stmt, 'iss', $userId, $note, $cart);
$ok = mysqli_stmt_execute($stmt);
if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to save']);
    exit;
}

echo json_encode(['success' => true, 'id' => mysqli_insert_id($conn)]);
exit;
?>


