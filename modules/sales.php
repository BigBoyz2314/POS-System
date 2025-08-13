<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require login
requireLogin();

$message = '';
$error = '';

// Handle messages from redirects
if (isset($_GET['invoice']) && $_GET['invoice'] == '1') {
    if (isset($_SESSION['last_sale'])) {
        $message = 'Sale completed successfully! Sale ID: ' . $_SESSION['last_sale']['id'];
    }
}

if (isset($_GET['error']) && $_GET['error'] == '1') {
    if (isset($_SESSION['error_message'])) {
        $error = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
    } else {
        $error = 'An error occurred during the sale. Your cart has been preserved.';
    }
}

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'checkout') {
    $cart_items = json_decode($_POST['cart_items'], true);
    $subtotal_amount = floatval($_POST['subtotal_amount']);
    $tax_amount = floatval($_POST['tax_amount']);
    $total_amount = floatval($_POST['total_amount']);
    $discount_amount = floatval($_POST['discount_amount']);
    $payment_method = $_POST['payment_method'];
    $cash_amount = floatval($_POST['cash_amount']);
    $card_amount = floatval($_POST['card_amount']);
    
    // Calculate final amount after discount
    $final_amount = $total_amount - $discount_amount;
    
    if (empty($cart_items)) {
        $error = 'Cart is empty.';
    } elseif ($final_amount < 0) {
        $error = 'Discount cannot be greater than total amount. Your cart has been preserved.';
    } elseif (($cash_amount + $card_amount) < $final_amount) {
        $error = 'Payment amount is less than the final amount. Your cart has been preserved.';
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert sale record with payment details
            $stmt = mysqli_prepare($conn, "INSERT INTO sales (total_amount, subtotal_amount, tax_amount, discount_amount, payment_method, cash_amount, card_amount, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Failed to prepare sales insert statement: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "ddddssdi", $total_amount, $subtotal_amount, $tax_amount, $discount_amount, $payment_method, $cash_amount, $card_amount, $_SESSION['user_id']);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to execute sales insert: " . mysqli_stmt_error($stmt));
            }
            $sale_id = mysqli_insert_id($conn);
            if (!$sale_id) {
                throw new Exception("Failed to get sale ID after insert");
            }
            
            // Insert sale items and update stock
            foreach ($cart_items as $item) {
                // Calculate tax for this item
                $item_total = $item['price'] * $item['quantity']; // Tax-inclusive total
                $item_subtotal = $item['tax_rate'] > 0 ? ($item_total / (1 + $item['tax_rate'] / 100)) : $item_total;
                $item_tax = $item_total - $item_subtotal;
                
                // Insert sale item
                $stmt = mysqli_prepare($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, price, tax_rate, tax_amount) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Failed to prepare sale_items insert statement: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt, "iidddd", $sale_id, $item['id'], $item['quantity'], $item['price'], $item['tax_rate'], $item_tax);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to execute sale_items insert: " . mysqli_stmt_error($stmt));
                }
                
                // Update product stock with stock check to prevent overselling
                $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                if (!$stmt) {
                    throw new Exception("Failed to prepare stock update statement: " . mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt, "iii", $item['quantity'], $item['id'], $item['quantity']);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to execute stock update: " . mysqli_stmt_error($stmt));
                }
                
                // Check if the update actually affected a row (stock was sufficient)
                if (mysqli_affected_rows($conn) == 0) {
                    throw new Exception("Insufficient stock for product: " . $item['name'] . ". Current stock may have changed.");
                }
            }
            
            mysqli_commit($conn);
            $message = 'Sale completed successfully! Sale ID: ' . $sale_id;
            
            // Store sale data for invoice with unique identifier
            $sale_key = 'sale_' . $sale_id . '_' . time() . '_' . $_SESSION['user_id'];
            $_SESSION['last_sale'] = [
                'id' => $sale_id,
                'items' => $cart_items,
                'total' => $subtotal_amount,
                'tax' => $tax_amount,
                'discount' => $discount_amount,
                'final' => $final_amount,
                'payment_method' => $payment_method,
                'cash_amount' => $cash_amount,
                'card_amount' => $card_amount,
                'balance' => ($cash_amount + $card_amount) - $final_amount,
                'sale_key' => $sale_key
            ];
            // Use PRG pattern to prevent duplicate submission on refresh
            header('Location: sales.php?invoice=1');
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = 'Error processing sale: ' . $e->getMessage() . '. Your cart has been preserved. Please try again.';
            
            // Log the error for debugging
            error_log("Sale processing error: " . $e->getMessage());
            error_log("Cart items: " . json_encode($cart_items));
            
            // Store cart data in session to restore it after error
            $_SESSION['error_cart'] = $cart_items;
            $_SESSION['error_payment_data'] = [
                'discount_amount' => $discount_amount,
                'payment_method' => $payment_method,
                'cash_amount' => $cash_amount,
                'card_amount' => $card_amount
            ];
            $_SESSION['error_message'] = $error_message;
            // Also store in localStorage for persistence across refreshes
            echo "<script>localStorage.setItem('pos_cart', '" . json_encode($cart_items) . "');</script>";
            // Redirect to avoid form resubmission on refresh
            header('Location: sales.php?error=1');
            exit;
        }
    }
    
    // If there's an error, store cart data to restore it
    if ($error && !empty($cart_items)) {
        $_SESSION['error_cart'] = $cart_items;
        $_SESSION['error_payment_data'] = [
            'discount_amount' => $discount_amount,
            'payment_method' => $payment_method,
            'cash_amount' => $cash_amount,
            'card_amount' => $card_amount
        ];
        $_SESSION['error_message'] = $error;
        // Also store in localStorage for persistence across refreshes
        echo "<script>localStorage.setItem('pos_cart', '" . json_encode($cart_items) . "');</script>";
        // Redirect to avoid resubmission on refresh
        header('Location: sales.php?error=1');
        exit;
    }
}

