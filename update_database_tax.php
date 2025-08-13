<?php
// Database update script for tax functionality
// Run this script once to add tax-related columns to existing database

require_once 'includes/db.php';

echo "<h2>Database Update Script - Tax Functionality</h2>";
echo "<p>Adding tax-related columns to existing database...</p>";

try {
    // 1. Add tax_rate column to products table
    echo "<p>1. Adding tax_rate column to products table...</p>";
    $sql = "ALTER TABLE products ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER cost_price";
    if (mysqli_query($conn, $sql)) {
        echo "<p style='color: green;'>✓ tax_rate column added to products table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding tax_rate column: " . mysqli_error($conn) . "</p>";
    }

    // 2. Add subtotal_amount and tax_amount columns to sales table
    echo "<p>2. Adding subtotal_amount and tax_amount columns to sales table...</p>";
    
    // Check if subtotal_amount column exists
    $result = mysqli_query($conn, "SHOW COLUMNS FROM sales LIKE 'subtotal_amount'");
    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE sales ADD COLUMN subtotal_amount DECIMAL(10,2) NOT NULL AFTER total_amount";
        if (mysqli_query($conn, $sql)) {
            echo "<p style='color: green;'>✓ subtotal_amount column added to sales table</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding subtotal_amount column: " . mysqli_error($conn) . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ subtotal_amount column already exists</p>";
    }

    // Check if tax_amount column exists
    $result = mysqli_query($conn, "SHOW COLUMNS FROM sales LIKE 'tax_amount'");
    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE sales ADD COLUMN tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal_amount";
        if (mysqli_query($conn, $sql)) {
            echo "<p style='color: green;'>✓ tax_amount column added to sales table</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding tax_amount column: " . mysqli_error($conn) . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ tax_amount column already exists</p>";
    }

    // 3. Add tax_rate and tax_amount columns to sale_items table
    echo "<p>3. Adding tax_rate and tax_amount columns to sale_items table...</p>";
    
    // Check if tax_rate column exists in sale_items
    $result = mysqli_query($conn, "SHOW COLUMNS FROM sale_items LIKE 'tax_rate'");
    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE sale_items ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER price";
        if (mysqli_query($conn, $sql)) {
            echo "<p style='color: green;'>✓ tax_rate column added to sale_items table</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding tax_rate column: " . mysqli_error($conn) . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ tax_rate column already exists in sale_items</p>";
    }

    // Check if tax_amount column exists in sale_items
    $result = mysqli_query($conn, "SHOW COLUMNS FROM sale_items LIKE 'tax_amount'");
    if (mysqli_num_rows($result) == 0) {
        $sql = "ALTER TABLE sale_items ADD COLUMN tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER tax_rate";
        if (mysqli_query($conn, $sql)) {
            echo "<p style='color: green;'>✓ tax_amount column added to sale_items table</p>";
        } else {
            echo "<p style='color: red;'>✗ Error adding tax_amount column: " . mysqli_error($conn) . "</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ tax_amount column already exists in sale_items</p>";
    }

    // 4. Update existing sales records to have proper subtotal and tax values
    echo "<p>4. Updating existing sales records...</p>";
    
    // Get all sales that don't have subtotal_amount set
    $result = mysqli_query($conn, "SELECT id, total_amount FROM sales WHERE subtotal_amount = 0 OR subtotal_amount IS NULL");
    $updated_count = 0;
    
    while ($sale = mysqli_fetch_assoc($result)) {
        // For existing sales, set subtotal = total and tax = 0 (since they were created before tax system)
        $sql = "UPDATE sales SET subtotal_amount = ?, tax_amount = 0.00 WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "di", $sale['total_amount'], $sale['id']);
        
        if (mysqli_stmt_execute($stmt)) {
            $updated_count++;
        }
    }
    
    echo "<p style='color: green;'>✓ Updated $updated_count existing sales records</p>";

    // 5. Update existing sale_items to have tax information
    echo "<p>5. Updating existing sale_items records...</p>";
    
    // Get all sale_items that don't have tax_rate set
    $result = mysqli_query($conn, "SELECT id FROM sale_items WHERE tax_rate = 0 OR tax_rate IS NULL");
    $updated_items = 0;
    
    while ($item = mysqli_fetch_assoc($result)) {
        // For existing items, set tax_rate = 0 and tax_amount = 0
        $sql = "UPDATE sale_items SET tax_rate = 0.00, tax_amount = 0.00 WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $item['id']);
        
        if (mysqli_stmt_execute($stmt)) {
            $updated_items++;
        }
    }
    
    echo "<p style='color: green;'>✓ Updated $updated_items existing sale_items records</p>";

    // 6. Set default tax rates for existing products (optional)
    echo "<p>6. Setting default tax rates for existing products...</p>";
    
    $result = mysqli_query($conn, "UPDATE products SET tax_rate = 0.00 WHERE tax_rate = 0 OR tax_rate IS NULL");
    if ($result) {
        $affected = mysqli_affected_rows($conn);
        echo "<p style='color: green;'>✓ Set default tax rate (0%) for $affected products</p>";
    } else {
        echo "<p style='color: red;'>✗ Error updating product tax rates: " . mysqli_error($conn) . "</p>";
    }

    echo "<hr>";
    echo "<h3 style='color: green;'>✅ Database update completed successfully!</h3>";
    echo "<p><strong>Summary of changes:</strong></p>";
    echo "<ul>";
    echo "<li>Added tax_rate column to products table</li>";
    echo "<li>Added subtotal_amount and tax_amount columns to sales table</li>";
    echo "<li>Added tax_rate and tax_amount columns to sale_items table</li>";
    echo "<li>Updated existing sales and sale_items records</li>";
    echo "<li>Set default tax rates for existing products</li>";
    echo "</ul>";
    
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Update product tax rates in the Products module</li>";
    echo "<li>Test the new tax functionality in the Sales module</li>";
    echo "<li>Delete this script after successful execution</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Fatal error: " . $e->getMessage() . "</p>";
}

mysqli_close($conn);
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
h2 {
    color: #333;
    border-bottom: 2px solid #007cba;
    padding-bottom: 10px;
}
p {
    margin: 5px 0;
    padding: 5px;
    background-color: white;
    border-radius: 3px;
}
ul {
    background-color: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
