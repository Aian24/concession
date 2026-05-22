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
        FROM receiving s
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
        $sql .= " AND (s.item_no LIKE ? OR s.os_no LIKE ? OR s.from_store LIKE ? OR s.username LIKE ? OR s.id LIKE ?)";
        $lk = "%$search%";
        $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk; $params[] = $lk; $types .= "sssss";
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
$filename = "receiving_export_" . date('Ymd_His');
$headers  = ['Date', 'Time', 'Store', 'OS #', 'From (Store)', 'To (Store)', 'Qty', 'Username'];

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
        $db->query("UPDATE receiving SET is_exported = 1 WHERE id IN ($id_list)");
    }
} else {
    $data_rows = [];
}

if ($type === 'xls') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename={$filename}.xls");
    header("Cache-Control: max-age=0");
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-type" content="text/html;charset=utf-8" /></head>';
    echo '<body>';
    echo '<table border="0">';
    echo '<tr style="background-color: #f2f2f2; font-weight: bold;">';
    foreach ($headers as $h) echo "<td>$h</td>";
    echo '</tr>';

    foreach ($data_rows as $row) {
        $line = [
            date('M d, Y', strtotime($row['created_at'])),
            date('h:i A', strtotime($row['created_at'])),
            $row['sname'] ? "{$row['sname']} ({$row['store_code']})" : $row['store_code'],
            $row['os_no'],
            $row['from_store'],
            $row['to_store'],
            $row['quantity'],
            $row['username']
        ];
        echo '<tr>';
        foreach ($line as $val) echo "<td>$val</td>";
        echo '</tr>';
    }
    echo '</table>';
    echo '</body></html>';
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
            $row['os_no'],
            $row['from_store'],
            $row['to_store'],
            $row['quantity']
        ];
        fwrite($output, implode(',', $line) . "\n");
    } else {
        $line = [
            date('M d, Y', strtotime($row['created_at'])),
            date('h:i A', strtotime($row['created_at'])),
            $row['sname'] ? "{$row['sname']} ({$row['store_code']})" : $row['store_code'],
            $row['os_no'],
            $row['from_store'],
            $row['to_store'],
            $row['quantity'],
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
