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

$id         = intval($data['id'] ?? 0);
$os_no      = trim($data['os_no'] ?? '');
$from_store = trim($data['from_store'] ?? '');
$to_store   = trim($data['to_store'] ?? '');
$quantity   = intval($data['quantity'] ?? 0);

$created_at = $data['created_at'] ?? '';

if (!$id || $os_no === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid data. OS # is required.']);
    exit;
}

if (!empty($created_at)) {
    $stmt = $db->prepare("UPDATE receiving SET os_no = ?, from_store = ?, to_store = ?, quantity = ?, created_at = ? WHERE id = ?");
    $stmt->bind_param("sssisi", $os_no, $from_store, $to_store, $quantity, $created_at, $id);
} else {
    $stmt = $db->prepare("UPDATE receiving SET os_no = ?, from_store = ?, to_store = ?, quantity = ? WHERE id = ?");
    $stmt->bind_param("sssii", $os_no, $from_store, $to_store, $quantity, $id);
}

// Fetch original record for logging
$old_res = $db->query("SELECT store_code, os_no, quantity FROM receiving WHERE id = " . intval($id));
$old_row = $old_res ? $old_res->fetch_assoc() : null;
$store_code = $old_row ? $old_row['store_code'] : '';
$old_qty = $old_row ? $old_row['quantity'] : 0;
$old_os = $old_row ? $old_row['os_no'] : '';

if ($stmt->execute()) {
    log_activity(
        $db, 
        $_SESSION['user'], 
        'edit', 
        'Receiving', 
        $store_code, 
        $os_no, 
        $quantity, 
        "Edited Receiving #$id: Changed OS # from '$old_os' to '$os_no', qty from $old_qty to $quantity"
    );
    echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
}
$stmt->close();
?>
