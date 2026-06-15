<?php
session_start();
if (!isset($_SESSION['user'])) exit;

require_once '../includes/db.php';
$db = db_connect();

$type         = $_GET['type'] ?? 'csv';
$search       = $_GET['search'] ?? '';
$start_date   = $_GET['start_date'] ?? '';
$end_date     = $_GET['end_date']   ?? '';
$store_filter = $_GET['store_filter'] ?? '';
$ids          = $_GET['ids'] ?? '';

$role            = $_SESSION['role'] ?? 'user';
$is_full_admin   = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
$is_admin_view   = ($role === 'admin_view');
$is_store_admin  = ($role === 'store_admin');
$is_multi_store_admin = ($role === 'multi_store_admin');
$is_admin        = ($is_full_admin || $is_admin_view || $is_store_admin || $is_multi_store_admin);

$store_code = $_SESSION['store_code'];

$where = "WHERE 1=1";
if ($is_store_admin || (!$is_admin && !$is_multi_store_admin)) {
    $where .= " AND s.store_code = '$store_code'";
} elseif ($is_multi_store_admin) {
    $assigned = $_SESSION['assigned_stores'] ?? [];
    if ($store_filter !== '' && in_array($store_filter, $assigned)) {
        $store_filter_escaped = $db->real_escape_string($store_filter);
        $where .= " AND s.store_code = '$store_filter_escaped'";
    } else {
        $where .= build_multi_store_clause('s.store_code', $assigned);
    }
} elseif ($is_admin && $store_filter !== '') {
    $store_filter_escaped = $db->real_escape_string($store_filter);
    $where .= " AND s.store_code = '$store_filter_escaped'";
}

if ($ids !== '') {
    $id_list = implode(',', array_map('intval', explode(',', $ids)));
    $where .= " AND s.id IN ($id_list)";
} else {
    if ($search !== '') {
        $lk = $db->real_escape_string($search);
        $where .= " AND (s.item_no LIKE '%$lk%' OR s.username LIKE '%$lk%' OR s.id LIKE '%$lk%')";
    }
    if ($start_date !== '') {
        $where .= " AND s.created_at >= '$start_date 00:00:00'";
    }
    if ($end_date !== '') {
        $where .= " AND s.created_at <= '$end_date 23:59:59'";
    }
}

$query = "SELECT s.*, sc.sname FROM pullouts s LEFT JOIN storecode sc ON s.store_code = sc.scode COLLATE utf8mb4_unicode_ci $where ORDER BY s.created_at DESC";
$result = $db->query($query);

// Mark records as exported and collect data
$data_rows = [];
$update_ids = [];
if ($result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        $data_rows[] = $r;
        $update_ids[] = $r['id'];
    }
    if (!empty($update_ids) && $type === 'txt') {
        $id_list = implode(',', $update_ids);
        $db->query("UPDATE pullouts SET is_exported = 1 WHERE id IN ($id_list)");
    }
}

$filename = "pullouts_export_" . date('Y-m-d_His');

if ($type === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Store', 'ITR # / OS #', 'Qty', 'User', 'Image Proof']);
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI'])) . "/";

    foreach ($data_rows as $row) {
        $img_link = $row['image_path'] ? $base_url . $row['image_path'] : 'No Image';
        fputcsv($output, [
            date('M d, Y', strtotime($row['created_at'])),
            $row['sname'] ? "{$row['sname']} ({$row['store_code']})" : $row['store_code'], 
            $row['item_no'], 
            $row['quantity'], 
            $row['username'],
            $img_link
        ]);
    }
    fclose($output);
} elseif ($type === 'xls') {
    require_once '../includes/SimpleXLSXGen.php';
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['REQUEST_URI'])) . "/";

    $excel_data = [];
    $excel_data[] = ['Date', 'Store', 'ITR # / OS #', 'Qty', 'User', 'Image Proof'];
    
    foreach ($data_rows as $row) {
        $img_link = $row['image_path'] ? $base_url . $row['image_path'] : '';
        
        $excel_data[] = [
            date('M d, Y', strtotime($row['created_at'])),
            $row['sname'] ? "{$row['sname']} ({$row['store_code']})" : $row['store_code'],
            $row['item_no'],
            (int)$row['quantity'],
            $row['username'],
            $img_link ? '<f>IMAGE("'.$img_link.'")</f>' : 'No Image'
        ];
    }
    
    $xlsx = Shuchkin\SimpleXLSXGen::fromArray($excel_data);
    $xlsx->downloadAs("{$filename}.xlsx");
    exit;
} else {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
    
    $output = fopen('php://output', 'w');
    foreach ($data_rows as $row) {
        $line = [
            $row['item_no'],
            $row['quantity']
        ];
        fwrite($output, implode(',', $line) . "\n");
    }
    fclose($output);
}
exit;
?>
