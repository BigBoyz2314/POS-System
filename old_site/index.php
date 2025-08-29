<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get dashboard statistics
$stats = [];

// Total products
$query = "SELECT COUNT(*) as total FROM products";
$result = mysqli_query($conn, $query);
$stats['products'] = mysqli_fetch_assoc($result)['total'];

// Total sales today
$query = "SELECT COUNT(*) as total, SUM(total_amount) as amount FROM sales WHERE DATE(date) = CURDATE()";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$stats['sales_today'] = $row['total'];
$stats['amount_today'] = $row['amount'] ?: 0;

// Low stock products (less than 10)
$query = "SELECT COUNT(*) as total FROM products WHERE stock < 10";
$result = mysqli_query($conn, $query);
$stats['low_stock'] = mysqli_fetch_assoc($result)['total'];

// Recent sales
$query = "SELECT s.*, u.name as cashier_name FROM sales s 
          LEFT JOIN users u ON s.user_id = u.id 
          ORDER BY s.date DESC LIMIT 5";
$recent_sales = mysqli_query($conn, $query);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">
            <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
        </h1>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Products -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-blue-600 uppercase tracking-wide">Total Products</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?php echo $stats['products']; ?></p>
                </div>
                <div class="text-blue-500">
                    <i class="fas fa-box text-3xl"></i>
                </div>
            </div>
        </div>

        <!-- Sales Today -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-green-600 uppercase tracking-wide">Sales Today</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?php echo $stats['sales_today']; ?></p>
                </div>
                <div class="text-green-500">
                    <i class="fas fa-shopping-cart text-3xl"></i>
                </div>
            </div>
        </div>

        <!-- Revenue Today -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-indigo-500">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-indigo-600 uppercase tracking-wide">Revenue Today</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">PKR <?php echo number_format($stats['amount_today'], 2); ?></p>
                </div>
                <div class="text-indigo-500">
                    <i class="fas fa-money-bill-wave text-3xl"></i>
                </div>
            </div>
        </div>

        <!-- Low Stock Items -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-yellow-600 uppercase tracking-wide">Low Stock Items</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?php echo $stats['low_stock']; ?></p>
                </div>
                <div class="text-yellow-500">
                    <i class="fas fa-exclamation-triangle text-3xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Sales</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cashier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Amount</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php while ($sale = mysqli_fetch_assoc($recent_sales)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <?php echo date('M d, Y H:i', strtotime($sale['date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <?php echo htmlspecialchars($sale['cashier_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                            PKR <?php echo number_format($sale['total_amount'], 2); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
