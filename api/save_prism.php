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
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$id      = $data['id'] ?? null;
$item_no = trim($data['item_no'] ?? '');
$srp     = floatval($data['srp'] ?? 0);

if ($item_no === '') {
    echo json_encode(['success' => false, 'message' => 'Item Number is required.']);
    exit;
}

if ($id) {
    // Update
    $stmt = $db->prepare("UPDATE prismdata SET item_no = ?, srp = ? WHERE id = ?");
    $stmt->bind_param("sdi", $item_no, $srp, $id);
} else {
    // Insert
    $stmt = $db->prepare("INSERT INTO prismdata (item_no, srp) VALUES (?, ?)");
    $stmt->bind_param("sd", $item_no, $srp);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Prism Data saved successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$db->close();
