<?php
session_start();
header('Content-Type: application/json');

$is_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['role'] ?? 'user') === 'admin_view' || ($_SESSION['user'] ?? '') === 'admin');
if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin access required.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    exit;
}

// Fetch original record for logging BEFORE deleting
$old_res = $db->query("SELECT store_code, item_no, quantity FROM sales WHERE id = " . intval($id));
$old_row = $old_res ? $old_res->fetch_assoc() : null;
$store_code = $old_row ? $old_row['store_code'] : '';
$qty = $old_row ? $old_row['quantity'] : 0;
$item_no = $old_row ? $old_row['item_no'] : '';

$stmt = $db->prepare("DELETE FROM sales WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    log_activity(
        $db, 
        $_SESSION['user'], 
        'delete', 
        'Sale', 
        $store_code, 
        $item_no, 
        $qty, 
        "Deleted Sale #$id: Item #$item_no, quantity $qty"
    );
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
}
$stmt->close();
?>
