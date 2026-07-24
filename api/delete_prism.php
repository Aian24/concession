<?php
session_start();
header('Content-Type: application/json');

$can_delete = ($_SESSION['can_delete'] ?? false) || (($_SESSION['role'] ?? '') === 'admin') || (($_SESSION['user'] ?? '') === 'admin');
if (!$can_delete) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Full Admin access required.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID is required.']);
    exit;
}

$stmt = $db->prepare("DELETE FROM prismdata WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Prism Data deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$db->close();
