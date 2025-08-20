<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

requireLogin();
header('Content-Type: application/json');
@ini_set('display_errors', '0');
ob_start();

try {
    // Create table if not exists (safety for first run)
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        sale_item_id INT NULL,
        product_id INT NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        refund_amount DECIMAL(10,2) NOT NULL,
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure return_receipts table exists for reprints
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS return_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        payload LONGTEXT NULL,
        total_refund DECIMAL(10,2) NOT NULL,
        cash_refund DECIMAL(10,2) NOT NULL,
        card_refund DECIMAL(10,2) NOT NULL,
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $where = '';
    if (isset($_GET['sale_id']) && intval($_GET['sale_id']) > 0) {
        $sale_id = intval($_GET['sale_id']);
        $where = 'WHERE r.sale_id = ' . $sale_id;
    }
    $sql = "SELECT r.id, r.sale_id, r.product_id, p.name AS product_name, r.quantity, r.reason, r.refund_amount, r.created_at
            FROM returns r LEFT JOIN products p ON r.product_id = p.id
            $where
            ORDER BY r.id DESC LIMIT 100";
    $res = mysqli_query($conn, $sql);
    if (!$res) { ob_clean(); echo json_encode(['success'=>false,'message'=>'DB error: '.mysqli_error($conn)]); exit; }
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['quantity'] = floatval($row['quantity']);
        $row['refund_amount'] = floatval($row['refund_amount']);
        $rows[] = $row;
    }
    // Fetch receipt ids for reprint mapping
    $map = [];
    $rr = mysqli_query($conn, "SELECT id, sale_id FROM return_receipts ORDER BY id DESC LIMIT 500");
    if ($rr) { while ($rrow = mysqli_fetch_assoc($rr)) { $map[$rrow['sale_id']] = $rrow['id']; } }
    ob_clean();
    echo json_encode(['success'=>true,'returns'=>$rows, 'receipts'=>$map]);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

?>


