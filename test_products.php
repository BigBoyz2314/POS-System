<?php
require_once 'includes/db.php';

echo "<h2>Database Connection Test</h2>";

if (!$conn) {
    echo "<p style='color: red;'>Database connection failed!</p>";
    exit;
}

echo "<p style='color: green;'>Database connection successful!</p>";

// Check if products table exists
$result = mysqli_query($conn, "SHOW TABLES LIKE 'products'");
if (mysqli_num_rows($result) == 0) {
    echo "<p style='color: red;'>Products table does not exist!</p>";
    exit;
}

echo "<p style='color: green;'>Products table exists!</p>";

// Count products
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM products");
$row = mysqli_fetch_assoc($result);
$product_count = $row['count'];

echo "<p>Total products in database: <strong>$product_count</strong></p>";

if ($product_count > 0) {
    echo "<h3>Sample Products:</h3>";
    $result = mysqli_query($conn, "SELECT * FROM products LIMIT 5");
    while ($product = mysqli_fetch_assoc($result)) {
        echo "<p>ID: {$product['id']} - Name: {$product['name']} - Price: {$product['price']} - Stock: {$product['stock']}</p>";
    }
} else {
    echo "<p style='color: red;'>No products found in database!</p>";
}

mysqli_close($conn);
?>
