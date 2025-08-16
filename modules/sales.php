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
    <div class="flex-1 px-2 sm:px-3 py-2 sm:py-3 min-h-0">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-10 gap-3 sm:gap-6 h-full">
        <!-- Product Search and Selection -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-3 sm:p-4 flex flex-col min-h-0 md:col-span-1 lg:col-span-5">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">
                <i class="fas fa-search mr-2"></i>Product Search
            </h2>
            
            <div class="mb-3 sm:mb-4">
                <div class="relative">
                    <i class="fas fa-search absolute left-2 sm:left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="searchInput" placeholder="Search by product name or SKU..." 
                           class="w-full pl-8 sm:pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            
            <div id="searchResults" class="overflow-y-auto border border-gray-200 rounded-md p-2 flex-1">
                <!-- Search results will be populated here -->
            </div>
        </div>

        <!-- Shopping Cart -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-3 sm:p-4 flex flex-col min-h-0 md:col-span-1 lg:col-span-5 relative">
            <!-- Toggle Header Button - Floating on cart -->
            <div class="absolute top-2 right-2 flex items-center gap-2 z-10">
                <button id="controlsBtn" class="bg-gray-500 hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 text-white p-2 rounded text-sm" title="Show Controls" onclick="openControlsModal()">
                    <i class="fas fa-keyboard"></i>
                </button>
                <button id="toggleHeaderBtn" class="bg-gray-500 hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 text-white p-2 rounded text-sm">
                    <i class="fas fa-eye-slash"></i>
                </button>
            </div>
            <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-3 sm:mb-4">
                <i class="fas fa-shopping-basket mr-2"></i>Shopping Cart
            </h2>
            
            <div id="cartItems" class="overflow-y-auto overflow-x-hidden mb-4 flex-1 min-w-0 max-h-[50vh] md:max-h-[60vh] lg:max-h-none">
                <!-- Cart items will be populated here -->
            </div>
            
            <div class="border-t pt-2 flex-shrink-0">
                <!-- Final Total -->
                <div class="flex justify-between items-center mb-2">
                    <span class="text-base font-semibold text-gray-900">Final Total:</span>
                    <span id="finalTotal" class="text-lg sm:text-xl font-bold text-blue-600">PKR 0.00</span>
                </div>
                <div class="flex flex-wrap items-center gap-2 mb-2 min-w-0">
                    <button id="parkCartBtn" onclick="openParkNoteModal()" disabled class="shrink-0 whitespace-nowrap bg-yellow-600 hover:bg-yellow-700 disabled:bg-gray-400 disabled:hover:bg-gray-400 disabled:cursor-not-allowed text-white font-semibold py-3 px-4 rounded text-base">
                        <i class="fas fa-inbox mr-2"></i>Park Sale
                    </button>
                    <button id="resumeCartBtn" onclick="openParkedModal()" class="shrink-0 whitespace-nowrap bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded text-base">
                        <i class="fas fa-folder-open mr-2"></i>Resume
                    </button>
                    <button id="clearCartBtn" onclick="clearCart()" class="shrink-0 whitespace-nowrap bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded text-base">
                        <i class="fas fa-trash mr-2"></i>Clear Cart
                    </button>
                    
                <button onclick="showPaymentModal()" id="checkoutBtn" disabled
                        class="flex-1 min-w-0 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded text-base">
                    <i class="fas fa-credit-card mr-2"></i>Proceed to Payment
                </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) hidePaymentModal()">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">
                <i class="fas fa-money-bill-wave mr-2"></i>Payment Details
            </h3>
            
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
            <!-- Discount -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-percentage mr-1"></i>Discount (PKR)
                </label>
                <input type="number" id="discountInput" step="0.01" min="0" value="0" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                       onchange="updateFinalTotal(); calculateBalance();">
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
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button id="completeSaleBtn" 
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-check mr-2"></i>Complete Sale
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Controls Modal (Sales) -->
<div id="controlsModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) closeControlsModal()">
  <div class="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i class="fas fa-keyboard mr-2"></i>Sales Controls</h3>
    <div class="text-sm grid grid-cols-1 gap-2">
      <div><span class="font-semibold">F2 / Ctrl+F</span> – Focus Search</div>
      <div><span class="font-semibold">Alt+P</span> – Proceed to Payment</div>
      <div><span class="font-semibold">Alt+K</span> – Park Sale</div>
      <div><span class="font-semibold">Alt+R</span> – Open Resume</div>
      <div><span class="font-semibold">Alt+C</span> – Clear Cart</div>
      <div><span class="font-semibold">+</span> – Increase last item qty</div>
      <div><span class="font-semibold">-</span> – Decrease last item qty</div>
      <div><span class="font-semibold">Delete</span> – Remove last item</div>
    </div>
    <div class="flex justify-end mt-4">
      <button class="bg-gray-500 hover:bg-gray-700 text-white py-2 px-4 rounded" onclick="closeControlsModal()"><i class="fas fa-times mr-1"></i>Close</button>
    </div>
  </div>
