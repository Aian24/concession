<?php
session_start();
header('Content-Type: application/json');

$role      = $_SESSION['role'] ?? 'user';
$can_edit  = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin' || $role === 'admin_view' || $role === 'multi_store_admin');
if (!$can_edit) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$data = json_decode(file_get_contents('php://input'), true);

$id      = $data['id'] ?? 0;
$item_no = trim($data['item_no'] ?? '');
$amount  = floatval($data['amount_sold'] ?? 0);
$qty     = intval($data['quantity'] ?? 0);
$store_code_new = trim($data['store_code'] ?? '');

$created_at = $data['created_at'] ?? '';

if (!$id || $item_no === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit;
}

// Fetch original record for logging and preserving time
$old_res = $db->query("SELECT store_code, item_no, quantity, created_at FROM sales WHERE id = " . intval($id));
$old_row = $old_res ? $old_res->fetch_assoc() : null;
$store_code = $old_row ? $old_row['store_code'] : '';
$old_qty = $old_row ? $old_row['quantity'] : 0;
$old_item = $old_row ? $old_row['item_no'] : '';
$old_time = $old_row ? date('H:i:s', strtotime($old_row['created_at'])) : '00:00:00';

$line_total = $amount * $qty;

if (!empty($created_at)) {
    // If only a date (YYYY-MM-DD) was provided, append the original time
    if (strlen($created_at) === 10) {
        $created_at .= ' ' . $old_time;
    }
    
    if ($store_code_new !== '') {
        $stmt = $db->prepare("UPDATE sales SET item_no = ?, amount_sold = ?, quantity = ?, line_total = ?, created_at = ?, store_code = ? WHERE id = ?");
        $stmt->bind_param("sdidssi", $item_no, $amount, $qty, $line_total, $created_at, $store_code_new, $id);
    } else {
        $stmt = $db->prepare("UPDATE sales SET item_no = ?, amount_sold = ?, quantity = ?, line_total = ?, created_at = ? WHERE id = ?");
        $stmt->bind_param("sdidsi", $item_no, $amount, $qty, $line_total, $created_at, $id);
    }
} else {
    if ($store_code_new !== '') {
        $stmt = $db->prepare("UPDATE sales SET item_no = ?, amount_sold = ?, quantity = ?, line_total = ?, store_code = ? WHERE id = ?");
        $stmt->bind_param("sdidsi", $item_no, $amount, $qty, $line_total, $store_code_new, $id);
    } else {
        $stmt = $db->prepare("UPDATE sales SET item_no = ?, amount_sold = ?, quantity = ?, line_total = ? WHERE id = ?");
        $stmt->bind_param("sdidi", $item_no, $amount, $qty, $line_total, $id);
    }
}

$final_store = ($store_code_new !== '' && $store_code_new !== $store_code) ? $store_code_new : $store_code;

if ($stmt->execute()) {
    log_activity(
        $db, 
        $_SESSION['user'], 
        'edit', 
        'Sale', 
        $final_store, 
        $item_no, 
        $qty, 
        "Edited Sale #$id: Changed item from '$old_item' to '$item_no', qty from $old_qty to $qty" . ($store_code_new !== '' && $store_code_new !== $store_code ? ", store from '$store_code' to '$store_code_new'" : "")
    );
    echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
}
$stmt->close();
?>
