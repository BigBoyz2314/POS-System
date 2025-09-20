<?php
// Database configuration
$host = 'localhost';
$username = 'root';  // Change this to your database username
$password = '';      // Change this to your database password
$database = 'pos_system'; // Change this to your database name

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8
mysqli_set_charset($conn, "utf8");
?>
