<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Require login
requireLogin();

echo "<h2>AJAX Test</h2>";

// Test the exact same query as ajax_search_products.php
$stmt = mysqli_prepare($conn, "SELECT p.*, c.name as category_name FROM products p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              ORDER BY p.name LIMIT 50");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$num_rows = mysqli_num_rows($result);
echo "<p>Found $num_rows products</p>";

if ($num_rows > 0) {
    echo "<h3>First 3 products:</h3>";
    $count = 0;
    while ($product = mysqli_fetch_assoc($result) && $count < 3) {
        echo "<p>ID: {$product['id']} - Name: {$product['name']} - Price: {$product['price']} - Stock: {$product['stock']}</p>";
        $count++;
    }
} else {
    echo "<p>No products found</p>";
}

mysqli_close($conn);
?>
