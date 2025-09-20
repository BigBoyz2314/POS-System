<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/db.php';

// Ensure table
$ok = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS shop_content (
  id INT PRIMARY KEY DEFAULT 1,
  hero_title VARCHAR(255) NULL,
  hero_subtitle VARCHAR(512) NULL,
  promo_text VARCHAR(255) NULL,
  featured_product_ids TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if (!$ok) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>mysqli_error($conn)]); exit; }

// Seed row
$res = mysqli_query($conn, "SELECT id FROM shop_content WHERE id=1");
if ($res && mysqli_num_rows($res) === 0) {
  mysqli_query($conn, "INSERT INTO shop_content (id, hero_title, hero_subtitle, promo_text, featured_product_ids) VALUES (1, 'Discover products you’ll love', 'Fresh arrivals, curated picks, and everyday essentials.', 'Free shipping over Rs. 2000', '')");
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $res = mysqli_query($conn, "SELECT hero_title, hero_subtitle, promo_text, featured_product_ids FROM shop_content WHERE id=1 LIMIT 1");
  $row = $res ? mysqli_fetch_assoc($res) : null;
  echo json_encode(['ok'=>true,'content'=>$row]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = json_decode(file_get_contents('php://input'), true);
  if (!$body) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }
  $hero_title = mysqli_real_escape_string($conn, trim($body['hero_title'] ?? ''));
  $hero_subtitle = mysqli_real_escape_string($conn, trim($body['hero_subtitle'] ?? ''));
  $promo_text = mysqli_real_escape_string($conn, trim($body['promo_text'] ?? ''));
  $featured_ids = mysqli_real_escape_string($conn, trim($body['featured_product_ids'] ?? ''));
  $sql = "UPDATE shop_content SET hero_title='{$hero_title}', hero_subtitle='{$hero_subtitle}', promo_text='{$promo_text}', featured_product_ids='{$featured_ids}' WHERE id=1";
  if (!mysqli_query($conn, $sql)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>mysqli_error($conn)]); exit; }
  echo json_encode(['ok'=>true]);
  exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);


