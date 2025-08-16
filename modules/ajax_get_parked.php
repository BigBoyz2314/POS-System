<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$remove = isset($_GET['remove']) ? intval($_GET['remove']) : 1; // default remove after load
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = mysqli_prepare($conn, "SELECT cart_json FROM parked_sales WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $id, $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

$cart = $row['cart_json'];

if ($remove) {
    $del = mysqli_prepare($conn, "DELETE FROM parked_sales WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($del, 'ii', $id, $userId);
    mysqli_stmt_execute($del);
}

echo json_encode(['success' => true, 'cart' => $cart]);
exit;
?>


