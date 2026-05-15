<?php
require_once 'includes/db.php';
$db = db_connect();

$res = $db->query("SELECT id, username, password FROM users");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Username: {$row['username']} | Hash: {$row['password']}\n";
    if (password_verify('R4styL0p3z', $row['password'])) {
        echo "   -> VERIFIED: This hash matches 'R4styL0p3z'\n";
    }
    if (password_verify('admin123', $row['password'])) {
        echo "   -> VERIFIED: This hash matches 'admin123'\n";
    }
}
?>