include '../includes/header.php';
?>

<div class="h-screen flex flex-col overflow-hidden">
    <!-- Toggle Header Button - Always Visible -->
    <div class="flex-shrink-0 p-2 bg-gray-100 border-b">
        <button id="toggleHeaderBtn" class="bg-gray-500 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">
            Hide Header
        </button>
    </div>

    <!-- Header with messages -->
    <div id="headerSection" class="flex-shrink-0 p-3">
        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-3">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded mb-3">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main content area -->
    <div class="flex-1 px-3 pb-3 min-h-0">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 h-full">
        <!-- Product Search and Selection -->
        <div class="bg-white rounded-lg shadow-md p-4 flex flex-col min-h-0">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Product Search</h2>
            
            <div class="mb-4">
                <input type="text" id="searchInput" placeholder="Search by product name or SKU..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div id="searchResults" class="overflow-y-auto border border-gray-200 rounded-md p-2 flex-1">
                <!-- Search results will be populated here -->
            </div>
        </div>

        <!-- Shopping Cart -->
        <div class="bg-white rounded-lg shadow-md p-4 flex flex-col min-h-0">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Shopping Cart</h2>
            
            <div id="cartItems" class="overflow-y-auto mb-4 flex-1">
                <!-- Cart items will be populated here -->
            </div>
            
            <!-- Cart Totals -->
            <div class="border-t pt-4 mb-4 flex-shrink-0">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Subtotal:</span>
                    <span id="cartTotal" class="text-sm font-medium text-gray-900">PKR 0.00</span>
                </div>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-gray-700">Tax:</span>
                    <span id="cartTax" class="text-sm font-medium text-red-600">PKR 0.00</span>
                </div>
                <div class="flex justify-between items-center border-t pt-2">
                    <span class="text-lg font-semibold text-gray-900">Cart Total:</span>
                    <span id="cartTotalWithTax" class="text-xl font-bold text-gray-900">PKR 0.00</span>
                </div>
            </div>
            
            <div class="border-t pt-4 flex-shrink-0">
                <!-- Discount Section -->
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount (PKR)</label>
                    <input type="number" id="discountInput" step="0.01" min="0" value="0" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           onchange="updateFinalTotal()">
                </div>
                
                <!-- Final Total -->
                <div class="flex justify-between items-center mb-3">
                    <span class="text-lg font-semibold text-gray-900">Final Total:</span>
                    <span id="finalTotal" class="text-2xl font-bold text-blue-600">PKR 0.00</span>
                </div>
                
                <button onclick="showPaymentModal()" id="checkoutBtn" disabled
                        class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded">
                    Proceed to Payment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Details</h3>
            
            <!-- Payment Method -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                <select id="paymentMethod" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updatePaymentFields()">
                    <option value="cash">Cash Only</option>
                    <option value="card">Card Only</option>
                    <option value="mixed">Cash + Card</option>
                </select>
            </div>
            
            <!-- Cash Amount -->
            <div id="cashSection" class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Cash Amount (PKR)</label>
                <input type="number" id="cashAmount" step="0.01" min="0" value="0" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                       onchange="calculateBalance()">
            </div>
            
            <!-- Card Amount -->
            <div id="cardSection" class="mb-4 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">Card Amount (PKR)</label>
                <input type="number" id="cardAmount" step="0.01" min="0" value="0" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                       onchange="calculateBalance()">
            </div>
            
            <!-- Balance/Change -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Balance/Change</label>
                <input type="text" id="balanceAmount" readonly 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
            </div>
            
            <div class="flex justify-end space-x-4">
                <button onclick="hidePaymentModal()" 
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Cancel
                </button>
                <button id="completeSaleBtn" 
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Complete Sale
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Invoice Modal -->
<div id="invoiceModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-5 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div id="invoiceContent" class="text-center">
                <!-- Invoice content will be populated here -->
            </div>
            <div class="flex justify-center space-x-4 mt-6">
                <button onclick="printInvoice()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Print Invoice
                </button>
                <button onclick="closeInvoice()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden div for print content -->
