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
                $name = trim($_POST['name']);
                $contact_person = trim($_POST['contact_person']);
                $phone = trim($_POST['phone']);
                $email = trim($_POST['email']);
                $address = trim($_POST['address']);
                
                if (empty($name) || empty($contact_person) || empty($phone)) {
                    $error = 'Please fill all required fields (Name, Contact Person, Phone).';
                } else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO vendors (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, "sssss", $name, $contact_person, $phone, $email, $address);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Vendor added successfully.';
                    } else {
                        $error = 'Error adding vendor.';
                    }
                }
                break;
                
            case 'edit':
                $id = intval($_POST['id']);
                $name = trim($_POST['name']);
                $contact_person = trim($_POST['contact_person']);
                $phone = trim($_POST['phone']);
                $email = trim($_POST['email']);
                $address = trim($_POST['address']);
                
                if (empty($name) || empty($contact_person) || empty($phone)) {
                    $error = 'Please fill all required fields (Name, Contact Person, Phone).';
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE vendors SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "sssssi", $name, $contact_person, $phone, $email, $address, $id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Vendor updated successfully.';
                    } else {
                        $error = 'Error updating vendor.';
                    }
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                
                // Check if vendor has any purchases
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM purchases WHERE vendor_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                
                if ($row['count'] > 0) {
                    $error = 'Cannot delete vendor. They have associated purchases.';
                } else {
                    $stmt = mysqli_prepare($conn, "DELETE FROM vendors WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Vendor deleted successfully.';
                    } else {
                        $error = 'Error deleting vendor.';
                    }
                }
                break;
        }
    }
}

// Get vendors
$vendors = mysqli_query($conn, "SELECT * FROM vendors ORDER BY name");

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
            <i class="fas fa-truck mr-3"></i>Vendor Management
        </h1>
        <button onclick="showAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-plus mr-2"></i>Add New Vendor
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

    <!-- Vendors Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Vendors</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contact Person</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php while ($vendor = mysqli_fetch_assoc($vendors)): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($vendor['name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($vendor['contact_person']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo htmlspecialchars($vendor['phone']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($vendor['email']); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <?php echo htmlspecialchars($vendor['address']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($vendor)); ?>)"
                                    class="text-indigo-600 hover:text-indigo-900 mr-3">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button onclick="deleteVendor(<?php echo $vendor['id']; ?>)" 
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

<!-- Add Vendor Modal -->
<div id="addModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) document.getElementById('addModal').classList.add('hidden')">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Vendor</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Vendor Name *</label>
                    <input type="text" name="name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact Person *</label>
                    <input type="text" name="contact_person" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                    <input type="text" name="phone" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea name="address" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeAddModal()" 
                            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        <i class="fas fa-plus mr-2"></i>Add Vendor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Vendor Modal -->
<div id="editModal" class="fixed inset-0 bg-gray-900/60 overflow-y-auto h-full w-full hidden modal-overlay" onclick="if(event.target===this) document.getElementById('editModal').classList.add('hidden')">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800 dark:text-gray-100 modal-panel" onclick="event.stopPropagation()">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Vendor</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Vendor Name *</label>
                    <input type="text" name="name" id="editName" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Contact Person *</label>
                    <input type="text" name="contact_person" id="editContactPerson" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                    <input type="text" name="phone" id="editPhone" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" id="editEmail"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea name="address" id="editAddress" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeEditModal()" 
                            class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        <i class="fas fa-save mr-2"></i>Update Vendor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
}

function showEditModal(vendor) {
    document.getElementById('editId').value = vendor.id;
    document.getElementById('editName').value = vendor.name;
    document.getElementById('editContactPerson').value = vendor.contact_person;
    document.getElementById('editPhone').value = vendor.phone;
    document.getElementById('editEmail').value = vendor.email;
    document.getElementById('editAddress').value = vendor.address;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function deleteVendor(id) {
    if (confirm('Are you sure you want to delete this vendor?')) {
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
</script>

<?php include '../includes/footer.php'; ?>
