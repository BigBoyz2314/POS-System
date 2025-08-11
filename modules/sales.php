<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require login
requireLogin();

$message = '';
$error = '';

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'checkout') {
    $cart_items = json_decode($_POST['cart_items'], true);
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
            $stmt = mysqli_prepare($conn, "INSERT INTO sales (date, total_amount, discount_amount, payment_method, cash_amount, card_amount, user_id) VALUES (NOW(), ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ddssdi", $total_amount, $discount_amount, $payment_method, $cash_amount, $card_amount, $_SESSION['user_id']);
            mysqli_stmt_execute($stmt);
            $sale_id = mysqli_insert_id($conn);
            
            // Insert sale items and update stock
            foreach ($cart_items as $item) {
                // Insert sale item
                $stmt = mysqli_prepare($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "iidd", $sale_id, $item['id'], $item['quantity'], $item['price']);
                mysqli_stmt_execute($stmt);
                
                // Update product stock
                $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $item['quantity'], $item['id']);
                mysqli_stmt_execute($stmt);
            }
            
            mysqli_commit($conn);
            $message = 'Sale completed successfully! Sale ID: ' . $sale_id;
            
            // Store sale data for invoice
            $_SESSION['last_sale'] = [
                'id' => $sale_id,
                'items' => $cart_items,
                'total' => $total_amount,
                'discount' => $discount_amount,
                'final' => $final_amount,
                'payment_method' => $payment_method,
                'cash_amount' => $cash_amount,
                'card_amount' => $card_amount,
                'balance' => ($cash_amount + $card_amount) - $final_amount
            ];
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = 'Error processing sale. Your cart has been preserved. Please try again.';
            
            // Store cart data in session to restore it after error
            $_SESSION['error_cart'] = $cart_items;
            $_SESSION['error_payment_data'] = [
                'discount_amount' => $discount_amount,
                'payment_method' => $payment_method,
                'cash_amount' => $cash_amount,
                'card_amount' => $card_amount
            ];
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
    }
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">POS Sales</h1>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Product Search and Selection -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Product Search</h2>
            
            <div class="mb-4">
                <input type="text" id="searchInput" placeholder="Search by product name or SKU..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div id="searchResults" class="space-y-2 h-96 overflow-y-auto border border-gray-200 rounded-md p-2">
                <!-- Search results will be populated here -->
            </div>
        </div>

        <!-- Shopping Cart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Shopping Cart</h2>
            
            <div id="cartItems" class="space-y-2 mb-4">
                <!-- Cart items will be populated here -->
            </div>
            
            <!-- Cart Total -->
            <div class="border-t pt-4 mb-4">
                <div class="flex justify-between items-center">
                    <span class="text-lg font-semibold text-gray-900">Cart Total:</span>
                    <span id="cartTotal" class="text-xl font-bold text-gray-900">PKR 0.00</span>
                </div>
            </div>
            
            <div class="border-t pt-4">
                <!-- Discount Section -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Discount (PKR)</label>
                    <input type="number" id="discountInput" step="0.01" min="0" value="0" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           onchange="updateFinalTotal()">
                </div>
                
                <!-- Final Total -->
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-semibold text-gray-900">Final Total:</span>
                    <span id="finalTotal" class="text-2xl font-bold text-blue-600">PKR 0.00</span>
                </div>
                
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
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Balance/Change</label>
                    <input type="text" id="balanceAmount" readonly 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
                </div>
                
                <button onclick="processPayment()" id="checkoutBtn" disabled
                        class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded">
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

<!-- Print-only invoice content -->
<div id="printInvoice" class="hidden">
    <div id="printInvoiceContent">
        <!-- Print content will be populated here -->
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printInvoice, #printInvoice * {
        visibility: visible;
    }
    #printInvoice {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
        page-break-after: avoid;
        page-break-inside: avoid;
    }
    #printInvoiceContent {
        page-break-after: avoid;
        page-break-inside: avoid;
    }
    .no-print {
        display: none !important;
    }
}
</style>

<script>
let cart = [];
let searchTimeout;

