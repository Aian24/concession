<?php
session_start();
header('Content-Type: application/json');

$is_full_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
if (!$is_full_admin) {
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
