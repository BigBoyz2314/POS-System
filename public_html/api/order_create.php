<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

require_once __DIR__ . '/../includes/db.php';

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$customer_name = trim($body['customer_name'] ?? '');
$customer_phone = trim($body['customer_phone'] ?? '');
$customer_address = trim($body['customer_address'] ?? '');
$items = $body['items'] ?? [];

if (!$items || !is_array($items)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Items required']); exit; }

// Ensure tables exist
$ok = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(32) UNIQUE,
  customer_name VARCHAR(255),
  customer_phone VARCHAR(64),
  customer_address TEXT,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method VARCHAR(32) NOT NULL DEFAULT 'COD',
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>mysqli_error($conn)]); exit; }

$ok = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>mysqli_error($conn)]); exit; }

mysqli_begin_transaction($conn);
try {
  $subtotal = 0.0;
  $tax = 0.0; // extend later

  // Validate stock and compute pricing
  $prepared = [];
  foreach ($items as $it) {
    $pid = (int)($it['productId'] ?? 0);
    $qty = (int)($it['quantity'] ?? 0);
    if ($pid <= 0 || $qty <= 0) { throw new Exception('Invalid item'); }

    $res = mysqli_query($conn, "SELECT id, name, price, web_price, stock FROM products WHERE id=".$pid." LIMIT 1");
    if (!$res || mysqli_num_rows($res) === 0) { throw new Exception('Product not found: '.$pid); }
    $p = mysqli_fetch_assoc($res);

    if ((int)$p['stock'] < $qty) { throw new Exception('Insufficient stock for '.$p['name']); }

    $unit = isset($p['web_price']) && $p['web_price'] !== null && $p['web_price'] !== '' ? (float)$p['web_price'] : (float)$p['price'];
    $line = $unit * $qty;
    $subtotal += $line;
    $prepared[] = [ 'product' => $p, 'qty' => $qty, 'unit' => $unit, 'line' => $line ];
  }

  $total = $subtotal + $tax;

  // Insert order
  $stmt = mysqli_prepare($conn, "INSERT INTO orders (order_number, customer_name, customer_phone, customer_address, subtotal, tax, total, payment_method, status) VALUES (?,?,?,?,?,?,?,?,?)");
  $status = 'pending';
  $payment = 'COD';
  $orderNumber = null; // set after insert using id
  mysqli_stmt_bind_param($stmt, 'ssssddsss', $orderNumber, $customer_name, $customer_phone, $customer_address, $subtotal, $tax, $total, $payment, $status);
  if (!mysqli_stmt_execute($stmt)) { throw new Exception(mysqli_error($conn)); }
  $orderId = mysqli_insert_id($conn);
  $orderNumber = 'ORD'.str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
  mysqli_query($conn, "UPDATE orders SET order_number='".mysqli_real_escape_string($conn, $orderNumber)."' WHERE id=".$orderId);

  // Insert items and decrement stock
  foreach ($prepared as $pr) {
    $p = $pr['product']; $qty = $pr['qty']; $unit = $pr['unit']; $line = $pr['line'];
    $stmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, name, price, quantity, total_price) VALUES (?,?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'iisdid', $orderId, $p['id'], $p['name'], $unit, $qty, $line);
    if (!mysqli_stmt_execute($stmt)) { throw new Exception(mysqli_error($conn)); }

    // decrement stock
    $stmt2 = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt2, 'ii', $qty, $p['id']);
    if (!mysqli_stmt_execute($stmt2)) { throw new Exception(mysqli_error($conn)); }
  }

  mysqli_commit($conn);
  echo json_encode(['ok'=>true,'order_id'=>$orderId,'order_number'=>$orderNumber,'total'=>$total]);
} catch (Exception $ex) {
  mysqli_rollback($conn);
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$ex->getMessage()]);
}


