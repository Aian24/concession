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
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$id = isset($data['id']) && $data['id'] !== '' ? intval($data['id']) : null;
$date = $data['date'] ?? '';
$store_name = $data['store_name'] ?? '';
$store_code = $data['store_code'] ?? '';
$qty_sold = intval($data['qty_sold'] ?? 0);
$amount = floatval($data['amount'] ?? 0);

if (empty($date) || empty($store_code)) {
    echo json_encode(['success' => false, 'message' => 'Date and Store Code are required']);
    exit;
}

if ($id) {
    $stmt = $db->prepare("UPDATE boutique SET date=?, store_name=?, store_code=?, qty_sold=?, amount=? WHERE id=?");
    $stmt->bind_param("sssidi", $date, $store_name, $store_code, $qty_sold, $amount, $id);
} else {
    $stmt = $db->prepare("INSERT INTO boutique (date, store_name, store_code, qty_sold, amount) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssid", $date, $store_name, $store_code, $qty_sold, $amount);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Boutique data saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}
$stmt->close();
