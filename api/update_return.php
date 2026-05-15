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

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    exit;
}

$is_exchange = ($ex_item !== '' || $ex_amount > 0 || $ex_name !== '' || $ex_qty > 0) ? 1 : 0;

$stmt = $db->prepare("UPDATE returns SET return_item = ?, quantity = ?, return_amount = ?, reason = ?, is_exchange = ?, exchange_name = ?, exchange_item = ?, exchange_quantity = ?, exchange_amount = ? WHERE id = ?");
$stmt->bind_param("sidsissidi", $return_item, $qty, $return_amount, $reason, $is_exchange, $ex_name, $ex_item, $ex_qty, $ex_amount, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
}
$stmt->close();
?>
