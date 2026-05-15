<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

$username = $_GET['username'] ?? '';

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

$db = db_connect();
// Join with storecode to get the sname as well
$stmt = $db->prepare("
    SELECT u.store_code, sc.sname 
    FROM users u 
    LEFT JOIN storecode sc ON u.store_code = sc.scode 
    WHERE u.username = ? 
    LIMIT 1
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result) {
    echo json_encode([
        'success' => true, 
        'store_code' => $result['store_code'],
        'sname' => $result['sname'] ?: 'Unknown Store'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