<div id="printInvoiceContent" style="display: none !important; position: absolute !important; left: -9999px !important; top: -9999px !important; visibility: hidden !important; opacity: 0 !important; z-index: -9999 !important;">
    <!-- Print content will be populated here -->
</div>

<script>
let cart = [];
let searchTimeout;
let headerHidden = false;

// Save cart to localStorage
function saveCartToStorage() {
    localStorage.setItem('pos_cart', JSON.stringify(cart));
}

// Load cart from localStorage
function loadCartFromStorage() {
    const savedCart = localStorage.getItem('pos_cart');
    if (savedCart) {
        try {
            cart = JSON.parse(savedCart);
            updateCartDisplay();
        } catch (e) {
            console.error('Error loading cart from storage:', e);
            cart = [];
        }
    }
}

// Clear cart from localStorage
function clearCartFromStorage() {
    localStorage.removeItem('pos_cart');
}

// Toggle header visibility - Define this first since it's called by the button
function toggleHeader() {
    const headerSection = document.getElementById('headerSection');
    const toggleBtn = document.getElementById('toggleHeaderBtn');
    const navHeader = document.querySelector('nav');
    
    if (headerHidden) {
        // Show header
        headerSection.classList.remove('hidden');
        navHeader.classList.remove('hidden');
        toggleBtn.textContent = 'Hide Header';
        headerHidden = false;
        localStorage.setItem('headerHidden', 'false');
    } else {
        // Hide header
        headerSection.classList.add('hidden');
        navHeader.classList.add('hidden');
        toggleBtn.textContent = 'Show Header';
        headerHidden = true;
        localStorage.setItem('headerHidden', 'true');
    }
}

// Load header state from localStorage
function loadHeaderState() {
    const savedState = localStorage.getItem('headerHidden');
    if (savedState === 'true') {
        headerHidden = true;
        toggleHeader(); // Apply the saved state
    }
}

// Load all products initially
function loadAllProducts() {
    console.log('Loading all products...');
    console.log('Making AJAX request to ajax_search_products.php');
    
    $.ajax({
        url: 'ajax_search_products.php',
        method: 'POST',
        data: { query: '' },
        success: function(response) {
            console.log('Products loaded successfully');
            console.log('Response length:', response.length);
            console.log('Response preview:', response.substring(0, 200));
            document.getElementById('searchResults').innerHTML = response;
        },
        error: function(xhr, status, error) {
            console.error('Error loading products:', error);
            console.error('Status:', status);
            console.error('Response text:', xhr.responseText);
            document.getElementById('searchResults').innerHTML = '<p class="text-red-600">Error loading products: ' + error + '</p>';
        }
    });
}



