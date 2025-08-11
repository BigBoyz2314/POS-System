<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Require admin access
requireAdmin();

// Get filter parameters
$filter = $_GET['filter'] ?? 'today';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build date conditions
$date_condition = '';
$params = [];

switch ($filter) {
    case 'today':
        $date_condition = "WHERE DATE(s.date) = CURDATE()";
        break;
    case 'yesterday':
        $date_condition = "WHERE DATE(s.date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'week':
        $date_condition = "WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'custom':
        if ($start_date && $end_date) {
            $date_condition = "WHERE DATE(s.date) BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
        }
        break;
}

// Get sales statistics
$stats = [];

// Total sales and amount
$query = "SELECT COUNT(*) as total_sales, SUM(total_amount) as total_amount FROM sales s $date_condition";
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $params[0], $params[1]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}
$row = mysqli_fetch_assoc($result);
$stats['total_sales'] = $row['total_sales'];
$stats['total_amount'] = $row['total_amount'] ?: 0;

// Total items sold
$query = "SELECT SUM(si.quantity) as total_items FROM sales s 
          JOIN sale_items si ON s.id = si.sale_id $date_condition";
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $params[0], $params[1]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}
$row = mysqli_fetch_assoc($result);
$stats['total_items'] = $row['total_items'] ?: 0;

// Average sale amount
$stats['avg_sale'] = $stats['total_sales'] > 0 ? $stats['total_amount'] / $stats['total_sales'] : 0;

// Get detailed sales
$query = "SELECT s.*, u.name as cashier_name, 
          (SELECT SUM(si.quantity) FROM sale_items si WHERE si.sale_id = s.id) as items_count,
          (s.total_amount - s.discount_amount) as final_amount
          FROM sales s 
          LEFT JOIN users u ON s.user_id = u.id 
          $date_condition 
          ORDER BY s.date DESC";
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $params[0], $params[1]);
    mysqli_stmt_execute($stmt);
    $sales = mysqli_stmt_get_result($stmt);
} else {
    $sales = mysqli_query($conn, $query);
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Sales Reports</h1>
    </div>

    <!-- Filter Controls -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Filter Reports</h2>
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Quick Filter</label>
                <select name="filter" onchange="this.form.submit()" class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="today" <?php echo $filter == 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="yesterday" <?php echo $filter == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                    <option value="week" <?php echo $filter == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $filter == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="custom" <?php echo $filter == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            
            <div id="customDates" class="<?php echo $filter == 'custom' ? '' : 'hidden'; ?>">
                <div class="flex gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                               class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                               class="border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Apply Filter
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Sales -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-blue-600 uppercase tracking-wide">Total Sales</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_sales']; ?></p>
                </div>
                <div class="text-blue-500">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-green-600 uppercase tracking-wide">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900">PKR <?php echo number_format($stats['total_amount'], 2); ?></p>
                </div>
                <div class="text-green-500">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Items -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-indigo-500">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-indigo-600 uppercase tracking-wide">Items Sold</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $stats['total_items']; ?></p>
                </div>
                <div class="text-indigo-500">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Average Sale -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="flex-1">
                    <p class="text-sm font-medium text-yellow-600 uppercase tracking-wide">Avg Sale</p>
                    <p class="text-2xl font-bold text-gray-900">PKR <?php echo number_format($stats['avg_sale'], 2); ?></p>
                </div>
                <div class="text-yellow-500">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Details Table -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Sales Details</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sale ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cashier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Final Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (mysqli_num_rows($sales) > 0): ?>
                        <?php while ($sale = mysqli_fetch_assoc($sales)): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y H:i', strtotime($sale['date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                #<?php echo $sale['id']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($sale['cashier_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $sale['items_count']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                PKR <?php echo number_format($sale['total_amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                PKR <?php echo number_format($sale['discount_amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                PKR <?php echo number_format($sale['final_amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo strtoupper($sale['payment_method']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <button onclick="viewInvoice(<?php echo $sale['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-900 font-medium">
                                    View Invoice
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                No sales found for the selected period.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
.duplicate-watermark { display: none; }
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
    
    /* Single invoice with DUPLICATE watermark */
    .print-wrapper {
        position: relative;
    }
    .duplicate-watermark {
        display: block;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 40px;
        color: rgba(0,0,0,0.08);
        letter-spacing: 4px;
        z-index: 0;
        pointer-events: none;
        user-select: none;
        text-transform: uppercase;
        font-weight: 900;
    }
    #printInvoiceContent > * {
        position: relative;
        z-index: 1;
    }
}
</style>

