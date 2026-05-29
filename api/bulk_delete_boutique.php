<?php
session_start();
header('Content-Type: application/json');

$is_full_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
if (!$is_full_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No records selected']);
    exit;
}

$idList = implode(',', array_map('intval', $ids));
$res = $db->query("DELETE FROM boutique WHERE id IN ($idList)");

if ($res) {
    echo json_encode(['success' => true, 'message' => count($ids) . ' records deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete records']);
}