// Add product to cart
function addToCart(product) {
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        if (existingItem.quantity < product.stock) {
            existingItem.quantity++;
        } else {
            alert('Cannot add more items. Stock limit reached.');
            return;
        }
    } else {
        if (product.stock > 0) {
            cart.push({
                id: product.id,
                name: product.name,
                price: parseFloat(product.price),
                tax_rate: parseFloat(product.tax_rate) || 0,
                quantity: 1,
                stock: product.stock
            });
        } else {
            alert('Product is out of stock.');
            return;
        }
    }
    
    updateCartDisplay();
    saveCartToStorage();
    // Don't clear the search input or results - keep products visible
}

// Refresh product stock data periodically to handle concurrent sales
function refreshProductStock() {
    if (cart.length > 0) {
        const productIds = cart.map(item => item.id);
        $.ajax({
            url: 'ajax_get_stock.php',
            method: 'POST',
            data: { product_ids: productIds },
            success: function(response) {
                if (response.success) {
                    // Update cart items with fresh stock data
                    cart.forEach(item => {
                        const freshStock = response.stock_data[item.id];
                        if (freshStock !== undefined) {
                            item.stock = freshStock;
                            if (item.quantity > freshStock) {
                                item.quantity = freshStock;
                                alert(`Stock updated for ${item.name}. Quantity adjusted to available stock.`);
                            }
                        }
                    });
                    updateCartDisplay();
                }
            }
        });
    }
}

// Update quantity in cart
function updateQuantity(index, change) {
    const item = cart[index];
    const newQuantity = item.quantity + change;
    
    if (newQuantity <= 0) {
        cart.splice(index, 1);
    } else if (newQuantity <= item.stock) {
        item.quantity = newQuantity;
    } else {
        alert('Cannot add more items. Stock limit reached.');
        return;
    }
    
    updateCartDisplay();
    saveCartToStorage();
}

// Update cart display
function updateCartDisplay() {
    const cartContainer = document.getElementById('cartItems');
    const totalElement = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (cart.length === 0) {
        cartContainer.innerHTML = '<p class="text-gray-500 text-center">Cart is empty</p>';
        totalElement.textContent = 'PKR 0.00';
        document.getElementById('cartTax').textContent = 'PKR 0.00';
        document.getElementById('cartTotalWithTax').textContent = 'PKR 0.00';
        checkoutBtn.disabled = true;
        updateFinalTotal();
        return;
    }
    
    let total = 0;
    let cartHTML = '';
    
    cart.forEach((item, index) => {
        // Calculate tax backwards from tax-inclusive price
        const itemTotal = item.price * item.quantity; // This is tax-inclusive total
        const itemSubtotal = item.tax_rate > 0 ? (itemTotal / (1 + item.tax_rate / 100)) : itemTotal;
        const itemTax = itemTotal - itemSubtotal;
        total += itemSubtotal; // Add subtotal (without tax) to cart total
        
        cartHTML += `
            <div class="flex items-center justify-between p-2 border rounded mb-2">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center space-x-1">
                        <h3 class="font-medium text-gray-900 text-sm truncate">${item.name}</h3>
                        <span class="text-xs text-gray-400">|</span>
                        <span class="text-xs text-gray-500">x${item.quantity}</span>
                        <span class="text-xs text-gray-400">|</span>
                        <span class="text-xs text-gray-500">PKR ${item.price.toFixed(2)} each (inc. tax)</span>
                        ${item.tax_rate > 0 ? `<span class="text-xs text-gray-400">|</span><span class="text-xs text-red-500">${item.tax_rate}% tax</span>` : ''}
                    </div>
                </div>
                <div class="flex items-center space-x-1 flex-shrink-0 ml-2">
                    <button onclick="updateQuantity(${index}, -1)" class="bg-red-500 hover:bg-red-700 text-white px-1 py-1 rounded text-xs">-</button>
                    <span class="text-gray-900 text-sm px-1">${item.quantity}</span>
                    <button onclick="updateQuantity(${index}, 1)" class="bg-green-500 hover:bg-green-700 text-white px-1 py-1 rounded text-xs">+</button>
                </div>
            </div>
        `;
    });
    
    cartContainer.innerHTML = cartHTML;
    
    // Calculate tax backwards from tax-inclusive prices
    let totalTax = 0;
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity; // Tax-inclusive total
        const itemSubtotal = item.tax_rate > 0 ? (itemTotal / (1 + item.tax_rate / 100)) : itemTotal;
        const itemTax = itemTotal - itemSubtotal;
        totalTax += itemTax;
    });
    
    const totalWithTax = total + totalTax;
    
    totalElement.textContent = 'PKR ' + total.toFixed(2);
    document.getElementById('cartTax').textContent = 'PKR ' + totalTax.toFixed(2);
    document.getElementById('cartTotalWithTax').textContent = 'PKR ' + totalWithTax.toFixed(2);
    checkoutBtn.disabled = false;
    updateFinalTotal();
}

