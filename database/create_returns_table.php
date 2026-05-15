<?php
/**
 * create_returns_table.php
 * Run this to initialize the returns table.
 */
require_once __DIR__ . '/../includes/db.php';
$db = db_connect();

$sql = "CREATE TABLE IF NOT EXISTS returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    store_code VARCHAR(20) NOT NULL,
    return_item VARCHAR(100) NOT NULL,
    return_amount DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    is_exchange BOOLEAN DEFAULT 0,
    exchange_item VARCHAR(100) DEFAULT NULL,
    exchange_amount DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (username),
    INDEX (store_code),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($db->query($sql)) {
    echo "Returns table created successfully.\n";
} else {
    echo "Error creating returns table: " . $db->error . "\n";
}
