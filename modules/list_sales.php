<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

requireLogin();
header('Content-Type: application/json');
@ini_set('display_errors', '0');
ob_start();

try {
    // Ensure returns table exists so subqueries do not fail on first run
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

    // Fetch latest 100 sales with key fields for lookup
    $sql = "SELECT s.id, s.total_amount, s.subtotal_amount, s.tax_amount, s.discount_amount, s.payment_method, s.cash_amount, s.card_amount, s.date AS created_at, u.name AS cashier_name,
                   (SELECT COALESCE(SUM(si.quantity),0) FROM sale_items si WHERE si.sale_id = s.id) AS sold_qty,
                   (SELECT COALESCE(SUM(r.quantity),0) FROM returns r WHERE r.sale_id = s.id) AS returned_qty
            FROM sales s LEFT JOIN users u ON s.user_id = u.id
            WHERE (SELECT COALESCE(SUM(si2.quantity),0) FROM sale_items si2 WHERE si2.sale_id = s.id) >
                  (SELECT COALESCE(SUM(r2.quantity),0) FROM returns r2 WHERE r2.sale_id = s.id)
            ORDER BY s.id DESC LIMIT 100";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'DB error: ' . mysqli_error($conn)]);
        exit;
    }
    $sales = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['total_amount'] = floatval($row['total_amount']);
        $row['subtotal_amount'] = floatval($row['subtotal_amount']);
        $row['tax_amount'] = floatval($row['tax_amount']);
        $row['discount_amount'] = floatval($row['discount_amount']);
        $row['cash_amount'] = floatval($row['cash_amount']);
        $row['card_amount'] = floatval($row['card_amount']);
        $row['final'] = ($row['total_amount'] - $row['discount_amount']);
        $sales[] = $row;
    }
    ob_clean();
    echo json_encode(['success' => true, 'sales' => $sales]);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