// Update final total after discount
function updateFinalTotal() {
    const total = parseFloat(document.getElementById('cartTotal').textContent.replace('PKR ', '')) || 0;
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    
    // Calculate tax backwards from tax-inclusive prices
    let totalTax = 0;
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity; // Tax-inclusive total
        const itemSubtotal = item.tax_rate > 0 ? (itemTotal / (1 + item.tax_rate / 100)) : itemTotal;
        const itemTax = itemTotal - itemSubtotal;
        totalTax += itemTax;
    });
    
    const subtotal = total;
    const finalTotal = Math.max(0, subtotal + totalTax - discount);
    
    document.getElementById('finalTotal').textContent = 'PKR ' + finalTotal.toFixed(2);
}

// Update payment fields based on method
function updatePaymentFields() {
    const method = document.getElementById('paymentMethod').value;
    const currentTotal = parseFloat(document.getElementById('finalTotal').textContent.replace('PKR ', ''));
    
    if (method === 'cash') {
        document.getElementById('cashSection').classList.remove('hidden');
        document.getElementById('cardSection').classList.add('hidden');
        document.getElementById('cashAmount').value = currentTotal.toFixed(2);
        document.getElementById('cardAmount').value = '0';
    } else if (method === 'card') {
        document.getElementById('cashSection').classList.add('hidden');
        document.getElementById('cardSection').classList.remove('hidden');
        document.getElementById('cashAmount').value = '0';
        document.getElementById('cardAmount').value = currentTotal.toFixed(2);
    } else if (method === 'mixed') {
        document.getElementById('cashSection').classList.remove('hidden');
        document.getElementById('cardSection').classList.remove('hidden');
        document.getElementById('cashAmount').value = '0';
        document.getElementById('cardAmount').value = '0';
    }
    
    calculateBalance();
}

// Payment modal functions
function showPaymentModal() {
    // Set initial values
    const modalTotal = parseFloat(document.getElementById('finalTotal').textContent.replace('PKR ', ''));
    document.getElementById('cashAmount').value = modalTotal.toFixed(2);
    document.getElementById('cardAmount').value = '0.00';
    calculateBalance();
    
    document.getElementById('paymentModal').classList.remove('hidden');
}

function hidePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
}

// Calculate balance
function calculateBalance() {
    const balanceTotal = parseFloat(document.getElementById('finalTotal').textContent.replace('PKR ', ''));
    const cashAmount = parseFloat(document.getElementById('cashAmount').value) || 0;
    const cardAmount = parseFloat(document.getElementById('cardAmount').value) || 0;
    const totalPaid = cashAmount + cardAmount;
    const balance = totalPaid - balanceTotal;
    
    document.getElementById('balanceAmount').value = 'PKR ' + balance.toFixed(2);
    
    // Enable/disable checkout button - Fixed logic for equal amounts
    const completeSaleBtn = document.getElementById('completeSaleBtn');
    if (totalPaid >= balanceTotal && totalPaid > 0) {
        completeSaleBtn.disabled = false;
        completeSaleBtn.classList.remove('bg-gray-400');
        completeSaleBtn.classList.add('bg-green-600', 'hover:bg-green-700');
    } else {
        completeSaleBtn.disabled = true;
        completeSaleBtn.classList.add('bg-gray-400');
        completeSaleBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
    }
}

