<?php
session_start();
header('Content-Type: application/json');

$role      = $_SESSION['role'] ?? 'user';
$can_edit  = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin' || $role === 'admin_view');
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

$created_at = $data['created_at'] ?? '';

if (!$id || $item_no === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit;
}

$line_total = $amount * $qty;

if (!empty($created_at)) {
    // If we only have the date, we might want to preserve the time if it's already there, 
    // but usually, a simple date change sets it to 00:00:00 or current time.
    // For simplicity, we'll just set it to the provided date.
    $stmt = $db->prepare("UPDATE sales SET item_no = ?, amount_sold = ?, quantity = ?, line_total = ?, created_at = ? WHERE id = ?");
    $stmt->bind_param("sdidsi", $item_no, $amount, $qty, $line_total, $created_at, $id);
} else {
    $stmt = $db->prepare("UPDATE sales SET item_no = ?, amount_sold = ?, quantity = ?, line_total = ? WHERE id = ?");
    $stmt->bind_param("sdidi", $item_no, $amount, $qty, $line_total, $id);
}

// Fetch original record for logging
$old_res = $db->query("SELECT store_code, item_no, quantity FROM sales WHERE id = " . intval($id));
$old_row = $old_res ? $old_res->fetch_assoc() : null;
$store_code = $old_row ? $old_row['store_code'] : '';
$old_qty = $old_row ? $old_row['quantity'] : 0;
$old_item = $old_row ? $old_row['item_no'] : '';

if ($stmt->execute()) {
    log_activity(
        $db, 
        $_SESSION['user'], 
        'edit', 
        'Sale', 
        $store_code, 
        $item_no, 
        $qty, 
        "Edited Sale #$id: Changed item from '$old_item' to '$item_no', qty from $old_qty to $qty"
    );
    echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
}
$stmt->close();
?>
