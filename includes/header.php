<?php
// Determine the base path for navigation links
$current_dir = dirname($_SERVER['PHP_SELF']);
$base_path = '';
if (strpos($current_dir, '/modules') !== false) {
    $base_path = '../';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl font-bold text-gray-900">POS System</h1>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="<?php echo $base_path; ?>index.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                                                       <?php if (isAdmin()): ?>
                               <a href="<?php echo $base_path; ?>modules/products.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                   Products
                               </a>
                               <a href="<?php echo $base_path; ?>modules/vendors.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                   Vendors
                               </a>
                               <a href="<?php echo $base_path; ?>modules/purchases.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                   Purchases
                               </a>
                               <?php endif; ?>
                               <a href="<?php echo $base_path; ?>modules/sales.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                   POS Sales
                               </a>
                               <?php if (isAdmin()): ?>
                               <a href="<?php echo $base_path; ?>modules/reports.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                   Reports
                               </a>
                               <?php endif; ?>
                    </div>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:items-center">
                    <div class="ml-3 relative">
                        <div class="flex items-center space-x-4">
                            <span class="text-gray-700 text-sm">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            <a href="<?php echo $base_path; ?>logout.php" class="text-gray-500 hover:text-gray-700 text-sm font-medium">
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile menu -->
    <div class="sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
                               <a href="<?php echo $base_path; ?>index.php" class="bg-indigo-50 border-indigo-500 text-indigo-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Dashboard</a>
                   <?php if (isAdmin()): ?>
                   <a href="<?php echo $base_path; ?>modules/products.php" class="border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Products</a>
                   <a href="<?php echo $base_path; ?>modules/vendors.php" class="border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Vendors</a>
                   <a href="<?php echo $base_path; ?>modules/purchases.php" class="border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Purchases</a>
                   <?php endif; ?>
                   <a href="<?php echo $base_path; ?>modules/sales.php" class="border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">POS Sales</a>
                   <?php if (isAdmin()): ?>
                   <a href="<?php echo $base_path; ?>modules/reports.php" class="border-transparent text-gray-500 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-700 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">Reports</a>
                   <?php endif; ?>
        </div>
    </div>
