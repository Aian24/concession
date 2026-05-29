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

$res = $db->query("TRUNCATE TABLE boutique");

if ($res) {
    echo json_encode(['success' => true, 'message' => 'All boutique data cleared']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to clear data']);
}
