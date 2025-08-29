<?php
require_once 'includes/db.php';

echo "<h2>Database Connection Test</h2>";
if ($conn) {
    echo "✅ Database connected successfully<br>";
} else {
    echo "❌ Database connection failed<br>";
    exit;
}

echo "<h2>Users Table Check</h2>";
$result = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($result) > 0) {
    echo "✅ Users table exists<br>";
} else {
    echo "❌ Users table does not exist<br>";
    exit;
}

echo "<h2>Current Users</h2>";
$result = mysqli_query($conn, "SELECT id, username, active FROM users");
if ($result) {
    if (mysqli_num_rows($result) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Username</th><th>Active</th></tr>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['username'] . "</td>";
            echo "<td>" . $row['active'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ No users found in database<br>";
    }
} else {
    echo "❌ Error querying users: " . mysqli_error($conn) . "<br>";
}

echo "<h2>Create Test User</h2>";
$test_username = 'admin';
$test_password = 'admin123';
$hashed_password = password_hash($test_password, PASSWORD_DEFAULT);

// Check if test user already exists
$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt, "s", $test_username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    echo "✅ Test user 'admin' already exists<br>";
} else {
    // Create test user
    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, active) VALUES (?, ?, 1)");
    mysqli_stmt_bind_param($stmt, "ss", $test_username, $hashed_password);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "✅ Test user created successfully<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else {
        echo "❌ Error creating test user: " . mysqli_stmt_error($stmt) . "<br>";
    }
}

echo "<h2>Test Login</h2>";
$stmt = mysqli_prepare($conn, "SELECT id, username, password FROM users WHERE username = ? AND active = 1");
mysqli_stmt_bind_param($stmt, "s", $test_username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if ($user && password_verify($test_password, $user['password'])) {
    echo "✅ Login test successful<br>";
} else {
    echo "❌ Login test failed<br>";
}
?>
