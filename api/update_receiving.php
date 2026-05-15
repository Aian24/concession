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

if (!$id || $os_no === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid data. OS # is required.']);
    exit;
}

$stmt = $db->prepare("UPDATE receiving SET os_no = ?, from_store = ?, to_store = ?, quantity = ? WHERE id = ?");
$stmt->bind_param("sssii", $os_no, $from_store, $to_store, $quantity, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
}
$stmt->close();
?>
