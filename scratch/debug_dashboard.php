<?php
require_once 'includes/db.php';
$db = db_connect();

echo "SALES COUNT: ";
$res = $db->query("SELECT COUNT(*) FROM sales");
echo $res->fetch_row()[0] . "\n";

echo "LAST 7 DAYS ACTIVITY:\n";
$chart_res = $db->query("SELECT DATE(created_at) as d, SUM(line_total) as total FROM sales WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(created_at)");
while($row = $chart_res->fetch_assoc()) {
    print_r($row);
}
?>
