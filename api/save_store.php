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

$data = json_decode(file_get_contents('php://input'), true);
$scode = trim($data['scode'] ?? '');
$sname = trim($data['sname'] ?? '');
$old_scode = trim($data['old_scode'] ?? '');

if ($scode === '' || $sname === '') {
    echo json_encode(['success' => false, 'message' => 'Both store code and name are required.']);
    exit;
}

if ($old_scode !== '') {
    // Update existing store
    $stmt = $db->prepare("UPDATE storecode SET scode = ?, sname = ? WHERE scode = ?");
    $stmt->bind_param("sss", $scode, $sname, $old_scode);
} else {
    // Check if scode already exists
    $check = $db->prepare("SELECT scode FROM storecode WHERE scode = ?");
    $check->bind_param("s", $scode);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Store code already exists.']);
        exit;
    }
    $check->close();

    // Insert new store
    $stmt = $db->prepare("INSERT INTO storecode (scode, sname) VALUES (?, ?)");
    $stmt->bind_param("ss", $scode, $sname);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Store saved successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
}
$stmt->close();
?>