<script>
// Show/hide custom date inputs based on filter selection
document.querySelector('select[name="filter"]').addEventListener('change', function() {
    const customDates = document.getElementById('customDates');
    if (this.value === 'custom') {
        customDates.classList.remove('hidden');
    } else {
        customDates.classList.add('hidden');
    }
});

// View invoice function
function viewInvoice(saleId) {
    console.log('Fetching invoice for sale ID:', saleId);
    
    // Fetch sale data via AJAX
    fetch('get_sale_details.php?sale_id=' + saleId)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                showInvoice(data.sale);
            } else {
                alert('Error loading invoice: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading invoice: ' + error.message);
        });
}

// Show invoice modal
function showInvoice(saleData) {
    const invoiceContent = document.getElementById('invoiceContent');
    const printInvoiceContent = document.getElementById('printInvoiceContent');
    const currentDate = new Date(saleData.date).toLocaleDateString();
    const currentTime = new Date(saleData.date).toLocaleTimeString();
    
    let itemsHTML = '';
    saleData.items.forEach(item => {
        const price = parseFloat(item.price);
        const quantity = parseInt(item.quantity);
        const itemTotal = price * quantity;
        itemsHTML += `
            <tr class="border-b">
                <td class="py-2 text-left">${item.name}</td>
                <td class="py-2 text-center">${item.quantity}</td>
                <td class="py-2 text-right">${price.toFixed(2)}</td>
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
                    <span>PKR ${parseFloat(saleData.total_amount).toFixed(2)}</span>
                </div>
                <div class="flex justify-between mb-2">
                    <span>Discount:</span>
                    <span>-PKR ${parseFloat(saleData.discount_amount).toFixed(2)}</span>
                </div>
                <div class="flex justify-between font-bold text-lg">
                    <span>Total:</span>
                    <span>PKR ${(parseFloat(saleData.total_amount) - parseFloat(saleData.discount_amount)).toFixed(2)}</span>
                </div>
            </div>
            
            <div class="border-t pt-4 mt-4">
                <div class="flex justify-between mb-2">
                    <span>Payment Method:</span>
                    <span>${saleData.payment_method.toUpperCase()}</span>
                </div>
                ${parseFloat(saleData.cash_amount) > 0 ? `<div class="flex justify-between mb-2"><span>Cash:</span><span>PKR ${parseFloat(saleData.cash_amount).toFixed(2)}</span></div>` : ''}
                ${parseFloat(saleData.card_amount) > 0 ? `<div class="flex justify-between mb-2"><span>Card:</span><span>PKR ${parseFloat(saleData.card_amount).toFixed(2)}</span></div>` : ''}
                ${(parseFloat(saleData.cash_amount) + parseFloat(saleData.card_amount)) > (parseFloat(saleData.total_amount) - parseFloat(saleData.discount_amount)) ? `<div class="flex justify-between mb-2"><span>Change:</span><span>PKR ${((parseFloat(saleData.cash_amount) + parseFloat(saleData.card_amount)) - (parseFloat(saleData.total_amount) - parseFloat(saleData.discount_amount))).toFixed(2)}</span></div>` : ''}
            </div>
            
            <div class="text-center mt-8">
                <p class="text-gray-600">Thank you for your purchase!</p>
            </div>
        </div>
    `;
    
    // Set content for modal (single invoice)
    invoiceContent.innerHTML = invoiceHTML;
    
    // Set content for print (single invoice with DUPLICATE watermark overlay)
    const printWrapperHTML = `
        <div class="print-wrapper">
            <div class="duplicate-watermark">DUPLICATE</div>
            ${invoiceHTML}
        </div>
    `;
    printInvoiceContent.innerHTML = printWrapperHTML;
    
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
}
</script>

<?php include '../includes/footer.php'; ?>
