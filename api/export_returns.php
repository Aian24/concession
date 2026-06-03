<?php
session_start();
if (!isset($_SESSION['user'])) exit;

require_once '../includes/db.php';
$db = db_connect();

$role            = $_SESSION['role'] ?? 'user';
$is_full_admin   = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
$is_admin_view   = ($role === 'admin_view');
$is_store_admin  = ($role === 'store_admin');
$is_multi_store_admin = ($role === 'multi_store_admin');
$is_admin        = ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);

$search       = $_GET['search']       ?? '';
$type         = $_GET['type']         ?? 'csv';
$start_date   = $_GET['start_date']   ?? '';
$end_date     = $_GET['end_date']     ?? '';
if (!$end_date) $end_date = $_GET['endDate'] ?? '';

$ids_str      = $_GET['ids']          ?? '';
$store_filter = $_GET['store_filter'] ?? '';
$my_store     = $_SESSION['store_code'] ?? '';

$sql = "SELECT s.*, sc.sname 
        FROM returns s
        LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci
        WHERE 1=1";
$params = [];
$types  = "";

// Role-based filtering
if ($is_store_admin || (!$is_admin && !$is_multi_store_admin)) {
    $sql .= " AND s.store_code = ?";
    $params[] = $my_store;
    $types .= "s";
} elseif ($is_multi_store_admin) {
    $assigned = $_SESSION['assigned_stores'] ?? [];
    if ($store_filter !== '' && in_array($store_filter, $assigned)) {
        $sql .= " AND s.store_code = ?";
        $params[] = $store_filter;
        $types .= "s";
    } else {
        $sql .= build_multi_store_clause('s.store_code', $assigned);
    }
} elseif ($is_admin && $store_filter !== '') {
    $sql .= " AND s.store_code = ?";
    $params[] = $store_filter;
    $types .= "s";
}

if ($ids_str !== '') {
    $ids = explode(',', $ids_str);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql .= " AND s.id IN ($placeholders)";
    foreach($ids as $id) { $params[] = intval($id); $types .= "i"; }
} else {
    if ($search !== '') {
        $sql .= " AND (s.return_item LIKE ? OR s.exchange_item LIKE ? OR s.username LIKE ? OR s.id LIKE ?)";
        $lk = "%$search%";
        $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk; $types .= "ssss";
    }
    if ($start_date !== '') {
        $sql .= " AND s.created_at >= ?"; $params[] = $start_date . ' 00:00:00'; $types .= "s";
    }
    if ($end_date !== '') {
        $sql .= " AND s.created_at <= ?"; $params[] = $end_date . ' 23:59:59'; $types .= "s";
    }
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Setup file headers
$filename = "returns_export_" . date('Ymd_His');
$headers  = ['Date', 'Store', 'Returned Item', 'Return Amt', 'Reason', 'Exchange Name', 'Exchange Item #', 'Exchange Amt', 'Username'];

// Mark records as exported
if ($result->num_rows > 0) {
    $update_ids = [];
    $data_rows = [];
    while ($r = $result->fetch_assoc()) {
        $update_ids[] = $r['id'];
        $data_rows[] = $r;
    }
    
    if (!empty($update_ids) && $type === 'txt') {
        $id_list = implode(',', $update_ids);
        $db->query("UPDATE returns SET is_exported = 1 WHERE id IN ($id_list)");
    }
} else {
    $data_rows = [];
}

if ($type === 'xls') {
    require_once '../includes/SimpleXLSXGen.php';
    
    $excel_data = [];
    $excel_data[] = $headers;
    
    foreach ($data_rows as $row) {
        $excel_data[] = [
            date('M d, Y', strtotime($row['created_at'])),
            $row['sname'] ? "{$row['sname']} ({$row['store_code']})" : $row['store_code'],
            $row['return_item'] ?: 'Exchange Only',
            $row['return_amount'] ? (float)$row['return_amount'] : 0.00,
            $row['reason'] ?: '—',
            $row['exchange_name'] ?: ($row['is_exchange'] ? 'Replacement' : '—'),
            $row['exchange_item'] ?: '—',
            $row['exchange_amount'] ? (float)$row['exchange_amount'] : 0.00,
            $row['username']
        ];
    }
    
    $xlsx = Shuchkin\SimpleXLSXGen::fromArray($excel_data);
    $xlsx->downloadAs("{$filename}.xlsx");
    exit;
}

if ($type === 'txt') {
    header("Content-Type: text/plain; charset=UTF-8");
    header("Content-Disposition: attachment; filename={$filename}.txt");
} else {
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename={$filename}.csv");
}

header("Cache-Control: max-age=0");
echo "\xEF\xBB\xBF"; // UTF-8 BOM for CSV/TXT

$output = fopen('php://output', 'w');
$sep = ($type === 'csv') ? "," : "\t";

if ($type === 'csv') {
    fputcsv($output, $headers);
} elseif ($type !== 'txt') {
    fwrite($output, implode($sep, $headers) . "\n");
}

foreach ($data_rows as $row) {
    if ($type === 'txt') {
        $line = [
            $row['return_item'],
            number_format($row['return_amount'], 2, '.', ''),
            $row['quantity']
        ];
        fwrite($output, implode(',', $line) . "\n");
    } else {
        $line = [
            date('M d, Y', strtotime($row['created_at'])),
            $row['sname'] ? "{$row['sname']} ({$row['store_code']})" : $row['store_code'],
            $row['return_item'] ?: 'Exchange Only',
            $row['return_amount'] ? number_format($row['return_amount'], 2) : '0.00',
            $row['reason'] ?: '—',
            $row['exchange_name'] ?: ($row['is_exchange'] ? 'Replacement' : '—'),
            $row['exchange_item'] ?: '—',
            $row['exchange_amount'] ? number_format($row['exchange_amount'], 2) : '0.00',
            $row['username']
        ];
        if ($type === 'csv') {
            fputcsv($output, $line);
        } else {
            fwrite($output, implode($sep, $line) . "\n");
        }
    }
}

fclose($output);
exit;
