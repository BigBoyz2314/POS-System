<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/db.php';

$sql = "SELECT id, name, slug, description, sort_order FROM categories ORDER BY sort_order ASC, name ASC";
$res = mysqli_query($conn, $sql);
$rows = [];
if ($res) {
  while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
  echo json_encode([ 'ok' => true, 'categories' => $rows ]);
} else {
  http_response_code(500);
  echo json_encode([ 'ok' => false, 'error' => mysqli_error($conn) ]);
}

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

    // Get categories
    $query = "SELECT * FROM categories ORDER BY name";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        throw new Exception("Error fetching categories: " . mysqli_error($conn));
    }

    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = [
            'id' => (int)$row['id'],
            'name' => $row['name']
        ];
    }

    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    error_log("Categories error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>