// Process payment function
function processPayment() {
    // Prevent double-click submission
    const checkoutBtn = document.getElementById('completeSaleBtn');
    if (checkoutBtn.disabled) {
        return;
    }
    checkoutBtn.disabled = true;
    checkoutBtn.textContent = 'Processing...';
    
    if (cart.length === 0) {
        alert('Cart is empty.');
        checkoutBtn.disabled = false;
        checkoutBtn.textContent = 'Complete Sale';
        return;
    }
    
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const paymentMethod = document.getElementById('paymentMethod').value;
    const cashAmount = parseFloat(document.getElementById('cashAmount').value) || 0;
    const cardAmount = parseFloat(document.getElementById('cardAmount').value) || 0;
    
    // Get the final total from the displayed value (same as updateFinalTotal function)
    const paymentTotal = parseFloat(document.getElementById('finalTotal').textContent.replace('PKR ', '')) || 0;
    const totalPaid = cashAmount + cardAmount;
    
    if (totalPaid < paymentTotal) {
        alert('Payment amount is less than the final total.');
        checkoutBtn.disabled = false;
        checkoutBtn.textContent = 'Complete Sale';
        return;
    }
    
    if (totalPaid === 0) {
        alert('Please enter payment amounts.');
        checkoutBtn.disabled = false;
        checkoutBtn.textContent = 'Complete Sale';
        return;
    }
    
    // Hide payment modal
    hidePaymentModal();
    
    // Calculate totals for submission (same as updateFinalTotal function)
    const total = parseFloat(document.getElementById('cartTotal').textContent.replace('PKR ', '')) || 0;
    
    // Calculate tax backwards from tax-inclusive prices
    let totalTax = 0;
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity; // Tax-inclusive total
        const itemSubtotal = item.tax_rate > 0 ? (itemTotal / (1 + item.tax_rate / 100)) : itemTotal;
        const itemTax = itemTotal - itemSubtotal;
        totalTax += itemTax;
    });
    
    const subtotal = total;
    const finalTotal = Math.max(0, subtotal + totalTax - discount);
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="checkout">
        <input type="hidden" name="cart_items" value='${JSON.stringify(cart)}'>
        <input type="hidden" name="subtotal_amount" value="${subtotal}">
        <input type="hidden" name="tax_amount" value="${totalTax}">
        <input type="hidden" name="total_amount" value="${finalTotal}">
        <input type="hidden" name="discount_amount" value="${discount}">
        <input type="hidden" name="payment_method" value="${paymentMethod}">
        <input type="hidden" name="cash_amount" value="${cashAmount}">
        <input type="hidden" name="card_amount" value="${cardAmount}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Show invoice after successful sale
