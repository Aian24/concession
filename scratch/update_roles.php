<?php
require_once 'includes/db.php';
$db = db_connect();

$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user', 'admin_view', 'store_admin') DEFAULT 'user'";

if ($db->query($sql)) {
    echo "Database updated successfully.\n";
} else {
    echo "Error updating database: " . $db->error . "\n";
}
?>
