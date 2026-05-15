<?php
session_start();
header('Content-Type: application/json');

$is_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
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

$stmt = $db->prepare("DELETE FROM sales WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
}
$stmt->close();
?>
