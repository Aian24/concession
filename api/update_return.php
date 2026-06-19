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

$id            = intval($data['id'] ?? 0);
$return_item   = trim($data['return_item'] ?? '');
$qty           = intval($data['quantity'] ?? 0);
// Ensure return amount is negative
$return_amount = -abs(floatval($data['return_amount'] ?? 0));
$reason        = trim($data['reason'] ?? '');
$ex_item       = trim($data['exchange_item'] ?? '');
$ex_qty        = intval($data['exchange_quantity'] ?? 0);
$ex_amount     = floatval($data['exchange_amount'] ?? 0);
$ex_name       = trim($data['exchange_name'] ?? '');

$created_at = $data['created_at'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    exit;
}

// Fetch original record for logging and preserving time
$old_res = $db->query("SELECT store_code, return_item, exchange_item, quantity, created_at FROM returns WHERE id = " . intval($id));
$old_row = $old_res ? $old_res->fetch_assoc() : null;
$store_code = $old_row ? $old_row['store_code'] : '';
$old_qty = $old_row ? $old_row['quantity'] : 0;
$old_item = $old_row ? ($old_row['return_item'] ?: $old_row['exchange_item']) : '';
$old_time = $old_row ? date('H:i:s', strtotime($old_row['created_at'])) : '00:00:00';

$is_exchange = ($ex_item !== '' || $ex_amount > 0 || $ex_name !== '' || $ex_qty > 0) ? 1 : 0;

if (!empty($created_at)) {
    if (strlen($created_at) === 10) {
        $created_at .= ' ' . $old_time;
    }
    $stmt = $db->prepare("UPDATE returns SET return_item = ?, quantity = ?, return_amount = ?, reason = ?, is_exchange = ?, exchange_name = ?, exchange_item = ?, exchange_quantity = ?, exchange_amount = ?, created_at = ? WHERE id = ?");
    $stmt->bind_param("sidsissidsi", $return_item, $qty, $return_amount, $reason, $is_exchange, $ex_name, $ex_item, $ex_qty, $ex_amount, $created_at, $id);
} else {
    $stmt = $db->prepare("UPDATE returns SET return_item = ?, quantity = ?, return_amount = ?, reason = ?, is_exchange = ?, exchange_name = ?, exchange_item = ?, exchange_quantity = ?, exchange_amount = ? WHERE id = ?");
    $stmt->bind_param("sidsissidi", $return_item, $qty, $return_amount, $reason, $is_exchange, $ex_name, $ex_item, $ex_qty, $ex_amount, $id);
}

if ($stmt->execute()) {
    $ref_item = ($return_item !== '') ? $return_item : ($ex_item !== '' ? $ex_item : 'Return Item');
    log_activity(
        $db, 
        $_SESSION['user'], 
        'edit', 
        'Return', 
        $store_code, 
        $ref_item, 
        $qty, 
        "Edited Return #$id: Changed item from '$old_item' to '$ref_item', qty from $old_qty to $qty"
    );
    echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
}
$stmt->close();
?>
