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

// Get products for dropdown
$products = mysqli_query($conn, "SELECT * FROM products ORDER BY name");

// Get purchases with vendor and user info
$query = "SELECT p.*, v.name as vendor_name, u.name as user_name,
          (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) as items_count
          FROM purchases p 
          LEFT JOIN vendors v ON p.vendor_id = v.id 
          LEFT JOIN users u ON p.user_id = u.id 
          ORDER BY p.purchase_date DESC";
$purchases = mysqli_query($conn, $query);

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Purchase Management</h1>
        <button onclick="showAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Add New Purchase
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
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Purchases</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Added By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($purchase = mysqli_fetch_assoc($purchases)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo date('M d, Y', strtotime($purchase['purchase_date'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            #<?php echo $purchase['id']; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($purchase['vendor_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $purchase['items_count']; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            PKR <?php echo number_format($purchase['total_amount'], 2); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo strtoupper($purchase['payment_method']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($purchase['user_name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="viewPurchase(<?php echo $purchase['id']; ?>)" 
                                    class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                            <button onclick="deletePurchase(<?php echo $purchase['id']; ?>)"
                                    class="text-red-600 hover:text-red-900">Delete</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Purchase Modal -->
<div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Purchase</h3>
            <form method="POST" id="purchaseForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="items" id="itemsInput">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Vendor *</label>
                        <select name="vendor_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Vendor</option>
                            <?php mysqli_data_seek($vendors, 0); ?>
                            <?php while ($vendor = mysqli_fetch_assoc($vendors)): ?>
                            <option value="<?php echo $vendor['id']; ?>"><?php echo htmlspecialchars($vendor['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Purchase Date *</label>
                        <input type="date" name="purchase_date" required value="<?php echo date('Y-m-d'); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                        <select name="payment_method" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Total Amount (PKR) *</label>
                        <input type="number" name="total_amount" id="totalAmount" step="0.01" min="0" required readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <!-- Purchase Items -->
                <div class="mb-6">
                    <h4 class="text-md font-medium text-gray-900 mb-3">Purchase Items</h4>
                    <div id="purchaseItems" class="space-y-3">
                        <!-- Items will be added here dynamically -->
                    </div>
                    <button type="button" onclick="addItem()" 
                            class="mt-3 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Add Item
                    </button>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeAddModal()" 
                            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Add Purchase
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Purchase Modal -->
<div id="viewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Purchase Details</h3>
            <div id="purchaseDetails">
                <!-- Purchase details will be loaded here -->
            </div>
            <div class="flex justify-end mt-6">
                <button onclick="closeViewModal()" 
                        class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let purchaseItems = [];
let itemCounter = 0;

function showAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
    addItem(); // Add first item by default
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
    purchaseItems = [];
    itemCounter = 0;
    document.getElementById('purchaseItems').innerHTML = '';
    document.getElementById('purchaseForm').reset();
}

function addItem() {
    const itemDiv = document.createElement('div');
    itemDiv.className = 'grid grid-cols-1 md:grid-cols-4 gap-3 p-3 border rounded';
    itemDiv.innerHTML = `
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Product *</label>
            <select name="product_id_${itemCounter}" required onchange="updateItemTotal(${itemCounter})"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select Product</option>
                <?php mysqli_data_seek($products, 0); ?>
                <?php while ($product = mysqli_fetch_assoc($products)): ?>
                <option value="<?php echo $product['id']; ?>" data-price="<?php echo $product['price']; ?>">
                    <?php echo htmlspecialchars($product['name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
            <input type="number" name="quantity_${itemCounter}" min="1" required onchange="updateItemTotal(${itemCounter})"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cost Price (PKR) *</label>
            <input type="number" name="cost_price_${itemCounter}" step="0.01" min="0" required onchange="updateItemTotal(${itemCounter})"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex items-end">
            <button type="button" onclick="removeItem(${itemCounter})" 
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                Remove
            </button>
        </div>
    `;
    
    document.getElementById('purchaseItems').appendChild(itemDiv);
    purchaseItems.push({
        id: itemCounter,
        product_id: '',
        quantity: 0,
        cost_price: 0,
        total: 0
    });
    itemCounter++;
}

function removeItem(id) {
    purchaseItems = purchaseItems.filter(item => item.id !== id);
    updateTotal();
    // Re-render items
    document.getElementById('purchaseItems').innerHTML = '';
    purchaseItems.forEach((item, index) => {
        addItem();
        // Restore values
        const selects = document.querySelectorAll('select[name^="product_id_"]');
        const quantities = document.querySelectorAll('input[name^="quantity_"]');
        const prices = document.querySelectorAll('input[name^="cost_price_"]');
        if (selects[index]) selects[index].value = item.product_id;
        if (quantities[index]) quantities[index].value = item.quantity;
        if (prices[index]) prices[index].value = item.cost_price;
    });
}

function updateItemTotal(id) {
    const quantity = parseFloat(document.querySelector(`input[name="quantity_${id}"]`).value) || 0;
    const costPrice = parseFloat(document.querySelector(`input[name="cost_price_${id}"]`).value) || 0;
    
    const item = purchaseItems.find(item => item.id === id);
    if (item) {
        item.quantity = quantity;
        item.cost_price = costPrice;
        item.total = quantity * costPrice;
    }
    
    updateTotal();
}

function updateTotal() {
    const total = purchaseItems.reduce((sum, item) => sum + item.total, 0);
    document.getElementById('totalAmount').value = total.toFixed(2);
}

function viewPurchase(id) {
    // Fetch purchase details via AJAX
    fetch('get_purchase_details.php?purchase_id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPurchaseDetails(data.purchase);
            } else {
                alert('Error loading purchase: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading purchase');
        });
}

function showPurchaseDetails(purchase) {
    const detailsDiv = document.getElementById('purchaseDetails');
    
    let itemsHTML = '';
    purchase.items.forEach(item => {
        const itemTotal = parseFloat(item.cost_price) * parseInt(item.quantity);
        itemsHTML += `
            <tr class="border-b">
                <td class="py-2 text-left">${item.product_name}</td>
                <td class="py-2 text-center">${item.quantity}</td>
                <td class="py-2 text-right">PKR ${parseFloat(item.cost_price).toFixed(2)}</td>
                <td class="py-2 text-right">PKR ${itemTotal.toFixed(2)}</td>
            </tr>
        `;
    });
    
    detailsDiv.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <p><strong>Purchase ID:</strong> #${purchase.id}</p>
                <p><strong>Date:</strong> ${new Date(purchase.purchase_date).toLocaleDateString()}</p>
                <p><strong>Vendor:</strong> ${purchase.vendor_name}</p>
            </div>
            <div>
                <p><strong>Total Amount:</strong> PKR ${parseFloat(purchase.total_amount).toFixed(2)}</p>
                <p><strong>Payment Method:</strong> ${purchase.payment_method.toUpperCase()}</p>
                <p><strong>Added By:</strong> ${purchase.user_name}</p>
            </div>
        </div>
        
        <div class="mb-4">
            <p><strong>Notes:</strong> ${purchase.notes || 'No notes'}</p>
        </div>
        
        <div class="mb-6">
            <h4 class="text-md font-medium text-gray-900 mb-3">Purchase Items</h4>
            <table class="w-full">
                <thead>
                    <tr class="border-b-2 border-gray-300">
                        <th class="py-2 text-left">Product</th>
                        <th class="py-2 text-center">Quantity</th>
                        <th class="py-2 text-right">Cost Price</th>
                        <th class="py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHTML}
                </tbody>
            </table>
        </div>
    `;
    
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

// Handle form submission
document.getElementById('purchaseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Collect items data
    const items = [];
    purchaseItems.forEach(item => {
        const productSelect = document.querySelector(`select[name="product_id_${item.id}"]`);
        const quantityInput = document.querySelector(`input[name="quantity_${item.id}"]`);
        const costPriceInput = document.querySelector(`input[name="cost_price_${item.id}"]`);
        
        if (productSelect && quantityInput && costPriceInput) {
            items.push({
                product_id: parseInt(productSelect.value),
                quantity: parseInt(quantityInput.value),
                cost_price: parseFloat(costPriceInput.value)
            });
        }
    });
    
    if (items.length === 0) {
        alert('Please add at least one item to the purchase.');
        return;
    }
    
    // Set items data
    document.getElementById('itemsInput').value = JSON.stringify(items);
    
    // Submit form
    this.submit();
});
</script>

<?php include '../includes/footer.php'; ?>