function showInvoice(saleData) {
    const invoiceContent = document.getElementById('invoiceContent');
    const printInvoiceContent = document.getElementById('printInvoiceContent');
    const currentDate = new Date().toLocaleDateString();
    const currentTime = new Date().toLocaleTimeString();
    
    let itemsHTML = '';
    saleData.items.forEach(item => {
        const itemTotal = item.price * item.quantity; // Tax-inclusive total
        const taxRate = parseFloat(item.tax_rate) || 0;
        const itemSubtotal = taxRate > 0 ? (itemTotal / (1 + taxRate / 100)) : itemTotal;
        const itemTax = itemTotal - itemSubtotal;
        
        itemsHTML += `
            <tr class="border-b">
                <td class="py-1 text-left text-xs" style="padding: 2px; font-size: 12px;">${item.name}</td>
                <td class="py-1 text-center text-xs" style="padding: 2px; font-size: 12px;">${item.quantity}</td>
                <td class="py-1 text-right text-xs" style="padding: 2px; font-size: 12px;">${item.price.toFixed(2)}</td>
                <td class="py-1 text-right text-xs" style="padding: 2px; font-size: 12px;">${itemTotal.toFixed(2)}</td>
            </tr>
            <tr><td colspan="4" class="py-0 text-right text-xs" style="padding: 1px 2px; font-size: 10px; color: #666;">Tax: PKR ${itemTax.toFixed(2)} (${taxRate}%)</td></tr>
            <tr>
                <td colspan="4" class="py-0" style="padding: 0; font-size: 10px; color: #666;">
                    <div style="border-bottom: 1px solid #eee; margin: 1px 0;"></div>
                </td>
            </tr>
        `;
    });
    
    const invoiceHTML = `
        <div class="text-left" style="font-size: 14px; line-height: 1.3; width: 100%; margin: 0; padding: 0 3mm;">
            <div class="text-center mb-1" style="margin-bottom: 3px;">
                <h2 class="text-lg font-bold text-gray-900" style="font-size: 18px; margin: 0;">INVOICE</h2>
                <p class="text-xs text-gray-600" style="font-size: 12px; margin: 2px 0;">Sale #${saleData.id}</p>
                <p class="text-xs text-gray-600" style="font-size: 12px; margin: 2px 0;">${currentDate} ${currentTime}</p>
            </div>
            
            <div class="mb-1" style="margin-bottom: 3px;">
                <table class="w-full text-xs" style="font-size: 12px; margin: 2px 0; table-layout: fixed;">
                    <thead>
                        <tr class="border-b border-gray-300">
                            <th class="py-1 text-left" style="padding: 2px; font-size: 12px; width: 45%;">Item</th>
                            <th class="py-1 text-center" style="padding: 2px; font-size: 12px; width: 15%;">Qty</th>
                            <th class="py-1 text-right" style="padding: 2px; font-size: 12px; width: 20%;">Price</th>
                            <th class="py-1 text-right" style="padding: 2px; font-size: 12px; width: 20%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHTML}
                    </tbody>
                </table>
            </div>
            
            <div class="border-t pt-1" style="margin-top: 3px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Subtotal:</td>
                        <td style="text-align: right; padding: 1px 2px; font-size: 12px;">PKR ${saleData.total.toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Tax:</td>
                        <td style="text-align: right; padding: 1px 2px; font-size: 12px;">PKR ${saleData.tax ? saleData.tax.toFixed(2) : '0.00'}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Discount:</td>
                        <td style="text-align: right; padding: 1px 2px; font-size: 12px;">-PKR ${saleData.discount.toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; padding: 1px 2px; font-size: 14px; font-weight: bold;">Total:</td>
                        <td style="text-align: right; padding: 1px 2px; font-size: 14px; font-weight: bold;">PKR ${saleData.final.toFixed(2)}</td>
                    </tr>
                </table>
            </div>
            
            <div class="border-t pt-1 mt-1" style="margin-top: 3px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: left; padding: 1px 2px; font-size: 12px;">Payment Method:</td>
                        <td style="text-align: right; padding: 1px 2px; font-size: 12px;">${saleData.payment_method.toUpperCase()}</td>
                    </tr>
                    ${saleData.cash_amount > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 12px;">Cash:</td><td style="text-align: right; padding: 1px 2px; font-size: 12px;">PKR ${saleData.cash_amount.toFixed(2)}</td></tr>` : ''}
                    ${saleData.card_amount > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 12px;">Card:</td><td style="text-align: right; padding: 1px 2px; font-size: 12px;">PKR ${saleData.card_amount.toFixed(2)}</td></tr>` : ''}
                    ${saleData.balance > 0 ? `<tr><td style="text-align: left; padding: 1px 2px; font-size: 12px;">Change:</td><td style="text-align: right; padding: 1px 2px; font-size: 12px;">PKR ${saleData.balance.toFixed(2)}</td></tr>` : ''}
                </table>
            </div>
            
            <div class="text-center mt-1" style="margin-top: 3px;">
                <p class="text-xs text-gray-600" style="font-size: 12px; margin: 2px 0;">Thank you for your purchase!</p>
            </div>
        </div>
    `;
    
    // Set content for modal only
    invoiceContent.innerHTML = invoiceHTML;
    
    document.getElementById('invoiceModal').classList.remove('hidden');
}

// Print invoice
function printInvoice() {
    try {
        // Get the invoice content from the modal instead of the hidden div
        const invoiceContent = document.getElementById('invoiceContent').innerHTML;
        
        if (!invoiceContent) {
            alert('No invoice content found. Please try again.');
            return;
        }
        
        // Create a new window with the invoice
        const printWindow = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
        
        if (!printWindow) {
            alert('Popup blocked! Please allow popups for this site and try again.');
            return;
        }
        
        // Create the HTML content as a string to avoid ending tag interpretation issues
        const printHTML = '<!DOCTYPE html>' +
            '<html>' +
            '<head>' +
            '<title>Invoice</title>' +
            '<style>' +
            'body { font-family: Arial, sans-serif; margin: 0; padding: 2mm; font-size: 11px; line-height: 1.2; width: 76mm; overflow-x: hidden; }' +
            'table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }' +
            'th, td { padding: 1px; text-align: left; word-wrap: break-word; }' +
            'th:nth-child(1), td:nth-child(1) { width: 35%; }' +
            'th:nth-child(2), td:nth-child(2) { width: 15%; text-align: center; }' +
            'th:nth-child(3), td:nth-child(3) { width: 25%; text-align: right; }' +
            'th:nth-child(4), td:nth-child(4) { width: 25%; text-align: right; }' +
            '.text-center { text-align: center; }' +
            '.text-right { text-align: right; }' +
            '.border-t { border-top: 1px solid #ccc; }' +
            '.font-bold { font-weight: bold; }' +
            '@media print { body { margin: 0; padding: 2mm; width: 76mm; } @page { margin: 0; size: 80mm auto; } }' +
            '</style>' +
            '</head>' +
            '<body>' +
            '<div class="print-wrapper" style="position: relative; width: 100%;">' +
            invoiceContent +
            '</div>' +
            '<' + 'script' + '>' +
            'window.onload = function() {' +
            '    window.print();' +
            '    window.close();' +
            '};' +
            '</' + 'script' + '>' +
            '</body>' +
            '</html>';
    
    // Write the content to the new window
    printWindow.document.write(printHTML);
    printWindow.document.close();
    } catch (error) {
        console.error('Error in printInvoice:', error);
        alert('Error opening print window: ' + error.message);
    }
}

// Close invoice modal
function closeInvoice() {
    document.getElementById('invoiceModal').classList.add('hidden');
    cart = [];
    clearCartFromStorage();
    updateCartDisplay();
    document.getElementById('discountInput').value = '0';
    updateFinalTotal();
}



// Initialize cart display
updateCartDisplay();

// Initialize payment fields
updatePaymentFields();

// Test if jQuery is loaded
if (typeof $ === 'undefined') {
    console.error('jQuery is not loaded!');
    document.getElementById('searchResults').innerHTML = '<p class="text-red-600">jQuery not loaded. Please refresh the page.</p>';
} else {
    console.log('jQuery is loaded successfully');
    // Load all products initially
    loadAllProducts();
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing...');
    
    // Load cart from localStorage
    loadCartFromStorage();
    
    // Add event listener for toggle header button
    const toggleBtn = document.getElementById('toggleHeaderBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleHeader);
        console.log('Toggle button event listener added');
    }
    
    // Add event listener for Complete Sale button
    const completeSaleBtn = document.getElementById('completeSaleBtn');
    if (completeSaleBtn) {
        completeSaleBtn.addEventListener('click', processPayment);
        console.log('Complete Sale button event listener added');
    }
    
    // Add event listener for search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length === 0) {
                loadAllProducts();
                return;
            }
            
            if (query.length < 2) {
                return;
            }
            
            searchTimeout = setTimeout(() => {
                $.ajax({
                    url: 'ajax_search_products.php',
                    method: 'POST',
                    data: { query: query },
                    success: function(response) {
                        document.getElementById('searchResults').innerHTML = response;
                    },
                    error: function() {
                        document.getElementById('searchResults').innerHTML = '<p class="text-red-600">Error searching products.</p>';
                    }
                });
            }, 300);
        });
        console.log('Search input event listener added');
    }
    
    console.log('Initialization complete');
    
    // Set up periodic stock refresh for concurrent sales handling
    setInterval(refreshProductStock, 30000); // Refresh every 30 seconds
});

// Load header state from session
loadHeaderState();

// Check if there's a successful sale to show invoice
<?php if (isset($_SESSION['last_sale'])): ?>
showInvoice(<?php echo json_encode($_SESSION['last_sale']); ?>);
<?php unset($_SESSION['last_sale']); ?>
<?php endif; ?>

// Restore cart if there was an error
<?php if (isset($_SESSION['error_cart'])): ?>
cart = <?php echo json_encode($_SESSION['error_cart']); ?>;
updateCartDisplay();
<?php if (isset($_SESSION['error_payment_data'])): ?>
document.getElementById('discountInput').value = '<?php echo $_SESSION['error_payment_data']['discount_amount']; ?>';
document.getElementById('paymentMethod').value = '<?php echo $_SESSION['error_payment_data']['payment_method']; ?>';
document.getElementById('cashAmount').value = '<?php echo $_SESSION['error_payment_data']['cash_amount']; ?>';
document.getElementById('cardAmount').value = '<?php echo $_SESSION['error_payment_data']['card_amount']; ?>';
updatePaymentFields();
<?php endif; ?>
updateFinalTotal();
<?php 
unset($_SESSION['error_cart']); 
unset($_SESSION['error_payment_data']); 
?>
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>