// Load all products initially
function loadAllProducts() {
    $.ajax({
        url: 'ajax_search_products.php',
        method: 'POST',
        data: { query: '' },
        success: function(response) {
            document.getElementById('searchResults').innerHTML = response;
        },
        error: function() {
            document.getElementById('searchResults').innerHTML = '<p class="text-red-600">Error loading products.</p>';
        }
    });
}

// Search products
document.getElementById('searchInput').addEventListener('input', function() {
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
                quantity: 1,
                stock: product.stock
            });
        } else {
            alert('Product is out of stock.');
            return;
        }
    }
    
    updateCartDisplay();
    // Don't clear the search input or results - keep products visible
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
}

// Update cart display
function updateCartDisplay() {
    const cartContainer = document.getElementById('cartItems');
    const totalElement = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (cart.length === 0) {
        cartContainer.innerHTML = '<p class="text-gray-500 text-center">Cart is empty</p>';
        totalElement.textContent = '$0.00';
        checkoutBtn.disabled = true;
        updateFinalTotal();
        return;
    }
    
    let total = 0;
    let cartHTML = '';
    
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        cartHTML += `
            <div class="flex items-center justify-between p-3 border rounded">
                <div class="flex-1">
                    <h3 class="font-medium text-gray-900">${item.name}</h3>
                    <p class="text-sm text-gray-500">PKR ${item.price.toFixed(2)} x ${item.quantity}</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="updateQuantity(${index}, -1)" class="bg-red-500 hover:bg-red-700 text-white px-2 py-1 rounded text-sm">-</button>
                    <span class="text-gray-900">${item.quantity}</span>
                    <button onclick="updateQuantity(${index}, 1)" class="bg-green-500 hover:bg-green-700 text-white px-2 py-1 rounded text-sm">+</button>
                </div>
            </div>
        `;
    });
    
    cartContainer.innerHTML = cartHTML;
    totalElement.textContent = 'PKR ' + total.toFixed(2);
    checkoutBtn.disabled = false;
    updateFinalTotal();
}

// Update final total after discount
function updateFinalTotal() {
    const total = parseFloat(document.getElementById('cartTotal').textContent.replace('PKR ', '')) || 0;
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const finalTotal = Math.max(0, total - discount);
    
    document.getElementById('finalTotal').textContent = 'PKR ' + finalTotal.toFixed(2);
}

// Update payment fields based on method
function updatePaymentFields() {
    const method = document.getElementById('paymentMethod').value;
    const finalTotal = parseFloat(document.getElementById('finalTotal').textContent.replace('PKR ', ''));
    
    if (method === 'cash') {
        document.getElementById('cashSection').classList.remove('hidden');
        document.getElementById('cardSection').classList.add('hidden');
        document.getElementById('cashAmount').value = finalTotal.toFixed(2);
        document.getElementById('cardAmount').value = '0';
    } else if (method === 'card') {
        document.getElementById('cashSection').classList.add('hidden');
        document.getElementById('cardSection').classList.remove('hidden');
        document.getElementById('cashAmount').value = '0';
        document.getElementById('cardAmount').value = finalTotal.toFixed(2);
    } else if (method === 'mixed') {
        document.getElementById('cashSection').classList.remove('hidden');
        document.getElementById('cardSection').classList.remove('hidden');
        document.getElementById('cashAmount').value = '0';
        document.getElementById('cardAmount').value = '0';
    }
    
    calculateBalance();
}

// Calculate balance
function calculateBalance() {
    const finalTotal = parseFloat(document.getElementById('finalTotal').textContent.replace('PKR ', ''));
    const cashAmount = parseFloat(document.getElementById('cashAmount').value) || 0;
    const cardAmount = parseFloat(document.getElementById('cardAmount').value) || 0;
    const totalPaid = cashAmount + cardAmount;
    const balance = totalPaid - finalTotal;
    
    document.getElementById('balanceAmount').value = 'PKR ' + balance.toFixed(2);
    
    // Enable/disable checkout button
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (totalPaid >= finalTotal) {
        checkoutBtn.disabled = false;
        checkoutBtn.classList.remove('bg-gray-400');
        checkoutBtn.classList.add('bg-green-600', 'hover:bg-green-700');
    } else {
        checkoutBtn.disabled = true;
        checkoutBtn.classList.add('bg-gray-400');
        checkoutBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
    }
}

