<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    // Handle POST requests (add, edit, delete)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['action'])) {
            throw new Exception('Action is required');
        }

        switch ($input['action']) {
            case 'add':
                $name = trim($input['name']);
                $contact_person = trim($input['contact_person']);
                $phone = trim($input['phone']);
                $email = trim($input['email']);
                $address = trim($input['address']);
                
                if (empty($name) || empty($contact_person) || empty($phone)) {
                    throw new Exception('Please fill all required fields (Name, Contact Person, Phone).');
                }
                
                $stmt = mysqli_prepare($conn, "INSERT INTO vendors (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sssss", $name, $contact_person, $phone, $email, $address);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Vendor added successfully.']);
                } else {
                    throw new Exception('Error adding vendor: ' . mysqli_error($conn));
                }
                break;
                
            case 'edit':
                $id = intval($input['id']);
                $name = trim($input['name']);
                $contact_person = trim($input['contact_person']);
                $phone = trim($input['phone']);
                $email = trim($input['email']);
                $address = trim($input['address']);
                
                if (empty($name) || empty($contact_person) || empty($phone)) {
                    throw new Exception('Please fill all required fields (Name, Contact Person, Phone).');
                }
                
                $stmt = mysqli_prepare($conn, "UPDATE vendors SET name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "sssssi", $name, $contact_person, $phone, $email, $address, $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Vendor updated successfully.']);
                } else {
                    throw new Exception('Error updating vendor: ' . mysqli_error($conn));
                }
                break;
                
            case 'delete':
                $id = intval($input['id']);
                
                // Check if vendor has any purchases
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM purchases WHERE vendor_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                
                if ($row['count'] > 0) {
                    throw new Exception('Cannot delete vendor. They have associated purchases.');
                }
                
                $stmt = mysqli_prepare($conn, "DELETE FROM vendors WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                
                if (mysqli_stmt_execute($stmt)) {
                    echo json_encode(['success' => true, 'message' => 'Vendor deleted successfully.']);
                } else {
                    throw new Exception('Error deleting vendor: ' . mysqli_error($conn));
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    }
    
    // Handle GET requests (fetch vendors)
    else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = "SELECT * FROM vendors ORDER BY name";
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            throw new Exception("Error fetching vendors: " . mysqli_error($conn));
        }

        $vendors = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $vendors[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'contact_person' => $row['contact_person'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'address' => $row['address']
            ];
        }

        echo json_encode([
            'success' => true,
            'vendors' => $vendors
        ]);
    }

} catch (Exception $e) {
    error_log("Vendors management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
