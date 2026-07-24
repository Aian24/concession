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
