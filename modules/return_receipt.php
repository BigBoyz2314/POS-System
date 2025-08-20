<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

requireLogin();
header('Content-Type: application/json');
@ini_set('display_errors', '0');
ob_start();

if (!isset($_GET['id'])) { ob_clean(); echo json_encode(['success'=>false,'message'=>'Receipt ID required']); exit; }
$id = intval($_GET['id']);

try {
    $stmt = mysqli_prepare($conn, "SELECT id, sale_id, payload FROM return_receipts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if (!$res || mysqli_num_rows($res) === 0) { ob_clean(); echo json_encode(['success'=>false,'message'=>'Receipt not found']); exit; }
    $row = mysqli_fetch_assoc($res);
    $payload = json_decode($row['payload'] ?? '{}', true);
    ob_clean();
    echo json_encode(['success'=>true,'sale_id'=>$row['sale_id'], 'receipt'=>$payload]);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

?>


