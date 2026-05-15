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

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No IDs selected for deletion.']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

$stmt = $db->prepare("DELETE FROM prismdata WHERE id IN ($placeholders)");
$stmt->bind_param($types, ...$ids);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => count($ids) . ' records deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$db->close();
