<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'slug required']); exit; }

$slugSafe = mysqli_real_escape_string($conn, $slug);

$sql = "SELECT id, name, slug, description, price, web_price, stock, seo_title, seo_desc
        FROM products WHERE is_published=1 AND slug='{$slugSafe}' LIMIT 1";
$res = mysqli_query($conn, $sql);
if (!$res || mysqli_num_rows($res) === 0) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not found']); exit; }

$product = mysqli_fetch_assoc($res);

$imgs = [];
$imgRes = mysqli_query($conn, "SELECT image_url FROM product_images WHERE product_id=".(int)$product['id']." ORDER BY sort_order ASC, id ASC");
if ($imgRes) { while ($ir = mysqli_fetch_assoc($imgRes)) { $imgs[] = $ir['image_url']; } }

$cats = [];
$catRes = mysqli_query($conn, "SELECT c.id, c.name, c.slug FROM categories c JOIN product_categories pc ON pc.category_id=c.id WHERE pc.product_id=".(int)$product['id']);
if ($catRes) { while ($cr = mysqli_fetch_assoc($catRes)) { $cats[] = $cr; } }

$product['images'] = $imgs;
$product['categories'] = $cats;
$product['display_price'] = isset($product['web_price']) && $product['web_price'] !== null && $product['web_price'] !== '' ? (float)$product['web_price'] : (float)$product['price'];

echo json_encode(['ok'=>true,'product'=>$product]);


