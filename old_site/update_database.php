<?php
// Update database with new payment fields
require_once 'includes/db.php';

echo "=== Updating Database Schema ===\n";

try {
    // Add new columns to sales table
    $queries = [
        "ALTER TABLE sales ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount",
        "ALTER TABLE sales ADD COLUMN payment_method ENUM('cash', 'card', 'mixed') DEFAULT 'cash' AFTER discount_amount",
        "ALTER TABLE sales ADD COLUMN cash_amount DECIMAL(10,2) DEFAULT 0.00 AFTER payment_method",
        "ALTER TABLE sales ADD COLUMN card_amount DECIMAL(10,2) DEFAULT 0.00 AFTER cash_amount"
    ];
    
    foreach ($queries as $query) {
        if (mysqli_query($conn, $query)) {
            echo "✓ " . substr($query, 0, 50) . "...\n";
        } else {
            echo "✗ Error: " . mysqli_error($conn) . "\n";
        }
    }
    
    echo "\n=== Database Update Complete ===\n";
    echo "New payment features are now available!\n";
    
} catch (Exception $e) {
    echo "✗ Error updating database: " . $e->getMessage() . "\n";
}
?>
