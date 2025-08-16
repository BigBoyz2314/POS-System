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
    <title>Acumen Retail</title>
    <script>(function(){try{var theme=localStorage.getItem('theme');var prefersDark=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;var el=document.documentElement;el.classList.remove('dark');if(theme==='dark'||(!theme&&prefersDark)){el.classList.add('dark');}}catch(e){}})();</script>
    <?php
        // Prefer local Tailwind build if present; fallback to CDN otherwise
        $tailwindLocalHref = $base_path . 'assets/tailwind.css';
        $tailwindLocalFs = __DIR__ . '/../assets/tailwind.css';
        if (file_exists($tailwindLocalFs)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($tailwindLocalHref) . '">';
        } else {
            echo '<script>window.tailwind = window.tailwind || {}; window.tailwind.config = { darkMode: "class" };</script>';
            echo '<script src="https://cdn.tailwindcss.com"></script>';
        }
    ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/fontawesome.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/whiteboard-semibold.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/thumbprint-light.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/slab-press-regular.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/slab-regular.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/sharp-duotone-thin.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/sharp-duotone-solid.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/sharp-duotone-regular.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/sharp-duotone-light.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/sharp-thin.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/sharp-solid.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/sharp-regular.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/sharp-light.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/notdog-duo-solid.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/notdog-solid.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/jelly-fill-regular.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/jelly-duo-regular.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/jelly-regular.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/etch-solid.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/duotone-thin.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/duotone.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/duotone-regular.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/duotone-light.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/thin.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/solid.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/regular.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/light.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/brands.css">
    <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v7.0.0/css/chisel-regular.css">
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/ui.css">
</head>

<body class="bg-gray-100 dark:bg-gray-900">
    <!-- Navigation -->
    <nav class="bg-white dark:bg-gray-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <button id="mobileMenuToggle" class="sm:hidden inline-flex items-center justify-center w-9 h-9 mr-2 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700" aria-expanded="false" aria-controls="mobileMenu" title="Menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="flex-shrink-0 flex items-center min-w-0">
                        <h1 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100 truncate">Acumen Retail</h1>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="<?php echo $base_path; ?>index.php"
                            class="text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 text-sm font-medium">
                            <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                        </a>
                        <?php if (isAdmin()): ?>
                        <a href="<?php echo $base_path; ?>modules/products.php"
                            class="text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 text-sm font-medium">
                            <i class="fas fa-box mr-2"></i>Products
                        </a>
                        <a href="<?php echo $base_path; ?>modules/vendors.php"
                            class="text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 text-sm font-medium">
                            <i class="fas fa-truck mr-2"></i>Vendors
                        </a>
                        <a href="<?php echo $base_path; ?>modules/purchases.php"
                            class="text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 text-sm font-medium">
                            <i class="fas fa-shopping-cart mr-2"></i>Purchases
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo $base_path; ?>modules/sales.php"
                            class="text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 text-sm font-medium">
                            <i class="fas fa-cash-register mr-2"></i>POS Sales
                        </a>
                        <?php if (isAdmin()): ?>
                        <a href="<?php echo $base_path; ?>modules/reports.php"
                            class="text-gray-500 dark:text-gray-300 hover:text-gray-700 dark:hover:text-white inline-flex items-center px-1 pt-1 text-sm font-medium">
                            <i class="fas fa-chart-bar mr-2"></i>Reports
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:items-center">
                    <div class="ml-3 relative">
                        <div class="flex items-center space-x-4">
                            <button id="darkModeToggle" type="button" class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500" aria-pressed="false" title="Toggle dark mode">
                                <i class="fas fa-moon"></i>
                            </button>
                            <span class="text-gray-700 text-sm">Welcome,
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            <a href="<?php echo $base_path; ?>logout.php"
                                class="text-gray-500 hover:text-gray-700 text-sm font-medium">
                                <i class="fas fa-sign-out-alt mr-1"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile menu (collapsible) -->
    <div id="mobileMenu" class="hidden sm:hidden max-h-[60vh] overflow-y-auto">
        <div class="pt-2 pb-3 space-y-1">
            <a href="<?php echo $base_path; ?>index.php"
                class="bg-indigo-50 dark:bg-indigo-900/20 border-indigo-500 text-indigo-700 dark:text-indigo-300 block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
            <?php if (isAdmin()): ?>
            <a href="<?php echo $base_path; ?>modules/products.php"
                class="border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                <i class="fas fa-box mr-2"></i>Products</a>
            <a href="<?php echo $base_path; ?>modules/vendors.php"
                class="border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                <i class="fas fa-truck mr-2"></i>Vendors</a>
            <a href="<?php echo $base_path; ?>modules/purchases.php"
                class="border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                <i class="fas fa-shopping-cart mr-2"></i>Purchases</a>
            <?php endif; ?>
            <a href="<?php echo $base_path; ?>modules/sales.php"
                class="border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                <i class="fas fa-cash-register mr-2"></i>POS Sales</a>
            <?php if (isAdmin()): ?>
            <a href="<?php echo $base_path; ?>modules/reports.php"
                class="border-transparent text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:border-gray-300 hover:text-gray-700 dark:hover:text-white block pl-3 pr-4 py-2 border-l-4 text-base font-medium">
                <i class="fas fa-chart-bar mr-2"></i>Reports</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function(){
        var btn = document.getElementById('mobileMenuToggle');
        var menu = document.getElementById('mobileMenu');
        if (btn && menu) {
            // restore state
            try { var open = localStorage.getItem('mobileMenuOpen') === 'true'; if (open) { menu.classList.remove('hidden'); btn.setAttribute('aria-expanded','true'); } } catch(e) {}
            btn.addEventListener('click', function(){
                var isOpen = !menu.classList.contains('hidden');
                if (isOpen) { menu.classList.add('hidden'); this.setAttribute('aria-expanded','false'); }
                else { menu.classList.remove('hidden'); this.setAttribute('aria-expanded','true'); }
                try { localStorage.setItem('mobileMenuOpen', String(!isOpen)); } catch(e) {}
            });
            // Close menu when a link is clicked (mobile UX)
            menu.addEventListener('click', function(e){
                var target = e.target.closest('a');
                if (!target) return;
                menu.classList.add('hidden');
                btn.setAttribute('aria-expanded','false');
                try { localStorage.setItem('mobileMenuOpen', 'false'); } catch(e) {}
            });
        }
    })();
    </script>