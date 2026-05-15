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

if ($db->query("TRUNCATE TABLE prismdata")) {
    echo json_encode(['success' => true, 'message' => 'All Prism Data has been cleared.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
}

$db->close();
