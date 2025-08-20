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
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-search mr-2"></i>Product Search
                </h2>
                <button id="toggleLayoutBtn" class="text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white p-2" title="Toggle layout">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>
            
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
                <button id="returnsListBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white p-2 rounded text-sm" title="View Returns" onclick="openReturnsListModal()">
                    <i class="fas fa-clipboard-list"></i>
                </button>
                <button id="returnsBtn" class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded text-sm" title="Returns/Exchanges" onclick="openSalesLookupModal()">
                    <i class="fas fa-undo"></i>
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

<!-- Returns/Exchanges Modal -->
<div id="returnsModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay flex items-center justify-center" onclick="if(event.target===this) closeReturnsModal()">
  <div class="relative mx-auto p-5 border w-[95vw] max-w-6xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel max-h-[80vh] flex flex-col" onclick="event.stopPropagation()">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">
      <i class="fas fa-undo mr-2"></i>Returns / Exchanges
    </h3>
    <div class="mb-3 flex items-center gap-2">
      <input id="returnSaleId" type="number" min="1" placeholder="Enter Sale ID" class="w-40 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
      <button class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded" onclick="loadSaleForReturn()"><i class="fas fa-search mr-1"></i>Load</button>
    </div>
    <div id="returnSaleInfo" class="text-sm mb-3 text-gray-700 dark:text-gray-300"></div>
    <div id="returnItemsWrap" class="border border-gray-200 dark:border-gray-700 rounded mb-3 hidden flex-1 min-h-0 overflow-y-auto">
      <table class="w-full text-sm table-fixed">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr class="border-b border-gray-200 dark:border-gray-700">
            <th class="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-1/3 text-xs whitespace-nowrap">Item</th>
            <th class="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-12 text-xs whitespace-nowrap">Sold</th>
            <th class="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-12 text-xs whitespace-nowrap">Ret.</th>
            <th class="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-12 text-xs whitespace-nowrap">Rem.</th>
            <th class="text-center py-1 px-1 font-medium text-gray-700 dark:text-gray-300 w-20 text-xs whitespace-nowrap">Return Qty</th>
            <th class="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-56 text-xs whitespace-nowrap">Reason</th>
            <th class="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-24 text-xs whitespace-nowrap">Refund</th>
          </tr>
        </thead>
        <tbody id="returnItemsTbody" class="bg-white dark:bg-gray-800"></tbody>
        <tfoot class="bg-gray-50 dark:bg-gray-900 sticky bottom-0 z-10 border-t-2 border-gray-300 dark:border-gray-700">
          <tr class="border-t border-gray-200 dark:border-gray-700">
            <td colspan="6" class="py-2 px-2 text-right font-semibold text-gray-800 dark:text-gray-200">Total Refund</td>
            <td class="py-2 px-2 text-right font-bold text-gray-900 dark:text-gray-100"><span id="totalRefund">PKR 0.00</span></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <!-- Refund method & amounts -->
    <div id="returnRefundSection" class="border-t pt-3 mb-3 hidden">
      <div class="flex items-center justify-between mb-2">
        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">Refund</div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Refund Method</label>
          <select id="refundMethodReturn" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateRefundInputsSection()">
            <option value="cash">Cash Only</option>
            <option value="card">Card Only</option>
            <option value="mixed">Cash + Card</option>
          </select>
        </div>
        <div id="refundCashWrap">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cash Refund (PKR)</label>
          <input id="refundCash" type="number" step="0.01" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" oninput="returnAutofillLocked=true; validateReturnSubmission()">
        </div>
        <div id="refundCardWrap" class="hidden">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Card Refund (PKR)</label>
          <input id="refundCard" type="number" step="0.01" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" oninput="returnAutofillLocked=true; validateReturnSubmission()">
        </div>
      </div>
      <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">Total refund must equal: <span id="expectedRefundLabel">PKR 0.00</span></div>
    </div>
    <div class="flex justify-between items-center">
      <div class="text-xs text-gray-500 dark:text-gray-400">Refund will be issued to original tender (cash/card split as per sale).</div>
      <div class="flex gap-2">
        <button class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded" onclick="closeReturnsModal()"><i class="fas fa-times mr-1"></i>Close</button>
        <button id="processReturnBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded disabled:bg-gray-400" disabled onclick="submitReturn()"><i class="fas fa-check mr-1"></i>Process Return</button>
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

