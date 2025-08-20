<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require login
requireLogin();

// Debug: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p class='text-red-600 text-center p-4'>User not logged in</p>";
    exit;
}

// Debug: Check if we can connect to database
if (!$conn) {
    echo "<p class='text-red-600 text-center p-4'>Database connection failed</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query'])) {
    $query = trim($_POST['query']);
    $layout = isset($_POST['layout']) ? strtolower(trim($_POST['layout'])) : 'list';
    $isGrid = ($layout === 'grid');
    
    // Debug: Log the request
    error_log("AJAX request received: query = '$query'");
    
    if (strlen($query) >= 2) {
        // Search for specific products
        $search_query = "%$query%";
        $stmt = mysqli_prepare($conn, "SELECT p.*, c.name as category_name,
                                      COALESCE(
                                          (SELECT AVG(pi.cost_price) 
                                           FROM purchase_items pi 
                                           JOIN purchases pu ON pi.purchase_id = pu.id 
                                           WHERE pi.product_id = p.id 
                                           AND pu.purchase_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                                          ), p.cost_price
                                      ) as avg_cost_price
                                      FROM products p 
                                      LEFT JOIN categories c ON p.category_id = c.id 
                                      WHERE p.name LIKE ? OR p.sku LIKE ? 
                                      ORDER BY p.name LIMIT 10");
        mysqli_stmt_bind_param($stmt, "ss", $search_query, $search_query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        // Load all products
        $stmt = mysqli_prepare($conn, "SELECT p.*, c.name as category_name,
                                      COALESCE(
                                          (SELECT AVG(pi.cost_price) 
                                           FROM purchase_items pi 
                                           JOIN purchases pu ON pi.purchase_id = pu.id 
                                           WHERE pi.product_id = p.id 
                                           AND pu.purchase_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                                          ), p.cost_price
                                      ) as avg_cost_price
                                      FROM products p 
                                      LEFT JOIN categories c ON p.category_id = c.id 
                                      ORDER BY p.name LIMIT 50");
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        // Debug: Check if query executed successfully
        if (!$result) {
            echo "<p class='text-red-600 text-center p-4'>Query failed: " . mysqli_error($conn) . "</p>";
            exit;
        }
    }
    
    $num_rows = mysqli_num_rows($result);
    if ($num_rows > 0) {
        // Debug: Log the number of products
        error_log("Found $num_rows products to display");
        
        while ($product = mysqli_fetch_assoc($result)) {
            $stock_class = $product['stock'] < 10 ? 'text-red-600' : 'text-green-600';
            $avg_cost_price = $product['avg_cost_price'] ?: $product['cost_price'] ?: 0;
            
            // Check if this is being called from purchases page
            $is_purchase_page = isset($_POST['is_purchase']) && $_POST['is_purchase'] == '1';
            
            if ($is_purchase_page) {
                // Display for purchase page
                if ($isGrid) {
                    echo "
                    <div class='p-3 border rounded-lg hover:shadow cursor-pointer bg-white dark:bg-gray-800' onclick='addToPurchaseCart(" . json_encode($product) . ")'>
                        <div class='flex flex-col gap-1'>
                            <h3 class='font-medium text-gray-900 dark:text-gray-100 text-sm line-clamp-2'>" . htmlspecialchars($product['name']) . "</h3>
                            <div class='text-xs text-gray-500 dark:text-gray-400'>SKU: " . htmlspecialchars($product['sku']) . "</div>
                            <div class='text-xs text-gray-500 dark:text-gray-400'>" . htmlspecialchars($product['category_name']) . "</div>
                        </div>
                        <div class='flex items-center justify-between mt-2'>
                            <div class='font-semibold text-gray-900 dark:text-gray-100 text-sm'>PKR " . number_format($avg_cost_price, 2) . "</div>
                            <span class='text-xs $stock_class'>(" . $product['stock'] . ")</span>
                        </div>
                        <div class='text-[10px] text-gray-500 dark:text-gray-400'>avg cost</div>
                    </div>";
                } else {
                    echo "
                    <div class='p-2 border rounded hover:bg-gray-50 cursor-pointer dark:hover:bg-gray-700' onclick='addToPurchaseCart(" . json_encode($product) . ")'>
                        <div class='flex justify-between items-center'>
                            <div class='flex-1 min-w-0'>
                                <div class='flex items-center space-x-1'>
                                    <h3 class='font-medium text-gray-900 dark:text-gray-100 text-sm truncate'>" . htmlspecialchars($product['name']) . "</h3>
                                    <span class='text-xs text-gray-400'>|</span>
                                    <span class='text-xs text-gray-500 dark:text-gray-300'>" . htmlspecialchars($product['sku']) . "</span>
                                    <span class='text-xs text-gray-400'>|</span>
                                    <span class='text-xs text-gray-500 dark:text-gray-300'>" . htmlspecialchars($product['category_name']) . "</span>
                                </div>
                            </div>
                            <div class='text-right flex-shrink-0 ml-2'>
                                <div class='flex items-center space-x-2'>
                                    <p class='font-semibold text-gray-900 dark:text-gray-100 text-sm'>PKR " . number_format($avg_cost_price, 2) . "</p>
                                    <span class='text-xs text-gray-500 dark:text-gray-300'>avg cost</span>
                                    <span class='text-xs $stock_class'>(" . $product['stock'] . ")</span>
                                </div>
                            </div>
                        </div>
                    </div>";
                }
            } else {
                // Display for sales page
                if ($isGrid) {
                    echo "
                    <div class='p-3 border rounded-lg hover:shadow cursor-pointer bg-white dark:bg-gray-800' onclick='addToCart(" . json_encode($product) . ")'>
                        <div class='flex flex-col gap-1'>
                            <h3 class='font-medium text-gray-900 dark:text-gray-100 text-sm line-clamp-2'>" . htmlspecialchars($product['name']) . "</h3>
                            <div class='text-xs text-gray-500 dark:text-gray-400'>SKU: " . htmlspecialchars($product['sku']) . "</div>
                            <div class='text-xs text-gray-500 dark:text-gray-400'>" . htmlspecialchars($product['category_name']) . "</div>
                        </div>
                        <div class='flex items-center justify-between mt-2'>
                            <div class='font-semibold text-gray-900 dark:text-gray-100 text-sm'>PKR " . number_format($product['price'], 2) . "</div>
                            <span class='text-xs $stock_class'>(" . $product['stock'] . ")</span>
                        </div>
                        <div class='text-[10px] text-gray-500 dark:text-gray-400'>inc. tax</div>
                    </div>";
                } else {
                    echo "
                    <div class='p-2 border rounded hover:bg-gray-50 cursor-pointer dark:hover:bg-gray-700' onclick='addToCart(" . json_encode($product) . ")'>
                        <div class='flex justify-between items-center'>
                            <div class='flex-1 min-w-0'>
                                <div class='flex items-center space-x-1'>
                                    <h3 class='font-medium text-gray-900 dark:text-gray-100 text-sm truncate'>" . htmlspecialchars($product['name']) . "</h3>
                                    <span class='text-xs text-gray-400'>|</span>
                                    <span class='text-xs text-gray-500 dark:text-gray-300'>" . htmlspecialchars($product['sku']) . "</span>
                                    <span class='text-xs text-gray-400'>|</span>
                                    <span class='text-xs text-gray-500 dark:text-gray-300'>" . htmlspecialchars($product['category_name']) . "</span>
                                </div>
                            </div>
                            <div class='text-right flex-shrink-0 ml-2'>
                                <div class='flex items-center space-x-2'>
                                    <p class='font-semibold text-gray-900 dark:text-gray-100 text-sm'>PKR " . number_format($product['price'], 2) . "</p>
                                    <span class='text-xs text-gray-500 dark:text-gray-300'>inc. tax</span>
                                    <span class='text-xs $stock_class'>(" . $product['stock'] . ")</span>
                                </div>
                            </div>
                        </div>
                    </div>";
                }
            }
        }
    } else {
        echo "<p class='text-gray-500 text-center p-4'>No products found</p>";
    }
} else {
    echo "<p class='text-red-600 text-center p-4'>Invalid request</p>";
}
?>
