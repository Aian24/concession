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

$scode = $_GET['scode'] ?? '';

if ($scode === '') {
    echo json_encode(['success' => false, 'message' => 'Store code is required.']);
    exit;
}

$stmt = $db->prepare("DELETE FROM storecode WHERE scode = ?");
$stmt->bind_param("s", $scode);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Store deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
}
$stmt->close();
?>