</div>
<!-- Park Note Modal -->
<div id="parkNoteModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) closeParkNoteModal()">
  <div class="relative top-10 mx-auto p-5 border w-full max-w-sm shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i class="fas fa-sticky-note mr-2"></i>Park Sale Note</h3>
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Note (required)</label>
    <input id="parkNoteInput" type="text" required placeholder="Customer name or reference" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4">
    <div class="flex justify-end gap-2">
      <button class="bg-gray-500 hover:bg-gray-700 text-white py-2 px-4 rounded" onclick="closeParkNoteModal()"><i class="fas fa-times mr-1"></i>Cancel</button>
      <button class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded" onclick="submitParkNote()"><i class="fas fa-inbox mr-1"></i>Park</button>
    </div>
  </div>
</div>

<!-- Parked Sales Modal -->
<div id="parkedModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) closeParkedModal()">
  <div class="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i class="fas fa-folder-tree mr-2"></i>Parked Sales</h3>
    <div class="mb-3">
      <input id="parkedSearch" type="text" placeholder="Search notes or IDs..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div id="parkedList" class="border border-gray-200 dark:border-gray-700 rounded p-2 max-h-80 overflow-y-auto text-sm">
      <div class="text-gray-500 dark:text-gray-400">Loading...</div>
    </div>
    <div class="flex justify-end space-x-2 mt-4">
      <button onclick="closeParkedModal()" class="bg-gray-500 hover:bg-gray-700 text-white py-2 px-4 rounded"><i class="fas fa-times mr-1"></i>Close</button>
    </div>
  </div>
</div>

<!-- Invoice Modal -->
<div id="invoiceModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) closeInvoice()">
    <div class="relative top-5 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
        <div class="mt-3">
            <div id="invoiceContent" class="text-center">
                <!-- Invoice content will be populated here -->
            </div>
            <div class="flex justify-center space-x-4 mt-6">
                <button onclick="printInvoice()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-print mr-2"></i>Print Invoice
                </button>
                <button onclick="closeInvoice()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-times mr-2"></i>Close
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

// Clear cart action
function clearCart() {
    if (!cart.length) return;
    if (!confirm('Clear all items from the cart?')) return;
    cart = [];
    clearCartFromStorage();
    updateCartDisplay();
    // Reset totals and disable checkout
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) checkoutBtn.disabled = true;
    document.getElementById('finalTotal').textContent = 'PKR 0.00';
}

function isTypingInForm() {
    const ae = document.activeElement;
    if (!ae) return false;
    const tag = ae.tagName ? ae.tagName.toLowerCase() : '';
    return tag === 'input' || tag === 'textarea' || tag === 'select' || ae.isContentEditable;
}

function adjustLastCartQuantity(delta) {
    if (!cart.length) return;
    const index = cart.length - 1;
    updateQuantity(index, delta);
}

