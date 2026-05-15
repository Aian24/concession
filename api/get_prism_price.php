<?php
session_start();
header('Content-Type: application/json');

require_once '../includes/db.php';
$db = db_connect();

$item_no = $_GET['item_no'] ?? '';

if ($item_no === '') {
    echo json_encode(['success' => false, 'message' => 'Item Number is required.']);
    exit;
}

$stmt = $db->prepare("SELECT srp FROM prismdata WHERE item_no = ? LIMIT 1");
$stmt->bind_param("s", $item_no);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode(['success' => true, 'srp' => $row['srp']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Item not found in Prism Data.']);
}

$stmt->close();
$db->close();
