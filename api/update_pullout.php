<?php
session_start();
header('Content-Type: application/json');

$can_edit = ($_SESSION['can_edit'] ?? false) || (($_SESSION['role'] ?? '') === 'admin') || (($_SESSION['user'] ?? '') === 'admin');
if (!$can_edit) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$data = json_decode(file_get_contents('php://input'), true);

$id       = intval($data['id'] ?? 0);
$item_no  = trim($data['item_no'] ?? '');
$quantity = intval($data['quantity'] ?? 0);

$created_at = $data['created_at'] ?? '';

if (!$id || $item_no === '' || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit;
}

// Fetch original record for logging and preserving time
$old_res = $db->query("SELECT store_code, item_no, quantity, created_at FROM pullouts WHERE id = " . intval($id));
$old_row = $old_res ? $old_res->fetch_assoc() : null;
$store_code = $old_row ? $old_row['store_code'] : '';
$old_qty = $old_row ? $old_row['quantity'] : 0;
$old_item = $old_row ? $old_row['item_no'] : '';
$old_time = $old_row ? date('H:i:s', strtotime($old_row['created_at'])) : '00:00:00';

if (!empty($created_at)) {
    if (strlen($created_at) === 10) {
        $created_at .= ' ' . $old_time;
    }
    $stmt = $db->prepare("UPDATE pullouts SET item_no = ?, quantity = ?, created_at = ? WHERE id = ?");
    $stmt->bind_param("sisi", $item_no, $quantity, $created_at, $id);
} else {
    $stmt = $db->prepare("UPDATE pullouts SET item_no = ?, quantity = ? WHERE id = ?");
    $stmt->bind_param("sii", $item_no, $quantity, $id);
}

if ($stmt->execute()) {
    log_activity(
        $db, 
        $_SESSION['user'], 
        'edit', 
        'Pullout', 
        $store_code, 
        $item_no, 
        $quantity, 
        "Edited Pullout #$id: Changed item from '$old_item' to '$item_no', qty from $old_qty to $quantity"
    );
    echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
}

$stmt->close();
?>
