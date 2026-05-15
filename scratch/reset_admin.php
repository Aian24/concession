<?php
require_once 'includes/db.php';
$db = db_connect();

$new_pw = 'R4styL0p3z';
$hash = password_hash($new_pw, PASSWORD_DEFAULT);

$stmt = $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $hash);

if ($stmt->execute()) {
    echo "Admin password updated successfully.\n";
} else {
    echo "Error updating password: " . $db->error . "\n";
}
$stmt->close();
?>
