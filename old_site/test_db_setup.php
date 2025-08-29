<?php
require_once 'includes/db.php';

echo "<h2>Database Connection Test</h2>";

if (!$conn) {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit;
}

echo "<p style='color: green;'>✅ Database connection successful</p>";

// Check if tables exist
$tables = ['users', 'categories', 'products', 'sales', 'sale_items'];

foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "<p style='color: green;'>✅ Table '$table' exists</p>";
    } else {
        echo "<p style='color: red;'>❌ Table '$table' does not exist</p>";
    }
}

// Check if there are any sales
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM sales");
$row = mysqli_fetch_assoc($result);
echo "<p>📊 Total sales in database: " . $row['count'] . "</p>";

if ($row['count'] > 0) {
    // Show first sale
    $result = mysqli_query($conn, "SELECT * FROM sales ORDER BY id DESC LIMIT 1");
    $sale = mysqli_fetch_assoc($result);
    echo "<p>🔍 Latest sale ID: " . $sale['id'] . "</p>";
    
    // Check if sale_items exist for this sale
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM sale_items WHERE sale_id = " . $sale['id']);
    $row = mysqli_fetch_assoc($result);
    echo "<p>📦 Items in latest sale: " . $row['count'] . "</p>";
}

echo "<h3>Test get_sale_details.php</h3>";
if ($row['count'] > 0) {
    echo "<p>Testing with sale ID: " . $sale['id'] . "</p>";
    echo "<p><a href='modules/get_sale_details.php?sale_id=" . $sale['id'] . "' target='_blank'>Click here to test get_sale_details.php</a></p>";
} else {
    echo "<p style='color: orange;'>⚠️ No sales found. Please create a sale first to test invoice viewing.</p>";
}
?>
