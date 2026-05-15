<?php
$db = new mysqli('localhost', 'root', '', 'concession_db');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
$result = $db->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER role");
if ($result) {
    echo "Avatar column added successfully.";
} else {
    echo "Error: " . $db->error;
}
$db->close();
?>