// Process payment function
function processPayment() {
    if (cart.length === 0) {
        alert('Cart is empty.');
        return;
    }
    
    const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const paymentMethod = document.getElementById('paymentMethod').value;
    const cashAmount = parseFloat(document.getElementById('cashAmount').value) || 0;
    const cardAmount = parseFloat(document.getElementById('cardAmount').value) || 0;
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="checkout">
        <input type="hidden" name="cart_items" value='${JSON.stringify(cart)}'>
        <input type="hidden" name="total_amount" value="${total}">
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
        const itemTotal = item.price * item.quantity;
        itemsHTML += `
            <tr class="border-b">
                <td class="py-2 text-left">${item.name}</td>
                <td class="py-2 text-center">${item.quantity}</td>
                <td class="py-2 text-right">${item.price.toFixed(2)}</td>
                <td class="py-2 text-right">${itemTotal.toFixed(2)}</td>
            </tr>
        `;
    });
    
    const invoiceHTML = `
        <div class="text-left">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">INVOICE</h2>
                <p class="text-gray-600">Sale #${saleData.id}</p>
                <p class="text-gray-600">${currentDate} ${currentTime}</p>
            </div>
            
            <div class="mb-6">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-300">
                            <th class="py-2 text-left">Item</th>
                            <th class="py-2 text-center">Qty</th>
                            <th class="py-2 text-right">Price</th>
                            <th class="py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHTML}
                    </tbody>
                </table>
            </div>
            
            <div class="border-t pt-4">
                <div class="flex justify-between mb-2">
                    <span>Subtotal:</span>
                    <span>PKR ${saleData.total.toFixed(2)}</span>
                </div>
                <div class="flex justify-between mb-2">
                    <span>Discount:</span>
                    <span>-PKR ${saleData.discount.toFixed(2)}</span>
                </div>
                <div class="flex justify-between font-bold text-lg">
                    <span>Total:</span>
                    <span>PKR ${saleData.final.toFixed(2)}</span>
                </div>
            </div>
            
            <div class="border-t pt-4 mt-4">
                <div class="flex justify-between mb-2">
                    <span>Payment Method:</span>
                    <span>${saleData.payment_method.toUpperCase()}</span>
                </div>
                ${saleData.cash_amount > 0 ? `<div class="flex justify-between mb-2"><span>Cash:</span><span>PKR ${saleData.cash_amount.toFixed(2)}</span></div>` : ''}
                ${saleData.card_amount > 0 ? `<div class="flex justify-between mb-2"><span>Card:</span><span>PKR ${saleData.card_amount.toFixed(2)}</span></div>` : ''}
                ${saleData.balance > 0 ? `<div class="flex justify-between mb-2"><span>Change:</span><span>PKR ${saleData.balance.toFixed(2)}</span></div>` : ''}
            </div>
            
            <div class="text-center mt-8">
                <p class="text-gray-600">Thank you for your purchase!</p>
            </div>
        </div>
    `;
    
    // Set content for both modal and print
    invoiceContent.innerHTML = invoiceHTML;
    printInvoiceContent.innerHTML = invoiceHTML;
    
    document.getElementById('invoiceModal').classList.remove('hidden');
}

// Print invoice
function printInvoice() {
    // Hide the modal temporarily
    document.getElementById('invoiceModal').classList.add('hidden');
    
    // Show the print content
    document.getElementById('printInvoice').classList.remove('hidden');
    
    // Print
    window.print();
    
    // Hide print content and show modal again
    document.getElementById('printInvoice').classList.add('hidden');
    document.getElementById('invoiceModal').classList.remove('hidden');
}

// Close invoice modal
function closeInvoice() {
    document.getElementById('invoiceModal').classList.add('hidden');
    cart = [];
    updateCartDisplay();
    document.getElementById('discountInput').value = '0';
    updateFinalTotal();
}

// Initialize cart display
updateCartDisplay();

// Initialize payment fields
updatePaymentFields();

// Load all products initially
loadAllProducts();

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
