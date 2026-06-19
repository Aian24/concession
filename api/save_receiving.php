<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$inputData = json_decode(file_get_contents('php://input'), true);
$entries   = $inputData['entries'] ?? [];
$req_date  = $inputData['transaction_date'] ?? ($_SESSION['transaction_date'] ?? date('Y-m-d'));

if (empty($entries) || !is_array($entries)) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format.']);
    exit;
}

$username   = $_SESSION['user'];
$store_code = $_SESSION['store_code'] ?? '';
$success_count = 0;

$stmt = $db->prepare("INSERT INTO receiving (username, store_code, os_no, from_store, to_store, quantity, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");

foreach ($entries as $row) {
    // Basic validation
    $os    = trim($row['os_no'] ?? '');
    $from  = trim($row['from_store'] ?? '');
    $to    = trim($row['to_store'] ?? '');
    $qty   = intval($row['quantity'] ?? 0);

    if ($os === '') continue;

    $created_at = $req_date . ' ' . date('H:i:s');
    $stmt->bind_param("sssssis", $username, $store_code, $os, $from, $to, $qty, $created_at);
    if ($stmt->execute()) {
        $success_count++;
        log_activity($db, $username, 'create', 'Receiving', $store_code, $os, $qty, "Created receiving entry for OS #$os from $from");
    }
}

$stmt->close();

if ($success_count > 0) {
    echo json_encode(['success' => true, 'message' => "Successfully recorded $success_count items."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record items. Please check your data.']);
}
?>
