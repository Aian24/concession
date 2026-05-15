<?php
session_start();
header('Content-Type: application/json');

$role      = $_SESSION['role'] ?? 'user';
$can_edit  = ($role === 'admin' || ($_SESSION['user'] ?? '') === 'admin' || $role === 'admin_view');
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

if (!empty($created_at)) {
    $stmt = $db->prepare("UPDATE pullouts SET item_no = ?, quantity = ?, created_at = ? WHERE id = ?");
    $stmt->bind_param("sisi", $item_no, $quantity, $created_at, $id);
} else {
    $stmt = $db->prepare("UPDATE pullouts SET item_no = ?, quantity = ? WHERE id = ?");
    $stmt->bind_param("sii", $item_no, $quantity, $id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
}

$stmt->close();
?>
