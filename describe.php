<?php
require 'includes/db.php';
$db = db_connect();
$tables = ['sales', 'returns', 'receiving', 'pullouts'];
foreach ($tables as $t) {
    echo "$t columns:\n";
    $res = $db->query("DESCRIBE $t");
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "\n";
}
