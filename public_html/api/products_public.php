<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$categorySlug = isset($_GET['category']) ? trim($_GET['category']) : '';

$where = "p.is_published = 1";
$params = [];

if ($q !== '') {
  $qSafe = '%' . mysqli_real_escape_string($conn, $q) . '%';
  $where .= " AND (p.name LIKE '{$qSafe}' OR p.slug LIKE '{$qSafe}' OR p.barcode LIKE '{$qSafe}')";
}

if ($categorySlug !== '') {
  $catSafe = mysqli_real_escape_string($conn, $categorySlug);
  $where .= " AND EXISTS (SELECT 1 FROM product_categories pc JOIN categories c ON pc.category_id=c.id WHERE pc.product_id=p.id AND c.slug='{$catSafe}')";
}

$sql = "SELECT p.id, p.name, p.slug, p.price, p.web_price, p.stock, p.featured, p.sort_order,
        (SELECT image_url FROM product_images pi WHERE pi.product_id=p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS image_url
        FROM products p
        WHERE {$where}
        ORDER BY p.featured DESC, p.sort_order ASC, p.name ASC
        LIMIT 200";

$res = mysqli_query($conn, $sql);
$rows = [];
if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $r['display_price'] = isset($r['web_price']) && $r['web_price'] !== null && $r['web_price'] !== '' ? (float)$r['web_price'] : (float)$r['price'];
    $rows[] = $r;
  }
  echo json_encode([ 'ok' => true, 'products' => $rows ]);
} else {
  http_response_code(500);
  echo json_encode([ 'ok' => false, 'error' => mysqli_error($conn) ]);
}


