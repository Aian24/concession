<?php
require_once 'includes/db.php';
$db = db_connect();

$start_date = '2026-05-01 00:00:00';
$end_date = '2026-05-06 23:59:59';

$res = $db->query("SELECT store_code, SUM(quantity) as total_qty FROM sales WHERE created_at >= '$start_date' AND created_at <= '$end_date' GROUP BY store_code");
while($row = $res->fetch_assoc()) {
    echo "Store: {$row['store_code']}, Qty: {$row['total_qty']}\n";
}
?>
