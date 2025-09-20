<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/db.php';

// Ensure settings table
$ok = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS app_settings (
  id INT PRIMARY KEY DEFAULT 1,
  business_name VARCHAR(255) NULL,
  business_address TEXT NULL,
  business_phone VARCHAR(64) NULL,
  logo_url VARCHAR(1024) NULL,
  receipt_template VARCHAR(32) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>mysqli_error($conn)]); exit; }

// Seed single row if absent
$res = mysqli_query($conn, "SELECT id FROM app_settings WHERE id=1");
if ($res && mysqli_num_rows($res) === 0) {
  mysqli_query($conn, "INSERT INTO app_settings (id, business_name, business_address, business_phone, logo_url, receipt_template) VALUES (1, 'Your Business Name', '123 Street, City', '+92 300 0000000', '/uploads/logo.png', 'compact')");
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $res = mysqli_query($conn, "SELECT business_name, business_address, business_phone, logo_url, receipt_template FROM app_settings WHERE id=1 LIMIT 1");
  $row = $res ? mysqli_fetch_assoc($res) : null;
  echo json_encode(['ok'=>true,'settings'=>$row]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = json_decode(file_get_contents('php://input'), true);
  if (!$body) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }
  $name = mysqli_real_escape_string($conn, trim($body['business_name'] ?? ''));
  $addr = mysqli_real_escape_string($conn, trim($body['business_address'] ?? ''));
  $phone = mysqli_real_escape_string($conn, trim($body['business_phone'] ?? ''));
  $logo = mysqli_real_escape_string($conn, trim($body['logo_url'] ?? ''));
  $tpl = mysqli_real_escape_string($conn, trim($body['receipt_template'] ?? ''));
  $sql = "UPDATE app_settings SET business_name='{$name}', business_address='{$addr}', business_phone='{$phone}', logo_url='{$logo}', receipt_template='{$tpl}' WHERE id=1";
  if (!mysqli_query($conn, $sql)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>mysqli_error($conn)]); exit; }
  echo json_encode(['ok'=>true]);
  exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);