// Park current cart to backend
async function parkCurrentCart(fromModal) {
    if (!cart.length) { alert('Cart is empty.'); return; }
    try {
        const noteEl = document.getElementById('parkNoteInput');
        const note = noteEl ? noteEl.value.trim() : '';
        if (!note) { alert('Note is required.'); return; }
        const resp = await fetch('ajax_park_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `cart=${encodeURIComponent(JSON.stringify(cart))}&note=${encodeURIComponent(note)}`
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Failed to park cart');
        // Clear current cart
        clearCart();
        if (fromModal) { await loadParkedList(); }
        closeParkNoteModal();
        if (window.UIKit) UIKit.success('Sale parked successfully');
    } catch (e) {
        if (window.UIKit) UIKit.error(e.message || 'Error parking sale');
    }
}

function openParkedModal() {
    document.getElementById('parkedModal').classList.remove('hidden');
    loadParkedList();
}
function closeParkedModal() {
    document.getElementById('parkedModal').classList.add('hidden');
}

function openParkNoteModal() {
    document.getElementById('parkNoteModal').classList.remove('hidden');
    const input = document.getElementById('parkNoteInput');
    if (input) { input.value = ''; setTimeout(()=>input.focus(), 50); }
}
function closeParkNoteModal() {
    document.getElementById('parkNoteModal').classList.add('hidden');
}
function submitParkNote() { parkCurrentCart(false); }

async function loadParkedList() {
    const list = document.getElementById('parkedList');
    list.innerHTML = '<div class="text-gray-500 dark:text-gray-400">Loading...</div>';
    try {
        const resp = await fetch('ajax_list_parked.php');
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Failed to load');
        const rows = data.data || [];
        if (!rows.length) { list.innerHTML = '<div class="text-gray-500 dark:text-gray-400">No parked sales</div>'; return; }
        const render = (arr) => arr.map(r => `
            <div class="flex items-center justify-between border-b last:border-b-0 border-gray-200 dark:border-gray-700 py-2">
                <div class="min-w-0">
                    <div class="font-medium text-gray-900 dark:text-gray-100">#${r.id} ${r.note ? ' - ' + r.note : ''}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">${r.created_at}</div>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-1 rounded text-xs" onclick="resumeParked(${r.id})"><i class='fas fa-folder-open mr-1'></i>Resume</button>
                    <button class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs" onclick="deleteParked(${r.id})"><i class='fas fa-trash mr-1'></i>Delete</button>
                </div>
            </div>
        `).join('');
        list.innerHTML = render(rows);
        const search = document.getElementById('parkedSearch');
        if (search) {
            search.oninput = function(){
                const q = this.value.toLowerCase();
                const filtered = rows.filter(r => (String(r.id).includes(q) || (r.note||'').toLowerCase().includes(q)));
                list.innerHTML = filtered.length ? render(filtered) : '<div class="text-gray-500 dark:text-gray-400">No results</div>';
            };
        }
    } catch (e) {
        list.innerHTML = '<div class="text-red-600">'+ (e.message || 'Error') +'</div>';
    }
}

async function resumeParked(id) {
    try {
        const resp = await fetch('ajax_get_parked.php?id=' + encodeURIComponent(id) + '&remove=1');
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Failed to load');
        const loaded = JSON.parse(data.cart || '[]');
        if (!Array.isArray(loaded)) throw new Error('Invalid cart data');
        cart = loaded;
        saveCartToStorage();
        updateCartDisplay();
        closeParkedModal();
        if (window.UIKit) UIKit.success('Cart resumed');
    } catch (e) {
        if (window.UIKit) UIKit.error(e.message || 'Error resuming');
    }
}

async function deleteParked(id) {
    try {
        // reuse get endpoint with remove after fetch = true but not return cart
        const resp = await fetch('ajax_get_parked.php?id=' + encodeURIComponent(id) + '&remove=1');
        const data = await resp.json();
        if (!data.success) throw new Error(data.message || 'Failed to delete');
        await loadParkedList();
        if (window.UIKit) UIKit.success('Deleted');
    } catch (e) {
        if (window.UIKit) UIKit.error(e.message || 'Error deleting');
    }
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
        toggleBtn.innerHTML = '<i class="fas fa-eye-slash"></i>';
        headerHidden = false;
        localStorage.setItem('headerHidden', 'false');
    } else {
        // Hide header
        headerSection.classList.add('hidden');
        navHeader.classList.add('hidden');
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        headerHidden = true;
        localStorage.setItem('headerHidden', 'true');
    }
}

function openControlsModal(){ document.getElementById('controlsModal').classList.remove('hidden'); }
function closeControlsModal(){ document.getElementById('controlsModal').classList.add('hidden'); }

