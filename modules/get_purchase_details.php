<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require admin access
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['purchase_id'])) {
    echo json_encode(['success' => false, 'message' => 'Purchase ID is required']);
    exit;
}

$purchase_id = intval($_GET['purchase_id']);

// Get purchase details
$stmt = mysqli_prepare($conn, "SELECT p.*, v.name as vendor_name, u.name as user_name 
                               FROM purchases p 
                               LEFT JOIN vendors v ON p.vendor_id = v.id 
                               LEFT JOIN users u ON p.user_id = u.id 
                               WHERE p.id = ?");
mysqli_stmt_bind_param($stmt, "i", $purchase_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase not found']);
    exit;
}

$purchase = mysqli_fetch_assoc($result);

// Get purchase items
$stmt = mysqli_prepare($conn, "SELECT pi.*, p.name as product_name 
                               FROM purchase_items pi 
                               JOIN products p ON pi.product_id = p.id 
                               WHERE pi.purchase_id = ?");
mysqli_stmt_bind_param($stmt, "i", $purchase_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);

$items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $items[] = [
        'product_name' => $item['product_name'],
        'quantity' => $item['quantity'],
        'cost_price' => $item['cost_price']
    ];
}

$purchase['items'] = $items;

echo json_encode(['success' => true, 'purchase' => $purchase]);
?>
