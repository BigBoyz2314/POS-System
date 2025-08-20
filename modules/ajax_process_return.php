<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require login (could require admin or cashier rights depending on policy)
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$sale_id = isset($_POST['sale_id']) ? intval($_POST['sale_id']) : 0;
$items_json = $_POST['items'] ?? '[]';
$refund_method = $_POST['refund_method'] ?? '';
$refund_cash = isset($_POST['refund_cash']) ? floatval($_POST['refund_cash']) : 0;
$refund_card = isset($_POST['refund_card']) ? floatval($_POST['refund_card']) : 0;

if ($sale_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sale ID is required']);
    exit;
}

$items = json_decode($items_json, true);
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['success' => false, 'message' => 'No items provided']);
    exit;
}

$ddlOk = true;
// Ensure required tables exist BEFORE starting any transaction to avoid implicit commits mid-transaction
$ddlOk = $ddlOk && mysqli_query($conn, "CREATE TABLE IF NOT EXISTS returns (
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
$ddlOk = $ddlOk && mysqli_query($conn, "CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    total_refund DECIMAL(10,2) NOT NULL,
    cash_refund DECIMAL(10,2) NOT NULL,
    card_refund DECIMAL(10,2) NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$ddlOk = $ddlOk && mysqli_query($conn, "CREATE TABLE IF NOT EXISTS return_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    payload LONGTEXT NULL,
    total_refund DECIMAL(10,2) NOT NULL,
    cash_refund DECIMAL(10,2) NOT NULL,
    card_refund DECIMAL(10,2) NOT NULL,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if (!$ddlOk) {
    echo json_encode(['success' => false, 'message' => 'Failed to ensure required tables exist']);
    exit;
}

try {
    // Fetch original sale to get payment split for refund
    $saleStmt = mysqli_prepare($conn, "SELECT id, total_amount, subtotal_amount, tax_amount, discount_amount, payment_method, cash_amount, card_amount FROM sales WHERE id = ?");
    mysqli_stmt_bind_param($saleStmt, 'i', $sale_id);
    if (!mysqli_stmt_execute($saleStmt)) { throw new Exception('Failed to load sale: ' . mysqli_stmt_error($saleStmt)); }
    $saleRes = mysqli_stmt_get_result($saleStmt);
    if (mysqli_num_rows($saleRes) === 0) {
        throw new Exception('Sale not found');
    }
    $sale = mysqli_fetch_assoc($saleRes);

    // Pre-validate all items and compute totals BEFORE any write
    $prepared = [];
    $total_refund_cents = 0;
    foreach ($items as $i) {
        $sale_item_id = isset($i['sale_item_id']) ? intval($i['sale_item_id']) : 0;
        $product_id = isset($i['product_id']) ? intval($i['product_id']) : 0;
        $qty = isset($i['quantity']) ? floatval($i['quantity']) : 0;
        $reason = trim($i['reason'] ?? '');
        if ($product_id <= 0 || $qty <= 0 || $reason === '') {
            throw new Exception('Invalid return line');
        }

        // Get original sale item (prefer sale_item_id if provided)
        if ($sale_item_id > 0) {
            $siStmt = mysqli_prepare($conn, "SELECT id, product_id, quantity, price, tax_rate FROM sale_items WHERE sale_id = ? AND id = ? LIMIT 1");
            mysqli_stmt_bind_param($siStmt, 'ii', $sale_id, $sale_item_id);
        } else {
            $siStmt = mysqli_prepare($conn, "SELECT id, product_id, quantity, price, tax_rate FROM sale_items WHERE sale_id = ? AND product_id = ? LIMIT 1");
            mysqli_stmt_bind_param($siStmt, 'ii', $sale_id, $product_id);
        }
        if (!mysqli_stmt_execute($siStmt)) { throw new Exception('Failed to load sale line: ' . mysqli_stmt_error($siStmt)); }
        $siRes = mysqli_stmt_get_result($siStmt);
        if (mysqli_num_rows($siRes) === 0) {
            throw new Exception('Sale line not found');
        }
        $si = mysqli_fetch_assoc($siRes);

        // Check already returned quantity for this sale line
        if ($sale_item_id > 0) {
            $retSumStmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity),0) AS returned_qty FROM returns WHERE sale_id = ? AND sale_item_id = ?");
            mysqli_stmt_bind_param($retSumStmt, 'ii', $sale_id, $sale_item_id);
        } else {
            $retSumStmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity),0) AS returned_qty FROM returns WHERE sale_id = ? AND product_id = ?");
            mysqli_stmt_bind_param($retSumStmt, 'ii', $sale_id, $product_id);
        }
        if (!mysqli_stmt_execute($retSumStmt)) { throw new Exception('Failed to check prior returns: ' . mysqli_stmt_error($retSumStmt)); }
        $retSumRes = mysqli_stmt_get_result($retSumStmt);
        $retRow = mysqli_fetch_assoc($retSumRes);
        $already_returned = floatval($retRow['returned_qty'] ?? 0);

        $sold_qty = floatval($si['quantity']);
        if ($qty + $already_returned > $sold_qty + 1e-6) {
            throw new Exception('Return quantity exceeds remaining eligible quantity');
        }

        // Unit price in sale_items is tax-inclusive in this app
        $unit_refund = floatval($si['price']);
        $line_refund = round($unit_refund * $qty, 2);
        $line_refund_cents = (int) round($line_refund * 100);
        $total_refund_cents += $line_refund_cents;
        $prepared[] = [
            'sale_item_id' => intval($si['id']),
            'product_id' => intval($si['product_id']),
            'qty' => $qty,
            'reason' => $reason,
            'unit_refund' => $unit_refund,
            'line_refund' => $line_refund
        ];
    }

    // Validate refund method and compute final refund split BEFORE any writes
    if ($refund_method === 'cash') { $cash_refund = round($refund_cash, 2); $card_refund = 0.0; }
    else if ($refund_method === 'card') { $cash_refund = 0.0; $card_refund = round($refund_card, 2); }
    else if ($refund_method === 'mixed') { $cash_refund = round($refund_cash, 2); $card_refund = round($refund_card, 2); }
    else {
        // If unknown, fall back proportionally to original payment split
        $total_paid = floatval($sale['cash_amount']) + floatval($sale['card_amount']);
        if ($total_paid > 0) {
            $cash_ratio = floatval($sale['cash_amount']) / $total_paid;
            $card_ratio = floatval($sale['card_amount']) / $total_paid;
            $cash_refund = round($total_refund * $cash_ratio, 2);
            $card_refund = round($total_refund * $card_ratio, 2);
            $diff = round($total_refund - ($cash_refund + $card_refund), 2);
            if (abs($diff) >= 0.01) { $cash_refund += $diff; }
        } else {
            // default to cash
            $cash_refund = $total_refund; $card_refund = 0.0;
        }
    }
    $total_refund = round($total_refund_cents / 100, 2);
    $input_cents = (int) round(($cash_refund + $card_refund) * 100);
    if ($input_cents !== $total_refund_cents) {
        throw new Exception('Refund amounts do not match total refund');
    }

    // All validations passed; perform writes atomically
    mysqli_begin_transaction($conn);

    $receipt_items = [];
    foreach ($prepared as $p) {
        // Increase stock back
        $upStmt = mysqli_prepare($conn, "UPDATE products SET stock = stock + ? WHERE id = ?");
        mysqli_stmt_bind_param($upStmt, 'di', $p['qty'], $p['product_id']);
        if (!mysqli_stmt_execute($upStmt)) { throw new Exception('Failed to update stock: ' . mysqli_stmt_error($upStmt)); }
        if (mysqli_affected_rows($conn) <= 0) { throw new Exception('No stock row updated for product'); }

        // Record return line
        $retStmt = mysqli_prepare($conn, "INSERT INTO returns (sale_id, sale_item_id, product_id, quantity, reason, refund_amount, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $uid = $_SESSION['user_id'] ?? 0;
        mysqli_stmt_bind_param($retStmt, 'iiidsdi', $sale_id, $p['sale_item_id'], $p['product_id'], $p['qty'], $p['reason'], $p['line_refund'], $uid);
        if (!mysqli_stmt_execute($retStmt)) { throw new Exception('Failed to insert return line: ' . mysqli_stmt_error($retStmt)); }

        // Fetch product name for receipt
        $pname = '';
        $pnStmt = mysqli_prepare($conn, "SELECT name FROM products WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($pnStmt, 'i', $p['product_id']);
        if (!mysqli_stmt_execute($pnStmt)) { throw new Exception('Failed to load product: ' . mysqli_stmt_error($pnStmt)); }
        $pnRes = mysqli_stmt_get_result($pnStmt);
        if ($pnRes && mysqli_num_rows($pnRes) > 0) { $prow = mysqli_fetch_assoc($pnRes); $pname = $prow['name']; }

        $receipt_items[] = [
            'product_id' => $p['product_id'],
            'name' => $pname,
            'quantity' => $p['qty'],
            'unit_price' => $p['unit_refund'],
            'line_total' => $p['line_refund'],
            'tax_rate' => isset($si['tax_rate']) ? floatval($si['tax_rate']) : 0,
            'reason' => $p['reason']
        ];
    }

    // Record refund transaction
    $refStmt = mysqli_prepare($conn, "INSERT INTO refunds (sale_id, total_refund, cash_refund, card_refund, user_id) VALUES (?, ?, ?, ?, ?)");
    $uid2 = $_SESSION['user_id'] ?? null;
    mysqli_stmt_bind_param($refStmt, 'idddi', $sale_id, $total_refund, $cash_refund, $card_refund, $uid2);
    if (!mysqli_stmt_execute($refStmt)) { throw new Exception('Failed to record refund: ' . mysqli_stmt_error($refStmt)); }

    $payload = json_encode([
        'sale_id' => $sale_id,
        'items' => $receipt_items,
        'payment_method' => $sale['payment_method'],
        'cash_refund' => $cash_refund,
        'card_refund' => $card_refund,
        'total_refund' => $total_refund
    ]);

    $rrStmt = mysqli_prepare($conn, "INSERT INTO return_receipts (sale_id, payload, total_refund, cash_refund, card_refund, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($rrStmt, 'isdddi', $sale_id, $payload, $total_refund, $cash_refund, $card_refund, $uid2);
    if (!mysqli_stmt_execute($rrStmt)) { throw new Exception('Failed to save return receipt: ' . mysqli_stmt_error($rrStmt)); }
    $receipt_id = mysqli_insert_id($conn);

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'total_refund' => $total_refund, 'cash_refund' => $cash_refund, 'card_refund' => $card_refund, 'receipt_id' => $receipt_id, 'receipt' => json_decode($payload, true)]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>


