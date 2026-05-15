<?php
require_once __DIR__ . '/../includes/db.php';
$db = db_connect();

$sql = "ALTER TABLE returns ADD COLUMN exchange_name VARCHAR(100) AFTER is_exchange";
if ($db->query($sql)) {
    echo "Column exchange_name added successfully.\n";
} else {
    echo "Error: " . $db->error . "\n";
}
