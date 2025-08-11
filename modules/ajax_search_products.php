<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require login
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query'])) {
    $query = trim($_POST['query']);
    
    if (strlen($query) >= 2) {
        // Search for specific products
        $search_query = "%$query%";
        $stmt = mysqli_prepare($conn, "SELECT p.*, c.name as category_name FROM products p 
                                      LEFT JOIN categories c ON p.category_id = c.id 
                                      WHERE p.name LIKE ? OR p.sku LIKE ? 
                                      ORDER BY p.name LIMIT 10");
        mysqli_stmt_bind_param($stmt, "ss", $search_query, $search_query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        // Load all products
        $stmt = mysqli_prepare($conn, "SELECT p.*, c.name as category_name FROM products p 
                                      LEFT JOIN categories c ON p.category_id = c.id 
                                      ORDER BY p.name LIMIT 50");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    }
    
    if (mysqli_num_rows($result) > 0) {
        while ($product = mysqli_fetch_assoc($result)) {
            $stock_class = $product['stock'] < 10 ? 'text-red-600' : 'text-green-600';
            echo "
            <div class='p-3 border rounded hover:bg-gray-50 cursor-pointer' onclick='addToCart(" . json_encode($product) . ")'>
                <div class='flex justify-between items-center'>
                    <div>
                        <h3 class='font-medium text-gray-900'>" . htmlspecialchars($product['name']) . "</h3>
                        <p class='text-sm text-gray-500'>SKU: " . htmlspecialchars($product['sku']) . " | " . htmlspecialchars($product['category_name']) . "</p>
                    </div>
                    <div class='text-right'>
                        <p class='font-semibold text-gray-900'>PKR " . number_format($product['price'], 2) . "</p>
                        <p class='text-sm $stock_class'>Stock: " . $product['stock'] . "</p>
                    </div>
                </div>
            </div>";
        }
    } else {
        echo "<p class='text-gray-500 text-center p-4'>No products found</p>";
    }
} else {
    echo "<p class='text-red-600 text-center p-4'>Invalid request</p>";
}
?>