<!-- Sales Lookup Modal -->
<div id="salesLookupModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) closeSalesLookupModal()">
  <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel max-h-[80vh] flex flex-col" onclick="event.stopPropagation()">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i class="fas fa-receipt mr-2"></i>Select a Sale</h3>
    <div class="mb-3">
      <div class="relative">
        <i class="fas fa-search absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input id="salesLookupSearch" type="text" placeholder="Search by Sale ID, cashier, or method..." class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
    </div>
    <div id="salesLookupList" class="border border-gray-200 dark:border-gray-700 rounded p-2 overflow-y-auto text-sm flex-1 min-h-0">
      <div class="text-gray-500 dark:text-gray-400">Loading...</div>
    </div>
    <div class="flex justify-end gap-2 mt-4">
      <button class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded" onclick="closeSalesLookupModal()"><i class="fas fa-times mr-1"></i>Close</button>
    </div>
  </div>
</div>

<!-- Returns List Modal -->
<div id="returnsListModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) closeReturnsListModal()">
  <div class="relative top-10 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel max-h-[80vh] flex flex-col" onclick="event.stopPropagation()">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i class="fas fa-clipboard-list mr-2"></i>Recent Returns</h3>
    <div class="mb-3">
      <div class="relative">
        <i class="fas fa-search absolute left-2 sm:left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input id="returnsLookupSearch" type="text" placeholder="Search by Sale ID, product, reason..." class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
    </div>
    <div id="returnsLookupList" class="border border-gray-200 dark:border-gray-700 rounded p-2 overflow-y-auto text-sm flex-1 min-h-0">
      <div class="text-gray-500 dark:text-gray-400">Loading...</div>
    </div>
    <div class="flex justify-end gap-2 mt-4">
      <button class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded" onclick="closeReturnsListModal()"><i class="fas fa-times mr-1"></i>Close</button>
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
let currentReturn = { sale: null, items: [] };
let allSalesCache = [];
let allReturnsCache = [];

let returnAutofillLocked = false;

// Save cart to localStorage
function saveCartToStorage() {
    localStorage.setItem('pos_cart', JSON.stringify(cart));
}

