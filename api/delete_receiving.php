<?php
session_start();
header('Content-Type: application/json');

$is_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['role'] ?? 'user') === 'admin_view' || ($_SESSION['role'] ?? 'user') === 'multi_store_admin' || ($_SESSION['user'] ?? '') === 'admin');
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
$old_res = $db->query("SELECT store_code, os_no, quantity FROM receiving WHERE id = " . intval($id));
$old_row = $old_res ? $old_res->fetch_assoc() : null;
$store_code = $old_row ? $old_row['store_code'] : '';
$qty = $old_row ? $old_row['quantity'] : 0;
$os_no = $old_row ? $old_row['os_no'] : '';

$stmt = $db->prepare("DELETE FROM receiving WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    log_activity(
        $db, 
        $_SESSION['user'], 
        'delete', 
        'Receiving', 
        $store_code, 
        $os_no, 
        $qty, 
        "Deleted Receiving #$id: OS #$os_no, quantity $qty"
    );
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
}
$stmt->close();
?>