// Load header state from localStorage
function loadHeaderState() {
    const savedState = localStorage.getItem('headerHidden');
    if (savedState === 'true') {
        headerHidden = true;
        // Apply the saved state directly
        const headerSection = document.getElementById('headerSection');
        const navHeader = document.querySelector('nav');
        const toggleBtn = document.getElementById('toggleHeaderBtn');
        
        if (headerSection && navHeader && toggleBtn) {
            headerSection.classList.add('hidden');
            navHeader.classList.add('hidden');
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        }
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
    let addedItemIndex = -1;
    
    if (existingItem) {
        if (existingItem.quantity < product.stock) {
            existingItem.quantity++;
            addedItemIndex = cart.indexOf(existingItem);
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
            addedItemIndex = cart.length - 1;
        } else {
            alert('Product is out of stock.');
            return;
        }
    }
    
    updateCartDisplay();
    saveCartToStorage();
    
    // Scroll to the added item
    if (addedItemIndex >= 0) {
        setTimeout(() => {
            scrollToCartItem(addedItemIndex);
        }, 100);
    }
    
    // Don't clear the search input or results - keep products visible
}

// Scroll to specific cart item
function scrollToCartItem(itemIndex) {
    const cartContainer = document.getElementById('cartItems');
    const tableRows = cartContainer.querySelectorAll('tbody tr');
    
    if (tableRows[itemIndex]) {
        // Add highlight effect (light and dark friendly)
        tableRows[itemIndex].classList.add('bg-yellow-100', 'dark:bg-amber-800/40', 'border-yellow-400', 'dark:border-amber-500');
        
        // Scroll to the item
        tableRows[itemIndex].scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
        
        // Remove highlight after 2 seconds
        setTimeout(() => {
            tableRows[itemIndex].classList.remove('bg-yellow-100', 'dark:bg-amber-800/40', 'border-yellow-400', 'dark:border-amber-500');
        }, 2000);
    }
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
    const checkoutBtn = document.getElementById('checkoutBtn');
    let cartHTML = '';
    
    if (cart.length === 0) {
        cartHTML = `
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Sr.</th>
                        <th class="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Item Name</th>
                        <th class="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Desc</th>
                        <th class="text-center py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Qty</th>
                        <th class="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Price</th>
                        <th class="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Tax</th>
                        <th class="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Total</th>
                        <th class="text-center py-1 px-2 font-medium text-gray-700 dark:text-gray-300">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800">
                    <tr>
                        <td colspan="8" class="py-8 text-center text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700">Cart is empty</td>
                    </tr>
                </tbody>
            </table>
        `;
        cartContainer.innerHTML = cartHTML;
        checkoutBtn.disabled = true;
        const parkBtn = document.getElementById('parkCartBtn');
        if (parkBtn) parkBtn.disabled = true;
        updateFinalTotal();
        return;
    }
    
    let total = 0;
    
            cartHTML = `
        <table class="w-full text-sm table-fixed">
            <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0 z-0">
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-8">Sr.</th>
                    <th class="text-left py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-2/5">Item Name</th>
                    <th class="text-left py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-10">Desc</th>
                    <th class="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-10">Qty</th>
                    <th class="text-right py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-14">Price</th>
                    <th class="text-right py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-14">Tax</th>
                    <th class="text-right py-1 px-1 font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 w-14">Total</th>
                    <th class="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-10">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800">
    `;
    
    cart.forEach((item, index) => {
        // Calculate tax backwards from tax-inclusive price
        const itemTotal = item.price * item.quantity; // This is tax-inclusive total
        const itemSubtotal = item.tax_rate > 0 ? (itemTotal / (1 + item.tax_rate / 100)) : itemTotal;
        const itemTax = itemTotal - itemSubtotal;
        total += itemSubtotal; // Add subtotal (without tax) to cart total
        
        cartHTML += `
            <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="py-1 px-1 text-gray-600 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700">${index + 1}</td>
                <td class="py-1 px-1 font-medium text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">${item.name}</td>
                <td class="py-1 px-1 text-gray-600 dark:text-gray-400 text-xs border-r border-gray-200 dark:border-gray-700 whitespace-nowrap">${item.tax_rate > 0 ? `${item.tax_rate}% tax` : 'No tax'}</td>
                <td class="py-1 px-1 text-center text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">${item.quantity}</td>
                <td class="py-1 px-1 text-right text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">${item.price.toFixed(2)}</td>
                <td class="py-1 px-1 text-right text-red-600 dark:text-red-400 border-r border-gray-200 dark:border-gray-700">${itemTax.toFixed(2)}</td>
                <td class="py-1 px-1 text-right font-medium text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">${itemTotal.toFixed(2)}</td>
                <td class="py-1 px-1 text-center">
                    <div class="flex items-center justify-center space-x-0.5">
                        <button onclick="updateQuantity(${index}, -1)" class="bg-red-500 hover:bg-red-700 text-white p-0.5 rounded text-xs">
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        <button onclick="updateQuantity(${index}, 1)" class="bg-green-500 hover:bg-green-700 text-white p-0.5 rounded text-xs">
                            <i class="fas fa-plus text-xs"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    cartHTML += `
            </tbody>
        </table>
    `;
    
    cartContainer.innerHTML = cartHTML;
    const parkBtn = document.getElementById('parkCartBtn');
    if (parkBtn) parkBtn.disabled = false;
    
    // Calculate tax backwards from tax-inclusive prices
    let totalTax = 0;
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity; // Tax-inclusive total
        const itemSubtotal = item.tax_rate > 0 ? (itemTotal / (1 + item.tax_rate / 100)) : itemTotal;
        const itemTax = itemTotal - itemSubtotal;
        totalTax += itemTax;
    });
    
    const totalWithTax = total + totalTax;
    
    // Add footer with totals after calculation
    const tableElement = cartContainer.querySelector('table');
    if (tableElement) {
        const tfoot = document.createElement('tfoot');
        tfoot.className = 'bg-gray-50 dark:bg-gray-900 border-t-2 border-gray-300 dark:border-gray-700 sticky bottom-0 z-10';
        
        tfoot.innerHTML = `
            <tr class="border-t border-gray-300 dark:border-gray-700">
                <td colspan="4" class="py-1 px-1 text-right font-semibold text-gray-800 dark:text-gray-200 border-r border-gray-200 dark:border-gray-700">Total</td>
                <td class="py-1 px-1 text-right font-medium text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">${total.toFixed(2)}</td>
                <td class="py-1 px-1 text-right font-medium text-red-600 dark:text-red-400 border-r border-gray-200 dark:border-gray-700">${totalTax.toFixed(2)}</td>
                <td class="py-1 px-1 text-right font-bold text-gray-900 dark:text-gray-100 border-r border-gray-200 dark:border-gray-700">${totalWithTax.toFixed(2)}</td>
                <td class="py-1 px-1"></td>
            </tr>
        `;
        tableElement.appendChild(tfoot);
    }
    
    checkoutBtn.disabled = false;
    updateFinalTotal();
}

// Update final total after discount
function updateFinalTotal() {
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    
    // Calculate tax backwards from tax-inclusive prices
    let totalTax = 0;
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity; // Tax-inclusive total
        const itemSubtotal = item.tax_rate > 0 ? (itemTotal / (1 + item.tax_rate / 100)) : itemTotal;
        const itemTax = itemTotal - itemSubtotal;
        totalTax += itemTax;
    });
    
    const subtotal = cart.reduce((sum, item) => sum + (item.tax_rate > 0 ? (item.price * item.quantity / (1 + item.tax_rate / 100)) : item.price * item.quantity), 0);
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
    const total = cart.reduce((sum, item) => sum + (item.tax_rate > 0 ? (item.price * item.quantity / (1 + item.tax_rate / 100)) : item.price * item.quantity), 0);
    
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
    
    // Load header state from localStorage
    loadHeaderState();
    
    // Set up periodic stock refresh for concurrent sales handling
    setInterval(refreshProductStock, 30000); // Refresh every 30 seconds

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e){
        if (isTypingInForm()) return;
        // Ctrl+F: focus search
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') { e.preventDefault(); const si=document.getElementById('searchInput'); if (si) si.focus(); return; }
        // F2: focus search
        if (e.key === 'F2') { e.preventDefault(); const si=document.getElementById('searchInput'); if (si) si.focus(); return; }
        // Alt+P: open payment
        if (e.altKey && (e.key.toLowerCase() === 'p')) { e.preventDefault(); const btn=document.getElementById('checkoutBtn'); if (btn && !btn.disabled) showPaymentModal(); return; }
        // Alt+K: park sale (open note modal)
        if (e.altKey && (e.key.toLowerCase() === 'k')) { e.preventDefault(); const park=document.getElementById('parkCartBtn'); if (park && !park.disabled) openParkNoteModal(); return; }
        // Alt+R: open resume parked modal
        if (e.altKey && (e.key.toLowerCase() === 'r')) { e.preventDefault(); openParkedModal(); return; }
        // Alt+C: clear cart
        if (e.altKey && (e.key.toLowerCase() === 'c')) { e.preventDefault(); clearCart(); return; }
        // + / - adjust last item quantity
        if (e.key === '+' || e.key === '=') { e.preventDefault(); adjustLastCartQuantity(1); return; }
        if (e.key === '-' || e.key === '_') { e.preventDefault(); adjustLastCartQuantity(-1); return; }
        // Delete: remove last item
        if (e.key === 'Delete' || e.key === 'Backspace') { if (!cart.length) return; e.preventDefault(); updateQuantity(cart.length-1, -cart[cart.length-1].quantity); return; }
    });
});

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