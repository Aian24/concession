<?php
$users = ['shelby', 'donna', 'pamela', 'regine', 'grace', 'julie', 'richeel', 'joan', 'shane', 'arnulfo', 'roger', 'marial'];
$sql = "INSERT IGNORE INTO users (username, password, store_code, role) VALUES \n";
$values = [];
foreach ($users as $u) {
    $hash = password_hash($u, PASSWORD_DEFAULT);
    // For these users, we'll use their username as their store_code as a default, 
    // or you might want to change this to a specific store code later.
    $values[] = "('$u', '$hash', '$u', 'user')";
}
$sql .= implode(",\n", $values) . ";";

file_put_contents('scratch/insert_users.sql', $sql);
echo "SQL generated in scratch/insert_users.sql\n";
?>
