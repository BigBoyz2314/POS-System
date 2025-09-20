<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';

$saleId = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
if ($saleId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid sale_id']); exit; }
if (!isset($conn) || !$conn) { echo json_encode(['success' => false, 'message' => 'DB connection failed']); exit; }

// Load sale
$saleQ = mysqli_query($conn, "SELECT id, total_amount, payment_method, user_id FROM sales WHERE id = " . $saleId . " LIMIT 1");
if (!$saleQ || mysqli_num_rows($saleQ) === 0) { echo json_encode(['success' => false, 'message' => 'Sale not found']); exit; }
$sale = mysqli_fetch_assoc($saleQ);

// Load items with already returned quantities if available
$items = [];
$itemsQ = mysqli_query($conn, "SELECT si.id AS sale_item_id, si.product_id, p.name, si.quantity, si.price, si.tax_rate, 0 AS returned_qty, NULL AS remaining_qty FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = " . $saleId);
if ($itemsQ) {
	while ($row = mysqli_fetch_assoc($itemsQ)) {
		$row['quantity'] = (int)$row['quantity'];
		$row['price'] = (float)$row['price'];
		$row['tax_rate'] = (float)$row['tax_rate'];
		$row['returned_qty'] = (int)$row['returned_qty'];
		$row['remaining_qty'] = $row['quantity'] - $row['returned_qty'];
		$items[] = $row;
	}
	mysqli_free_result($itemsQ);
}

mysqli_close($conn);

echo json_encode([
	'success' => true,
	'sale' => [
		'id' => (int)$sale['id'],
		'payment_method' => $sale['payment_method'],
		'total_amount' => (float)$sale['total_amount'],
		'items' => $items,
	],
]);
