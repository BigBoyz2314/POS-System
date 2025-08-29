<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get filter parameters
        $filter = $_GET['filter'] ?? 'today';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';

        // Build date conditions
        $date_condition = '';
        $params = [];

        switch ($filter) {
            case 'today':
                $date_condition = "WHERE DATE(s.date) = CURDATE()";
                break;
            case 'yesterday':
                $date_condition = "WHERE DATE(s.date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'week':
                $date_condition = "WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_condition = "WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'custom':
                if ($start_date && $end_date) {
                    $date_condition = "WHERE DATE(s.date) BETWEEN ? AND ?";
                    $params = [$start_date, $end_date];
                }
                break;
        }

        // Get sales statistics
        $stats = [];

        // Total sales and amount
        $query = "SELECT COUNT(*) as total_sales, SUM(total_amount) as total_amount FROM sales s $date_condition";
        if (!empty($params)) {
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ss", $params[0], $params[1]);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else {
            $result = mysqli_query($conn, $query);
        }
        $row = mysqli_fetch_assoc($result);
        $stats['total_sales'] = (int)$row['total_sales'];
        $stats['total_amount'] = (float)($row['total_amount'] ?: 0);

        // Total items sold
        $query = "SELECT SUM(si.quantity) as total_items FROM sales s 
                  JOIN sale_items si ON s.id = si.sale_id $date_condition";
        if (!empty($params)) {
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ss", $params[0], $params[1]);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else {
            $result = mysqli_query($conn, $query);
        }
        $row = mysqli_fetch_assoc($result);
        $stats['total_items'] = (int)($row['total_items'] ?: 0);

        // Average sale amount
        $stats['avg_sale'] = $stats['total_sales'] > 0 ? $stats['total_amount'] / $stats['total_sales'] : 0;

        // Get detailed sales
        $query = "SELECT s.*, u.name as cashier_name, 
                  (SELECT SUM(si.quantity) FROM sale_items si WHERE si.sale_id = s.id) as items_count,
                  (s.total_amount - s.discount_amount) as final_amount
                  FROM sales s 
                  LEFT JOIN users u ON s.user_id = u.id 
                  $date_condition 
                  ORDER BY s.date DESC";
        if (!empty($params)) {
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ss", $params[0], $params[1]);
            mysqli_stmt_execute($stmt);
            $sales_result = mysqli_stmt_get_result($stmt);
        } else {
            $sales_result = mysqli_query($conn, $query);
        }

        $sales = [];
        while ($sale = mysqli_fetch_assoc($sales_result)) {
            $sales[] = [
                'id' => (int)$sale['id'],
                'date' => $sale['date'],
                'cashier_name' => $sale['cashier_name'],
                'items_count' => (int)$sale['items_count'],
                'total_amount' => (float)$sale['total_amount'],
                'discount_amount' => (float)$sale['discount_amount'],
                'final_amount' => (float)$sale['final_amount'],
                'payment_method' => $sale['payment_method']
            ];
        }

        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'sales' => $sales
        ]);
    }

} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
