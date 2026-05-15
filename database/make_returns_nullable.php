<?php
require_once __DIR__ . '/../includes/db.php';
$db = db_connect();

$sql = "ALTER TABLE returns 
        MODIFY COLUMN return_item VARCHAR(100) NULL,
        MODIFY COLUMN return_amount DECIMAL(10,2) NULL,
        MODIFY COLUMN reason TEXT NULL";

if ($db->query($sql)) {
    echo "Columns return_item, return_amount, and reason are now nullable.\n";
} else {
    echo "Error: " . $db->error . "\n";
}
