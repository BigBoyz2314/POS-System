<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/uploads';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

if (!isset($_FILES['file'])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'No file']); exit; }
$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Upload error']); exit; }

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['png','jpg','jpeg','gif','webp'])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid file type']); exit; }

$name = 'logo.' . $ext;
$path = $uploadDir . '/' . $name;
if (!move_uploaded_file($file['tmp_name'], $path)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Failed to save']); exit; }

// Return public URL used by both Shop and POS
echo json_encode(['ok'=>true,'url'=>'/uploads/'.$name]);


