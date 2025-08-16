<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require admin access
requireAdmin();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $vendor_id = intval($_POST['vendor_id']);
                $purchase_date = $_POST['purchase_date'];
                $total_amount = floatval($_POST['total_amount']);
                $payment_method = $_POST['payment_method'];
                $notes = trim($_POST['notes']);
                
                if ($vendor_id <= 0 || empty($purchase_date) || $total_amount <= 0) {
                    $error = 'Please fill all required fields correctly.';
                } else {
                    // Start transaction
                    mysqli_begin_transaction($conn);
                    
                    try {
                        // Insert purchase record
                        $stmt = mysqli_prepare($conn, "INSERT INTO purchases (vendor_id, purchase_date, total_amount, payment_method, notes, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($stmt, "isdsis", $vendor_id, $purchase_date, $total_amount, $payment_method, $notes, $_SESSION['user_id']);
                        mysqli_stmt_execute($stmt);
                        $purchase_id = mysqli_insert_id($conn);
                        
                        // Process purchase items
                        $items = json_decode($_POST['items'], true);
                        foreach ($items as $item) {
                            // Insert purchase item
                            $stmt = mysqli_prepare($conn, "INSERT INTO purchase_items (purchase_id, product_id, quantity, cost_price) VALUES (?, ?, ?, ?)");
                            mysqli_stmt_bind_param($stmt, "iiid", $purchase_id, $item['product_id'], $item['quantity'], $item['cost_price']);
                            mysqli_stmt_execute($stmt);
                            
                            // Update product stock and cost price
                            $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock + ?, cost_price = ? WHERE id = ?");
                            mysqli_stmt_bind_param($stmt, "idi", $item['quantity'], $item['cost_price'], $item['product_id']);
                            mysqli_stmt_execute($stmt);
                        }
                        
                        mysqli_commit($conn);
                        $message = 'Purchase added successfully! Purchase ID: ' . $purchase_id;
                        
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error = 'Error processing purchase. Please try again.';
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Get purchase items to reverse stock
                    $stmt = mysqli_prepare($conn, "SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    while ($item = mysqli_fetch_assoc($result)) {
                        // Reverse stock
                        $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ?");
                        mysqli_stmt_bind_param($stmt, "ii", $item['quantity'], $item['product_id']);
                        mysqli_stmt_execute($stmt);
                    }
                    
                    // Delete purchase items
                    $stmt = mysqli_prepare($conn, "DELETE FROM purchase_items WHERE purchase_id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    
                    // Delete purchase
                    $stmt = mysqli_prepare($conn, "DELETE FROM purchases WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    
                    mysqli_commit($conn);
                    $message = 'Purchase deleted successfully.';
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = 'Error deleting purchase. Please try again.';
                }
                break;
        }
    }
}

// Get vendors for dropdown
$vendors = mysqli_query($conn, "SELECT * FROM vendors ORDER BY name");

// Get purchases with vendor names
$query = "SELECT p.*, v.name as vendor_name, u.name as user_name 
          FROM purchases p 
          LEFT JOIN vendors v ON p.vendor_id = v.id 
          LEFT JOIN users u ON p.user_id = u.id 
          ORDER BY p.purchase_date DESC";
$purchases = mysqli_query($conn, $query);

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            <i class="fas fa-shopping-cart mr-3"></i>Purchase Management
        </h1>
        <button onclick="showAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-plus mr-2"></i>Add New Purchase
        </button>
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

    <!-- Purchases Table -->
    <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Vendor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payment Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Added By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php while ($purchase = mysqli_fetch_assoc($purchases)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <?php echo date('M d, Y', strtotime($purchase['purchase_date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                            <?php echo htmlspecialchars($purchase['vendor_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                            PKR <?php echo number_format($purchase['total_amount'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo htmlspecialchars($purchase['payment_method']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo htmlspecialchars($purchase['user_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="viewPurchase(<?php echo $purchase['id']; ?>)" 
                                    class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-eye mr-1"></i>View
                            </button>
                            <button onclick="deletePurchase(<?php echo $purchase['id']; ?>)" 
                                    class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Purchase Modal -->
<div id="addModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) document.getElementById('addModal').classList.add('hidden')">
    <div class="relative top-5 mx-auto p-4 sm:p-5 border w-full max-w-6xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">
                    <i class="fas fa-plus mr-2"></i>Add New Purchase
                </h3>
                <button id="purchaseControlsBtn" class="bg-gray-500 hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500 text-white p-2 rounded text-sm" title="Show Controls" onclick="openPurchaseControlsModal()">
                    <i class="fas fa-keyboard"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                <!-- Product Search and Selection -->
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        <i class="fas fa-search mr-2"></i>Product Search
                    </h4>
                    
                    <div class="mb-3 sm:mb-4">
                        <div class="relative">
                            <i class="fas fa-search absolute left-2 sm:left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            <input type="text" id="searchInput" placeholder="Search by product name or SKU..." 
                                   class="w-full pl-8 sm:pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div id="searchResults" class="overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 h-64">
                        <!-- Search results will be populated here -->
                    </div>
                </div>

                <!-- Purchase Cart -->
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        <i class="fas fa-shopping-basket mr-2"></i>Purchase Cart
                    </h4>
                    
                    <div id="purchaseCart" class="overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 h-64 mb-4">
                        <!-- Cart items will be populated here -->
                    </div>
                    
                    <div class="border-t dark:border-gray-700 pt-2">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-base font-semibold text-gray-900 dark:text-gray-100">Total Amount:</span>
                            <span id="cartTotal" class="text-xl font-bold text-blue-600">PKR 0.00</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Purchase Details Form -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vendor *</label>
                    <select id="vendorSelect" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Vendor</option>
                        <?php mysqli_data_seek($vendors, 0); ?>
                        <?php while ($vendor = mysqli_fetch_assoc($vendors)): ?>
                        <option value="<?php echo $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Purchase Date *</label>
                    <input type="date" id="purchaseDate" required value="<?php echo date('Y-m-d'); ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Method *</label>
                    <select id="paymentMethod" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                <textarea id="purchaseNotes" rows="3" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="flex justify-end space-x-4 mt-6">
                <button onclick="closeAddModal()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button onclick="processPurchase()" id="processPurchaseBtn" disabled
                        class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-check mr-2"></i>Complete Purchase
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Purchase Modal -->
<div id="viewModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) closeViewModal()">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Purchase Details</h3>
            <div id="purchaseDetails">
                <!-- Purchase details will be loaded here -->
            </div>
            <div class="flex justify-end mt-6">
                <button onclick="closeViewModal()" 
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-times mr-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Controls Modal (Purchases) -->
<div id="purchaseControlsModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) closePurchaseControlsModal()">
  <div class="relative top-10 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3"><i class="fas fa-keyboard mr-2"></i>Purchase Controls</h3>
    <ul class="text-sm space-y-2">
      <li><span class="font-semibold">F2 / Ctrl+F</span> – Focus Search</li>
      <li><span class="font-semibold">+</span> – Increase last item qty</li>
      <li><span class="font-semibold">-</span> – Decrease last item qty</li>
    </ul>
    <div class="flex justify-end mt-4">
      <button class="bg-gray-500 hover:bg-gray-700 text-white py-2 px-4 rounded" onclick="closePurchaseControlsModal()"><i class="fas fa-times mr-1"></i>Close</button>
    </div>
  </div>
</div>

<script>
let purchaseCart = [];
let searchTimeout;

// Load all products initially
function loadAllProducts() {
    $.ajax({
        url: 'ajax_search_products.php',
        method: 'POST',
        data: { query: '', is_purchase: '1' },
        success: function(response) {
            document.getElementById('searchResults').innerHTML = response;
        },
        error: function() {
            document.getElementById('searchResults').innerHTML = '<p class="text-red-600">Error loading products.</p>';
        }
    });
}

// Add product to purchase cart
function addToPurchaseCart(product) {
    const existingItem = purchaseCart.find(item => item.id === product.id);
    
    if (existingItem) {
        existingItem.quantity++;
    } else {
        // Get average cost price for this product
        const avgCostPrice = parseFloat(product.avg_cost_price) || parseFloat(product.cost_price) || 0;
        
        purchaseCart.push({
            id: product.id,
            name: product.name,
            cost_price: avgCostPrice,
            quantity: 1,
            stock: product.stock
        });
    }
    
    updatePurchaseCartDisplay();
}

// Update quantity in purchase cart
function updatePurchaseQuantity(index, change) {
    const item = purchaseCart[index];
    const newQuantity = item.quantity + change;
    
    if (newQuantity <= 0) {
        purchaseCart.splice(index, 1);
    } else {
        item.quantity = newQuantity;
    }
    
    updatePurchaseCartDisplay();
}

// Update cost price for an item
function updateCostPrice(index, newPrice) {
    purchaseCart[index].cost_price = parseFloat(newPrice);
    updatePurchaseCartDisplay();
}

// Update purchase cart display
function updatePurchaseCartDisplay() {
    const cartContainer = document.getElementById('purchaseCart');
    const processBtn = document.getElementById('processPurchaseBtn');
    
    if (purchaseCart.length === 0) {
        cartContainer.innerHTML = '<p class="text-gray-500 text-center py-8">Cart is empty</p>';
        processBtn.disabled = true;
        updateCartTotal();
        return;
    }
    
    let cartHTML = '<table class="w-full text-sm table-fixed">';
    cartHTML += '<thead class="bg-gray-100 dark:bg-gray-900"><tr>';
    cartHTML += '<th class="text-left py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-1/2">Item</th>';
    cartHTML += '<th class="text-center py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-16">Qty</th>';
    cartHTML += '<th class="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-24">Cost Price</th>';
    cartHTML += '<th class="text-right py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-28">Total</th>';
    cartHTML += '<th class="text-center py-1 px-2 font-medium text-gray-700 dark:text-gray-300 w-10">Action</th>';
    cartHTML += '</tr></thead><tbody class="bg-white dark:bg-gray-800">';
    
    purchaseCart.forEach((item, index) => {
        const total = item.cost_price * item.quantity;
        cartHTML += '<tr class="border-b border-gray-200 dark:border-gray-700">';
        cartHTML += '<td class="py-1 px-2 text-gray-900 dark:text-gray-100 whitespace-nowrap w-1/2">' + item.name + '</td>';
        cartHTML += '<td class="py-1 px-2 text-center text-gray-900 dark:text-gray-100 whitespace-nowrap w-16">';
        cartHTML += '<button onclick="updatePurchaseQuantity(' + index + ', -1)" class="bg-red-500 hover:bg-red-700 text-white p-0.5 rounded text-xs mr-1" title="Decrease" aria-label="Decrease">';
        cartHTML += '<i class="fas fa-minus text-xs"></i>';
        cartHTML += '</button>';
        cartHTML += '<span class="mx-1 align-middle text-xs">' + item.quantity + '</span>';
        cartHTML += '<button onclick="updatePurchaseQuantity(' + index + ', 1)" class="bg-green-500 hover:bg-green-700 text-white p-0.5 rounded text-xs ml-1" title="Increase" aria-label="Increase">';
        cartHTML += '<i class="fas fa-plus text-xs"></i>';
        cartHTML += '</button>';
        cartHTML += '</td>';
        cartHTML += '<td class="py-1 px-2 text-right whitespace-nowrap w-24">';
        cartHTML += '<input type="number" step="0.01" min="0" value="' + item.cost_price.toFixed(2) + '" ';
        cartHTML += 'onchange="updateCostPrice(' + index + ', this.value)" ';
        cartHTML += 'class="w-20 text-right border border-gray-300 dark:border-gray-700 rounded px-1 py-1 text-xs bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">';
        cartHTML += '</td>';
        cartHTML += '<td class="py-1 px-2 text-right font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap w-28">PKR ' + total.toFixed(2) + '</td>';
        cartHTML += '<td class="py-1 px-2 text-center whitespace-nowrap w-10">';
        cartHTML += '<button onclick="removeFromPurchaseCart(' + index + ')" class="text-red-600 hover:text-red-900 text-xs">';
        cartHTML += '<i class="fas fa-trash"></i>';
        cartHTML += '</button>';
        cartHTML += '</td>';
        cartHTML += '</tr>';
    });
    
    cartHTML += '</tbody></table>';
    cartContainer.innerHTML = cartHTML;
    
    processBtn.disabled = false;
    updateCartTotal();
}

function isTypingInPurchaseForm(){
  const ae=document.activeElement; if(!ae) return false; const t=(ae.tagName||'').toLowerCase(); return t==='input'||t==='textarea'||t==='select'||ae.isContentEditable;
}

function adjustLastPurchaseQty(delta){ if(!purchaseCart.length) return; const idx=purchaseCart.length-1; updatePurchaseQuantity(idx, delta); }

// Remove item from purchase cart
function removeFromPurchaseCart(index) {
    purchaseCart.splice(index, 1);
    updatePurchaseCartDisplay();
}

// Update cart total
function updateCartTotal() {
    const total = purchaseCart.reduce((sum, item) => sum + (item.cost_price * item.quantity), 0);
    document.getElementById('cartTotal').textContent = 'PKR ' + total.toFixed(2);
}

// Process purchase
function processPurchase() {
    if (purchaseCart.length === 0) {
        alert('Purchase cart is empty.');
        return;
    }
    
    const vendorId = document.getElementById('vendorSelect').value;
    const purchaseDate = document.getElementById('purchaseDate').value;
    const paymentMethod = document.getElementById('paymentMethod').value;
    const notes = document.getElementById('purchaseNotes').value;
    
    if (!vendorId || !purchaseDate || !paymentMethod) {
        alert('Please fill all required fields.');
        return;
    }
    
    const total = purchaseCart.reduce((sum, item) => sum + (item.cost_price * item.quantity), 0);
    
    // Create form and submit
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="vendor_id" value="${vendorId}">
        <input type="hidden" name="purchase_date" value="${purchaseDate}">
        <input type="hidden" name="total_amount" value="${total}">
        <input type="hidden" name="payment_method" value="${paymentMethod}">
        <input type="hidden" name="notes" value="${notes}">
        <input type="hidden" name="items" value='${JSON.stringify(purchaseCart)}'>
    `;
    document.body.appendChild(form);
    form.submit();
}

function openPurchaseControlsModal(){ const m=document.getElementById('purchaseControlsModal'); if(m) m.classList.remove('hidden'); }
function closePurchaseControlsModal(){ const m=document.getElementById('purchaseControlsModal'); if(m) m.classList.add('hidden'); }

// Keyboard shortcuts for purchase modal
document.addEventListener('keydown', function(e){
  // Only active when add purchase modal is open
  const addModal = document.getElementById('addModal');
  if (!addModal || addModal.classList.contains('hidden')) return;
  if (isTypingInPurchaseForm()) return;
  // Ctrl+F or F2 focuses search field in modal
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase()==='f') { e.preventDefault(); const si=document.getElementById('searchInput'); if (si) si.focus(); return; }
  if (e.key==='F2') { e.preventDefault(); const si=document.getElementById('searchInput'); if (si) si.focus(); return; }
  // + / - adjust last item qty
  if (e.key==='+' || e.key==='=') { e.preventDefault(); adjustLastPurchaseQty(1); return; }
  if (e.key==='-' || e.key==='_') { e.preventDefault(); adjustLastPurchaseQty(-1); return; }
});

// Modal functions
function showAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
    loadAllProducts();
    purchaseCart = [];
    updatePurchaseCartDisplay();
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
    purchaseCart = [];
    updatePurchaseCartDisplay();
}

function viewPurchase(id) {
    // Fetch purchase details via AJAX
    fetch('get_purchase_details.php?purchase_id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPurchaseDetails(data.purchase);
            } else {
                alert('Error loading purchase details: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading purchase details: ' + error.message);
        });
}

function showPurchaseDetails(purchaseData) {
    const detailsContainer = document.getElementById('purchaseDetails');
    
    let itemsHTML = '';
    purchaseData.items.forEach(item => {
        const total = item.cost_price * item.quantity;
        itemsHTML += `
            <tr class="border-b">
                <td class="py-2 px-4 text-sm">${item.name}</td>
                <td class="py-2 px-4 text-sm text-center">${item.quantity}</td>
                <td class="py-2 px-4 text-sm text-right">PKR ${item.cost_price.toFixed(2)}</td>
                <td class="py-2 px-4 text-sm text-right font-medium">PKR ${total.toFixed(2)}</td>
            </tr>
        `;
    });
    
    const detailsHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Purchase Information</h4>
                <p><strong>Purchase ID:</strong> ${purchaseData.id}</p>
                <p><strong>Date:</strong> ${purchaseData.purchase_date}</p>
                <p><strong>Vendor:</strong> ${purchaseData.vendor_name}</p>
                <p><strong>Payment Method:</strong> ${purchaseData.payment_method}</p>
                <p><strong>Added By:</strong> ${purchaseData.user_name}</p>
            </div>
            <div>
                <h4 class="font-semibold text-gray-900 mb-2">Notes</h4>
                <p class="text-gray-600">${purchaseData.notes || 'No notes'}</p>
            </div>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-semibold text-gray-900 mb-4">Purchase Items</h4>
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="text-left py-2 px-4 font-medium text-gray-700">Item</th>
                        <th class="text-center py-2 px-4 font-medium text-gray-700">Quantity</th>
                        <th class="text-right py-2 px-4 font-medium text-gray-700">Cost Price</th>
                        <th class="text-right py-2 px-4 font-medium text-gray-700">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHTML}
                </tbody>
                <tfoot class="bg-gray-100">
                    <tr>
                        <td colspan="3" class="py-2 px-4 text-right font-semibold">Total:</td>
                        <td class="py-2 px-4 text-right font-bold">PKR ${purchaseData.total_amount.toFixed(2)}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    `;
    
    detailsContainer.innerHTML = detailsHTML;
    document.getElementById('viewModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function deletePurchase(id) {
    if (confirm('Are you sure you want to delete this purchase? This will reverse the stock changes.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
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
                    data: { query: query, is_purchase: '1' },
                    success: function(response) {
                        document.getElementById('searchResults').innerHTML = response;
                    },
                    error: function() {
                        document.getElementById('searchResults').innerHTML = '<p class="text-red-600">Error searching products.</p>';
                    }
                });
            }, 300);
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>

