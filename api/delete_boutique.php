<?php
session_start();
header('Content-Type: application/json');

$can_delete = ($_SESSION['can_delete'] ?? false) || (($_SESSION['role'] ?? '') === 'admin') || (($_SESSION['user'] ?? '') === 'admin');
if (!$can_delete) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

$stmt = $db->prepare("DELETE FROM boutique WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete record']);
}
$stmt->close();
