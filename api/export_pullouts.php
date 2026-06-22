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
    fputcsv($output, ['Item#', 'qty']);

    foreach ($data_rows as $row) {
        fputcsv($output, [
            $row['item_no'], 
            $row['quantity']
        ]);
    }
    fclose($output);
} elseif ($type === 'xls') {
    require '../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->setCellValue('A1', 'Date');
    $sheet->setCellValue('B1', 'Store');
    $sheet->setCellValue('C1', 'ITR # / OS #');
    $sheet->setCellValue('D1', 'Qty');
    $sheet->setCellValue('E1', 'User');
    $sheet->setCellValue('F1', 'Image Proof');
    
    $row_num = 2;
    foreach ($data_rows as $row) {
        $sheet->setCellValue('A' . $row_num, date('M d, Y', strtotime($row['created_at'])));
        $sheet->setCellValue('B' . $row_num, $row['sname'] ? "{$row['sname']} ({$row['store_code']})" : $row['store_code']);
        $sheet->setCellValue('C' . $row_num, $row['item_no']);
        $sheet->setCellValue('D' . $row_num, (int)$row['quantity']);
        $sheet->setCellValue('E' . $row_num, $row['username']);
        
        if (!empty($row['image_path']) && file_exists('../' . $row['image_path'])) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName('Proof');
            $drawing->setDescription('Proof');
            $drawing->setPath('../' . $row['image_path']);
            $drawing->setCoordinates('F' . $row_num);
            $drawing->setHeight(80);
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension($row_num)->setRowHeight(80);
        } else {
            $sheet->setCellValue('F' . $row_num, 'No Image');
        }
        $row_num++;
    }
    
    $sheet->getColumnDimension('F')->setWidth(15);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
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
