<?php
require 'includes/db.php';
$db = db_connect();

$tables = $db->query("SHOW TABLES")->fetch_all(MYSQLI_NUM);
print_r($tables);
