<?php
// CORS headers for React development
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();
require_once '../includes/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        
        // Debug: Log the input (remove in production)
        error_log("Login attempt - Username: $username, Password length: " . strlen($password));
        
        if (empty($username) || empty($password)) {
            throw new Exception('Username and password are required');
        }
        
        // Check user credentials
        $stmt = mysqli_prepare($conn, "SELECT id, username, password FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare login query: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        // Debug: Log user data (remove in production)
        if ($user) {
            error_log("User found - ID: {$user['id']}, Username: {$user['username']}, Password hash: " . substr($user['password'], 0, 20) . "...");
        } else {
            error_log("No user found for username: $username");
        }
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $passwordValid = password_verify($password, $user['password']);
        error_log("Password verification result: " . ($passwordValid ? 'TRUE' : 'FALSE'));
        
        if (!$passwordValid) {
            throw new Exception('Invalid password for user: ' . $username);
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username']
            ]
        ]);
        
    } else {
        // GET request - check if user is logged in
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
            echo json_encode([
                'success' => true,
                'logged_in' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'logged_in' => false,
                'message' => 'Not logged in'
            ]);
        }
    }
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
