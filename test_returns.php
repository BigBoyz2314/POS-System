<?php
require_once 'includes/db.php';

// Insert sample returns data
$sample_returns = [
    [
        'sale_id' => 1,
        'product_id' => 1,
        'quantity' => 2,
        'reason' => 'Defective product',
        'refund_amount' => 50.00
    ],
    [
        'sale_id' => 1,
        'product_id' => 2,
        'quantity' => 1,
        'reason' => 'Wrong size',
        'refund_amount' => 25.00
    ],
    [
        'sale_id' => 2,
        'product_id' => 3,
        'quantity' => 3,
        'reason' => 'Customer changed mind',
        'refund_amount' => 75.00
    ]
];

// Insert sample return receipts
$sample_receipts = [
    [
        'sale_id' => 1,
        'total_refund' => 75.00,
        'cash_refund' => 50.00,
        'card_refund' => 25.00,
        'payload' => json_encode([
            'items' => [
                [
                    'product_name' => 'Sample Product 1',
                    'return_qty' => 2,
                    'quantity' => 2,
                    'price' => 25.00,
                    'refund_amount' => 50.00,
                    'reason' => 'Defective product'
                ],
                [
                    'product_name' => 'Sample Product 2',
                    'return_qty' => 1,
                    'quantity' => 1,
                    'price' => 25.00,
                    'refund_amount' => 25.00,
                    'reason' => 'Wrong size'
                ]
            ]
        ])
    ],
    [
        'sale_id' => 2,
        'total_refund' => 75.00,
        'cash_refund' => 75.00,
        'card_refund' => 0.00,
        'payload' => json_encode([
            'items' => [
                [
                    'product_name' => 'Sample Product 3',
                    'return_qty' => 3,
                    'quantity' => 3,
                    'price' => 25.00,
                    'refund_amount' => 75.00,
                    'reason' => 'Customer changed mind'
                ]
            ]
        ])
    ]
];

try {
    // Create tables if they don't exist
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        sale_item_id INT NULL,
        product_id INT NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        refund_amount DECIMAL(10,2) NOT NULL,
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS return_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        payload LONGTEXT NULL,
        total_refund DECIMAL(10,2) NOT NULL,
        cash_refund DECIMAL(10,2) NOT NULL,
        card_refund DECIMAL(10,2) NOT NULL,
        user_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insert sample returns
    foreach ($sample_returns as $return) {
        $sql = "INSERT INTO returns (sale_id, product_id, quantity, reason, refund_amount) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iidsd", 
            $return['sale_id'], 
            $return['product_id'], 
            $return['quantity'], 
            $return['reason'], 
            $return['refund_amount']
        );
        mysqli_stmt_execute($stmt);
    }

    // Insert sample receipts
    foreach ($sample_receipts as $receipt) {
        $sql = "INSERT INTO return_receipts (sale_id, payload, total_refund, cash_refund, card_refund) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isddd", 
            $receipt['sale_id'], 
            $receipt['payload'], 
            $receipt['total_refund'], 
            $receipt['cash_refund'], 
            $receipt['card_refund']
        );
        mysqli_stmt_execute($stmt);
    }

    echo "Sample returns data inserted successfully!\n";
    echo "You can now test the Returns page.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
