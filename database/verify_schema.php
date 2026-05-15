<?php
require_once __DIR__ . '/../includes/db.php';
$db = db_connect();
$res = $db->query("DESCRIBE returns");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " -> " . $row['Null'] . "\n";
}