// Helper: parse JSON with graceful fallback to text
async function parseJsonFromResponse(resp) {
    const text = await resp.text();
    try {
        return { ok: true, data: JSON.parse(text) };
    } catch (e) {
        return { ok: false, text };
    }
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

// Returns modal helpers
function openReturnsModal(){ document.getElementById('returnsModal').classList.remove('hidden'); }
function closeReturnsModal(){ document.getElementById('returnsModal').classList.add('hidden'); resetReturnState(); }
function resetReturnState(){ currentReturn = { sale: null, items: [] }; returnAutofillLocked = false; document.getElementById('returnSaleInfo').innerHTML=''; document.getElementById('returnItemsWrap').classList.add('hidden'); document.getElementById('returnItemsTbody').innerHTML=''; document.getElementById('totalRefund').textContent='PKR 0.00'; const btn=document.getElementById('processReturnBtn'); if (btn) btn.disabled = true; }
async function loadSaleForReturn(){
  const saleId = parseInt(document.getElementById('returnSaleId').value, 10);
  if (!saleId) { alert('Enter a valid Sale ID'); return; }
  try {
    const resp = await fetch('get_sale_details.php?sale_id=' + saleId);
    const parsed = await parseJsonFromResponse(resp);
    if (!parsed.ok) {
        console.error('Non-JSON response from get_sale_details.php:', parsed.text);
        throw new Error('Server returned non-JSON. Check PHP errors/logs.');
    }
    const data = parsed.data;
    if (!data.success) throw new Error(data.message || 'Failed to load sale');
    const sale = data.sale || {};
    currentReturn.sale = sale;
    document.getElementById('returnSaleInfo').innerHTML = `Sale #${sale.id} • ${sale.cashier_name || ''} • Method: ${String(sale.payment_method||'').toUpperCase()} • Total: PKR ${(parseFloat(sale.total_amount||sale.final||0)).toFixed(2)}`;
    renderReturnItems(Array.isArray(sale.items) ? sale.items : []);
    const wrap = document.getElementById('returnItemsWrap');
    if (wrap) wrap.classList.remove('hidden');
    // Focus first qty input for quick entry
    const firstQty = document.querySelector('#returnItemsTbody input[type="number"]');
    if (firstQty) firstQty.focus();
  } catch (e) { alert(e.message || 'Error'); }
}
function renderReturnItems(items){
  const tbody = document.getElementById('returnItemsTbody');
  tbody.innerHTML = items.map((it, idx) => {
    const soldQty = Number(it.quantity) || 0;
    const returnedQty = Number(it.returned_qty || 0);
    const remainingQty = Number((it.remaining_qty != null ? it.remaining_qty : (soldQty - returnedQty))) || 0;
    const price = Number(it.price) || 0;
    const rowId = 'ret_'+idx;
    return `
      <tr class="border-b border-gray-200 dark:border-gray-700">
        <td class="py-1 px-2 text-gray-900 dark:text-gray-100">${it.name}</td>
        <td class="py-1 px-1 text-center text-gray-900 dark:text-gray-100 text-xs whitespace-nowrap">${soldQty}</td>
        <td class="py-1 px-1 text-center text-gray-900 dark:text-gray-100 text-xs whitespace-nowrap">${returnedQty}</td>
        <td class="py-1 px-1 text-center text-gray-900 dark:text-gray-100 text-xs whitespace-nowrap">${remainingQty}</td>
        <td class="py-1 px-2 text-center">
          <div class="inline-flex items-center">
            <button class="bg-red-500 hover:bg-red-700 text-white p-0.5 rounded text-xs" title="Decrease" aria-label="Decrease" onclick="changeReturnQty(${idx}, -1)"><i class="fas fa-minus text-xs"></i></button>
            <span id="${rowId}_qval" class="mx-2 text-gray-900 dark:text-gray-100">0</span>
            <button class="bg-green-500 hover:bg-green-700 text-white p-0.5 rounded text-xs" title="Increase" aria-label="Increase" data-retplus="1" onclick="changeReturnQty(${idx}, 1)"><i class="fas fa-plus text-xs"></i></button>
          </div>
        </td>
        <td class="py-1 px-2"><input type="text" id="${rowId}_reason" placeholder="Reason (required)" class="w-full px-2 py-1 border border-gray-300 rounded" oninput="updateReturnRefund(${idx})"></td>
        <td class="py-1 px-2 text-right text-gray-900 dark:text-gray-100"><span id="${rowId}_refund">PKR 0.00</span></td>
      </tr>
    `;
  }).join('');
  currentReturn.items = items.map(it => ({ product_id: it.product_id, sale_item_id: it.sale_item_id || null, name: it.name, maxQty: Number(it.remaining_qty != null ? it.remaining_qty : it.quantity)||0, price: Number(it.price)||0, tax_rate: Number(it.tax_rate)||0, returnQty: 0, reason: '' }));
  updateTotalRefund();
}
function updateReturnRefund(idx){
  const rowId = 'ret_'+idx;
  const qtyLabel = document.getElementById(rowId+'_qval');
  const reasonEl = document.getElementById(rowId+'_reason');
  const refundEl = document.getElementById(rowId+'_refund');
  const entry = currentReturn.items[idx];
  const qty = Math.max(0, Math.min(parseInt((qtyLabel?.textContent)||'0',10), entry.maxQty));
  if (qtyLabel) qtyLabel.textContent = String(qty);
  entry.returnQty = qty;
  entry.reason = (reasonEl.value||'').trim();
  const refund = entry.price * qty; // price is tax-inclusive in sale_items
  refundEl.textContent = 'PKR ' + refund.toFixed(2);
  updateTotalRefund();
}
function changeReturnQty(idx, delta){
  const entry = currentReturn.items[idx];
  const rowId = 'ret_'+idx;
  const qtyLabel = document.getElementById(rowId+'_qval');
  const current = parseInt((qtyLabel?.textContent)||'0',10) || 0;
  const next = Math.max(0, Math.min(current + delta, entry.maxQty));
  if (qtyLabel) qtyLabel.textContent = String(next);
  updateReturnRefund(idx);
}
function updateTotalRefund(){
  const total = currentReturn.items.reduce((s,it)=> s + (it.returnQty * it.price), 0);
  document.getElementById('totalRefund').textContent = 'PKR ' + total.toFixed(2);
  const expected = document.getElementById('expectedRefundLabel');
  if (expected) expected.textContent = 'PKR ' + total.toFixed(2);
  const section = document.getElementById('returnRefundSection');
  if (section) section.classList.remove('hidden');
  if (!returnAutofillLocked) { autofillReturnAmounts(); }
  const hasAny = currentReturn.items.some(it => it.returnQty > 0);
  const allHaveReasons = currentReturn.items.every(it => it.returnQty === 0 || (it.reason && it.reason.length > 0));
  const btn = document.getElementById('processReturnBtn');
  if (btn) btn.disabled = !(hasAny && allHaveReasons && isRefundAmountsValid(total));
}
function updateRefundInputsSection(){
  const method = document.getElementById('refundMethodReturn').value;
  const cashWrap = document.getElementById('refundCashWrap');
  const cardWrap = document.getElementById('refundCardWrap');
  if (method === 'cash') { cashWrap.classList.remove('hidden'); cardWrap.classList.add('hidden'); }
  else if (method === 'card') { cashWrap.classList.add('hidden'); cardWrap.classList.remove('hidden'); }
  else { cashWrap.classList.remove('hidden'); cardWrap.classList.remove('hidden'); }
  returnAutofillLocked = false; // changing method re-enables autofill
  autofillReturnAmounts();
  validateReturnSubmission();
}
function isRefundAmountsValid(expected){
  const method = document.getElementById('refundMethodReturn').value;
  const cash = parseFloat(document.getElementById('refundCash').value)||0;
  const card = parseFloat(document.getElementById('refundCard').value)||0;
  if (method === 'cash') return Math.abs(cash - expected) < 0.01;
  if (method === 'card') return Math.abs(card - expected) < 0.01;
  return Math.abs((cash+card) - expected) < 0.01;
}
function validateReturnSubmission(){
  // Re-evaluate total and enable
  const total = (function(){ let s = 0; currentReturn.items.forEach(it=>{ s += it.returnQty * it.price; }); return s; })();
  const btn = document.getElementById('processReturnBtn');
  const hasAny = currentReturn.items.some(it => it.returnQty > 0);
  const allHaveReasons = currentReturn.items.every(it => it.returnQty === 0 || (it.reason && it.reason.length > 0));
  if (btn) btn.disabled = !(hasAny && allHaveReasons && isRefundAmountsValid(total));
}
function autofillReturnAmounts(){
  // Set refund inputs to match expected total and method
  const expectedText = document.getElementById('expectedRefundLabel').textContent.replace('PKR','').trim();
  const expected = parseFloat(expectedText)||0;
  const method = document.getElementById('refundMethodReturn').value;
  const cashInput = document.getElementById('refundCash');
  const cardInput = document.getElementById('refundCard');
  if (method === 'cash') { if (cashInput) cashInput.value = expected.toFixed(2); if (cardInput) cardInput.value = '0.00'; }
  else if (method === 'card') { if (cashInput) cashInput.value = '0.00'; if (cardInput) cardInput.value = expected.toFixed(2); }
  else { // mixed: default to all cash, user can adjust
    if (cashInput) cashInput.value = expected.toFixed(2);
    if (cardInput) cardInput.value = '0.00';
  }
  validateReturnSubmission();
}
async function submitReturn(){
  try {
    if (!currentReturn.sale) { alert('Load a sale first.'); return; }
    const payloadItems = currentReturn.items.filter(it => it.returnQty > 0).map(it => ({ sale_item_id: it.sale_item_id, product_id: it.product_id, quantity: it.returnQty, reason: it.reason }));
    if (!payloadItems.length) { alert('Select quantities and reasons.'); return; }
    const form = new URLSearchParams();
    form.set('sale_id', currentReturn.sale.id);
    form.set('items', JSON.stringify(payloadItems));
    // refund method
    const method = document.getElementById('refundMethodReturn').value;
    const cash = parseFloat(document.getElementById('refundCash').value)||0;
    const card = parseFloat(document.getElementById('refundCard').value)||0;
    form.set('refund_method', method);
    form.set('refund_cash', String(cash));
    form.set('refund_card', String(card));
    const resp = await fetch('ajax_process_return.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form.toString() });
    const data = await resp.json();
    if (!data.success) throw new Error(data.message || 'Failed to process return');
    if (window.UIKit) UIKit.success('Return processed');
    if (data.receipt_id) {
      // Offer immediate print
      if (confirm('Return processed. Print return invoice now?')) {
        printReturnInvoice({
          saleId: currentReturn.sale.id,
          receiptId: data.receipt_id,
          receipt: data.receipt
        });
      }
    }
    closeReturnsModal();
  } catch (e) { if (window.UIKit) UIKit.error(e.message || 'Error'); }
}

function printReturnInvoice({ saleId, receiptId, receipt }){
  try {
    const now = new Date();
    const currentDate = now.toLocaleDateString();
    const currentTime = now.toLocaleTimeString();
    const itemsHTML = (receipt.items||[]).map(it => {
      const rate = parseFloat(it.tax_rate||0) || 0;
      const lineTotal = parseFloat(it.line_total||0) || 0;
      const base = rate > 0 ? (lineTotal / (1 + rate/100)) : lineTotal;
      const tax = lineTotal - base;
      const taxHtml = rate > 0 && Math.abs(tax) >= 0.005 ? ` <span style="float:right;">Tax: PKR ${tax.toFixed(2)} (${rate.toFixed(2)}%)</span>` : '';
      return `
      <tr class="border-b">
        <td class="py-1 text-left text-xs" style="padding: 2px; font-size: 12px;">${it.name || ('#'+it.product_id)}</td>
        <td class="py-1 text-center text-xs" style="padding: 2px; font-size: 12px;">${it.quantity}</td>
        <td class="py-1 text-right text-xs" style="padding: 2px; font-size: 12px;">${(parseFloat(it.unit_price)||0).toFixed(2)}</td>
        <td class="py-1 text-right text-xs" style="padding: 2px; font-size: 12px;">${lineTotal.toFixed(2)}</td>
      </tr>
      <tr><td colspan="4" class="py-0 text-left text-xs" style="padding: 1px 2px; font-size: 10px; color: #666;">Reason: ${it.reason||''}${taxHtml}</td></tr>
      <tr>
        <td colspan="4" class="py-0" style="padding: 0; font-size: 10px; color: #666;">
          <div style="border-bottom: 1px solid #eee; margin: 1px 0;"></div>
        </td>
      </tr>
      `;
    }).join('');

    const html = '<!DOCTYPE html>' +
      '<html><head><title>Return Invoice</title>' +
      '<style>' +
      'body { font-family: Arial, sans-serif; margin: 0; padding: 2mm; font-size: 11px; line-height: 1.2; width: 76mm; overflow-x: hidden; }' +
      'table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }' +
      'th, td { padding: 1px; text-align: left; word-wrap: break-word; }' +
      'th:nth-child(1), td:nth-child(1) { width: 35%; }' +
      'th:nth-child(2), td:nth-child(2) { width: 15%; text-align: center; }' +
      'th:nth-child(3), td:nth-child(3) { width: 25%; text-align: right; }' +
      'th:nth-child(4), td:nth-child(4) { width: 25%; text-align: right; }' +
      '.dup { position: fixed; top: 10mm; left: 0; right: 0; text-align: center; font-size: 18px; color: rgba(200,0,0,0.25); transform: rotate(-20deg); }' +
      '@media print { body { margin: 0; padding: 2mm; width: 76mm; } @page { margin: 0; size: 80mm auto; } }' +
      '</style></head><body>' +
      '<div class="dup">DUPLICATE</div>' +
      '<div style="font-size:14px; line-height:1.3; width:100%; padding:0 3mm;">' +
      '<div style="text-align:center; margin-bottom:3px;">' +
      '<h2 style="font-size:18px; margin:0;">RETURN INVOICE</h2>' +
      `<p style="font-size:12px; margin:2px 0;">Sale #${saleId} • Return #${receiptId}</p>` +
      `<p style="font-size:12px; margin:2px 0;">${currentDate} ${currentTime}</p>` +
      '</div>' +
      '<div style="margin-bottom:3px;">' +
      '<table style="font-size:12px; margin:2px 0; table-layout: fixed;">' +
      '<thead><tr class="border-b" style="border-bottom:1px solid #ccc;">' +
      '<th style="padding:2px; width:45%; text-align:left;">Item</th>' +
      '<th style="padding:2px; width:15%; text-align:center;">Qty</th>' +
      '<th style="padding:2px; width:20%; text-align:right;">Price</th>' +
      '<th style="padding:2px; width:20%; text-align:right;">Total</th>' +
      '</tr></thead>' +
      `<tbody>${itemsHTML}</tbody>` +
      '</table>' +
      '</div>' +
      '<div style="border-top:1px solid #ccc; padding-top:2px;">' +
      '<table style="width:100%; border-collapse:collapse;">' +
      `<tr><td style="text-align:left; padding:1px 2px; font-size:12px;">Total Refund:</td><td style="text-align:right; padding:1px 2px; font-size:12px;">PKR ${(parseFloat(receipt.total_refund||0)).toFixed(2)}</td></tr>` +
      (parseFloat(receipt.cash_refund||0) > 0 ? `<tr><td style="text-align:left; padding:1px 2px; font-size:12px;">Cash Refund:</td><td style="text-align:right; padding:1px 2px; font-size:12px;">PKR ${(parseFloat(receipt.cash_refund)).toFixed(2)}</td></tr>` : '') +
      (parseFloat(receipt.card_refund||0) > 0 ? `<tr><td style="text-align:left; padding:1px 2px; font-size:12px;">Card Refund:</td><td style="text-align:right; padding:1px 2px; font-size:12px;">PKR ${(parseFloat(receipt.card_refund)).toFixed(2)}</td></tr>` : '') +
      '</table>' +
      '</div>' +
      '</div>' +
      '<' + 'script' + '>' +
      'window.onload = function(){ window.print(); window.close(); };' +
      '</' + 'script' + '>' +
      '</body></html>';

    const w = window.open('', '_blank', 'width=400,height=600,scrollbars=yes,resizable=yes');
    if (!w) { alert('Popup blocked!'); return; }
    w.document.write(html);
    w.document.close();
  } catch (e) { alert('Error printing: ' + (e.message||e)); }
}

// Sales lookup helpers
function openSalesLookupModal(){
  document.getElementById('salesLookupModal').classList.remove('hidden');
  loadRecentSales();
}

// Returns list helpers
function openReturnsListModal(){ document.getElementById('returnsListModal').classList.remove('hidden'); loadRecentReturns(); }
function closeReturnsListModal(){ document.getElementById('returnsListModal').classList.add('hidden'); }
async function loadRecentReturns(){
  const list = document.getElementById('returnsLookupList');
  list.innerHTML = '<div class="text-gray-500 dark:text-gray-400">Loading...</div>';
  try {
    const resp = await fetch('list_returns.php');
    const parsed = await parseJsonFromResponse(resp);
    if (!parsed.ok) { console.error('Non-JSON from list_returns.php:', parsed.text); throw new Error('Server returned non-JSON.'); }
    const data = parsed.data;
    if (!data.success) throw new Error(data.message || 'Failed to load');
    allReturnsCache = Array.isArray(data.returns) ? data.returns : [];
    renderReturnsLookup(allReturnsCache);
    const search = document.getElementById('returnsLookupSearch');
    if (search) {
      search.oninput = function(){
        const q = this.value.toLowerCase().trim();
        const filtered = allReturnsCache.filter(r => String(r.sale_id).includes(q) || (r.product_name||'').toLowerCase().includes(q) || (r.reason||'').toLowerCase().includes(q));
        renderReturnsLookup(filtered);
      };
    }
  } catch (e) {
    list.innerHTML = '<div class="text-red-600">'+ (e.message || 'Error') +'</div>';
  }
}
function renderReturnsLookup(rows){
  const list = document.getElementById('returnsLookupList');
  if (!rows.length) { list.innerHTML = '<div class="text-gray-500 dark:text-gray-400">No returns found</div>'; return; }
  // Group returns by sale_id
  const groups = {};
  rows.forEach(r => { (groups[r.sale_id] = groups[r.sale_id] || []).push(r); });
  const html = Object.keys(groups).map(saleId => {
    const lines = groups[saleId];
    const totalRefund = lines.reduce((s, l) => s + (parseFloat(l.refund_amount||0)), 0);
    const summary = `Invoice #${saleId} • Refund: PKR ${totalRefund.toFixed(2)}`;
    const items = lines.map(l => `
      <tr class="border-b last:border-b-0 border-gray-200 dark:border-gray-700">
        <td class="py-1 px-2">${l.product_name || ('#'+l.product_id)}</td>
        <td class="py-1 px-2 text-center">${parseFloat(l.quantity||0)}</td>
        <td class="py-1 px-2 text-right">PKR ${(parseFloat(l.refund_amount||0)).toFixed(2)}</td>
        <td class="py-1 px-2">${l.reason || ''}</td>
      </tr>
    `).join('');
    return `
      <div class="border-b last:border-b-0 border-gray-200 dark:border-gray-700">
        <div class="w-full py-2 px-2 flex items-center justify-between cursor-pointer select-none" onclick="toggleReturnGroup(this)">
          <span class="font-medium text-gray-900 dark:text-gray-100 truncate">${summary}</span>
          <span class="inline-flex items-center gap-2 shrink-0 whitespace-nowrap">
            <button class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs" onclick="event.stopPropagation(); reprintReturn(${saleId})"><i class='fas fa-print mr-1'></i>Reprint</button>
            <i class="fas fa-chevron-down text-gray-500"></i>
          </span>
        </div>
        <div class="hidden px-2 pb-2">
          <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="text-left py-1 px-2">Item</th>
                <th class="text-center py-1 px-2">Qty</th>
                <th class="text-right py-1 px-2">Refund</th>
                <th class="text-left py-1 px-2">Reason</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800">${items}</tbody>
          </table>
        </div>
      </div>
    `;
  }).join('');
  list.innerHTML = html;
}

function toggleReturnGroup(btn){
  const details = btn.nextElementSibling;
  if (!details) return;
  const icon = btn.querySelector('i');
  const hidden = details.classList.contains('hidden');
  details.classList.toggle('hidden');
  if (icon) icon.classList.toggle('rotate-180', hidden);
}

async function reprintReturn(saleId){
  try {
    // fetch latest receipt id mapping
    const resp = await fetch('list_returns.php?sale_id=' + encodeURIComponent(saleId));
    const parsed = await parseJsonFromResponse(resp);
    if (!parsed.ok) { alert('Server returned non-JSON'); return; }
    const data = parsed.data;
    if (!data.success) { alert(data.message||'Failed to load'); return; }
    const receiptId = (data.receipts && data.receipts[saleId]) ? data.receipts[saleId] : null;
    if (!receiptId) { alert('No receipt found for this return yet'); return; }
    // fetch receipt payload
    const r = await fetch('return_receipt.php?id=' + encodeURIComponent(receiptId));
    const parsedR = await parseJsonFromResponse(r);
    if (!parsedR.ok) { alert('Server returned non-JSON'); return; }
    const rd = parsedR.data;
    if (!rd.success) { alert(rd.message||'Failed to load receipt'); return; }
    printReturnInvoice({ saleId: rd.sale_id, receiptId: receiptId, receipt: rd.receipt });
  } catch (e) { alert(e.message||'Error'); }
}
function closeSalesLookupModal(){ document.getElementById('salesLookupModal').classList.add('hidden'); }
async function loadRecentSales(){
  const list = document.getElementById('salesLookupList');
  list.innerHTML = '<div class="text-gray-500 dark:text-gray-400">Loading...</div>';
  try {
    const resp = await fetch('list_sales.php');
    const parsed = await parseJsonFromResponse(resp);
    if (!parsed.ok) {
      console.error('Non-JSON response from list_sales.php:', parsed.text);
      throw new Error('Server returned non-JSON. Check PHP errors/logs.');
    }
    const data = parsed.data;
    if (!data.success) throw new Error(data.message || 'Failed to load');
    allSalesCache = Array.isArray(data.sales) ? data.sales : [];
    renderSalesLookup(allSalesCache);
    const search = document.getElementById('salesLookupSearch');
    if (search) {
      search.oninput = function(){
        const q = this.value.toLowerCase().trim();
        const filtered = allSalesCache.filter(s => String(s.id).includes(q) || (s.cashier_name||'').toLowerCase().includes(q) || (s.payment_method||'').toLowerCase().includes(q));
        renderSalesLookup(filtered);
      };
    }
  } catch (e) {
    list.innerHTML = '<div class="text-red-600">'+ (e.message || 'Error') +'</div>';
  }
}
function renderSalesLookup(rows){
  const list = document.getElementById('salesLookupList');
  if (!rows.length) { list.innerHTML = '<div class="text-gray-500 dark:text-gray-400">No sales found</div>'; return; }
  list.innerHTML = rows.map(r => `
    <div class="flex items-center justify-between border-b last:border-b-0 border-gray-200 dark:border-gray-700 py-2">
      <div class="min-w-0">
        <div class="font-medium text-gray-900 dark:text-gray-100">#${r.id} • PKR ${(parseFloat(r.final||r.total_amount||0)).toFixed(2)}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">${r.created_at || ''} • ${r.cashier_name || ''} • ${String(r.payment_method||'').toUpperCase()}</div>
      </div>
      <div class="shrink-0">
        <button class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs" onclick="selectSaleForReturn(${r.id})"><i class='fas fa-check mr-1'></i>Select</button>
      </div>
    </div>
  `).join('');
}
async function selectSaleForReturn(saleId){
  closeSalesLookupModal();
  // Open returns and load
  openReturnsModal();
  document.getElementById('returnSaleId').value = String(saleId);
  await loadSaleForReturn();
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
        data: { query: '', layout: (localStorage.getItem('productLayout')||'list') },
        success: function(response) {
            console.log('Products loaded successfully');
            console.log('Response length:', response.length);
            console.log('Response preview:', response.substring(0, 200));
            const isGrid = (localStorage.getItem('productLayout')||'list') === 'grid';
            const wrap = document.getElementById('searchResults');
            wrap.classList.toggle('grid', isGrid);
            wrap.classList.toggle('grid-cols-3', isGrid);
            wrap.classList.toggle('gap-2', isGrid);
            wrap.classList.toggle('p-2', true);
            wrap.innerHTML = response;
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
                    data: { query: query, layout: (localStorage.getItem('productLayout')||'list') },
                    success: function(response) {
                        const isGrid = (localStorage.getItem('productLayout')||'list') === 'grid';
                        const wrap = document.getElementById('searchResults');
                        wrap.classList.toggle('grid', isGrid);
                        wrap.classList.toggle('grid-cols-3', isGrid);
                        wrap.classList.toggle('gap-2', isGrid);
                        wrap.classList.toggle('p-2', true);
                        wrap.innerHTML = response;
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

    // Reverted splitter; no init

    // Product layout toggle
    const toggleLayoutBtn = document.getElementById('toggleLayoutBtn');
    const applyLayout = function(){
      const isGrid = (localStorage.getItem('productLayout')||'list') === 'grid';
      const wrap = document.getElementById('searchResults');
      if (!wrap) return;
      // Tailwind classes
      wrap.classList.toggle('grid', isGrid);
      wrap.classList.toggle('grid-cols-3', isGrid);
      wrap.classList.toggle('gap-2', isGrid);
      wrap.classList.toggle('p-2', true);
      // Inline fallback to guarantee 3 columns
      if (isGrid) {
        wrap.style.display = 'grid';
        wrap.style.gridTemplateColumns = 'repeat(3, minmax(0, 1fr))';
        wrap.style.gap = '0.5rem';
      } else {
        wrap.style.display = '';
        wrap.style.gridTemplateColumns = '';
        wrap.style.gap = '';
      }
      if (toggleLayoutBtn) {
        toggleLayoutBtn.innerHTML = isGrid ? '<i class="fas fa-list"></i>' : '<i class="fas fa-th-large"></i>';
        toggleLayoutBtn.title = isGrid ? 'List layout' : 'Grid layout';
      }
    };
    if (!localStorage.getItem('productLayout')) localStorage.setItem('productLayout','list');
    applyLayout();
    if (toggleLayoutBtn) toggleLayoutBtn.onclick = function(){
      const curr = localStorage.getItem('productLayout')||'list';
      localStorage.setItem('productLayout', curr === 'grid' ? 'list' : 'grid');
      applyLayout();
      loadAllProducts();
    };
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