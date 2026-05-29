<?php
session_start();
$is_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
if (!$is_admin) {
    die("Unauthorized");
}

require_once '../includes/db.php';
$db = db_connect();

$format = $_GET['format'] ?? 'excel';
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');
$store_filter = $_GET['store_filter'] ?? '';

// Generate dates
$dates = [];
$current = strtotime($start_date);
$end = strtotime($end_date);
if (($end - $current) > (365 * 86400)) {
    $end = $current + (365 * 86400); // Max 1 year range
}
while ($current <= $end) {
    $dates[] = date('Y-m-d', $current);
    $current = strtotime('+1 day', $current);
}

$where = "WHERE scode NOT IN ('HO', 'HEADOFFICE', 'HEAD OFFICE') AND (sname IS NULL OR (sname NOT LIKE '%Head Office%' AND sname NOT LIKE '%HO%'))";
$params = [];
$types = "";

if ($store_filter !== '') {
    $where .= " AND scode = ?";
    $params[] = $store_filter;
    $types .= "s";
}

if ($search !== '') {
    $where .= " AND (scode LIKE ? OR sname LIKE ?)";
    $lk = "%$search%";
    $params[] = $lk; $params[] = $lk;
    $types .= "ss";
}

$store_sql = "SELECT scode, sname FROM storecode $where ORDER BY scode ASC";
$stmt = $db->prepare($store_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sales_sql = "SELECT DISTINCT store_code, DATE(created_at) as sale_date FROM sales WHERE created_at >= ? AND created_at <= ?";
$stmt = $db->prepare($sales_sql);
$start_dt = $start_date . ' 00:00:00';
$end_dt = $end_date . ' 23:59:59';
$stmt->bind_param("ss", $start_dt, $end_dt);
$stmt->execute();
$sales_res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$sales_map = [];
foreach ($sales_res as $row) {
    $sales_map[$row['store_code']][] = $row['sale_date'];
}

$missing_data = [];
foreach ($stores as $st) {
    $scode = $st['scode'];
    $submitted_dates = $sales_map[$scode] ?? [];
    foreach ($dates as $d) {
        if (!in_array($d, $submitted_dates)) {
            $missing_data[] = [
                'scode' => $scode,
                'sname' => $st['sname'] ?: 'N/A',
                'missing_date' => $d
            ];
        }
    }
}

$filename = $_GET['filename'] ?? 'non_submission_report_' . date('Y-m-d');
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename); // Sanitize filename

if ($format === 'txt') {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
    
    echo "Non-Submission Report\n";
    echo "Date Range: $start_date to $end_date\n";
    echo str_repeat("-", 50) . "\n";
    
    if (empty($missing_data)) {
        echo "No stores missing submissions.\n";
    } else {
        foreach ($missing_data as $row) {
            echo "Store: " . $row['scode'] . " - " . $row['sname'] . " | Missing Date: " . $row['missing_date'] . "\n";
        }
    }
    exit;
} elseif ($format === 'xls') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th>Store Code</th><th>Store Name</th><th>Missing Date</th></tr>";
    foreach ($missing_data as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['scode']) . "</td>";
        echo "<td>" . htmlspecialchars($row['sname']) . "</td>";
        echo "<td>" . htmlspecialchars($row['missing_date']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

// Format = CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Store Code', 'Store Name', 'Missing Date']);

foreach ($missing_data as $row) {
    fputcsv($output, [
        $row['scode'],
        $row['sname'],
        $row['missing_date']
    ]);
}
fclose($output);
exit;
