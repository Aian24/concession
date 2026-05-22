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
    SELECT u.store_code, u.role, sc.sname 
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
    $role = $result['role'];
    $store_code = $result['store_code'];
    $sname = $result['sname'];

    if (empty($store_code)) {
        if ($role === 'multi_store_admin') {
            $store_code = 'MULTI';
            $sname = 'Multiple Stores';
        } elseif ($role === 'admin' || $role === 'admin_view') {
            $store_code = 'ALL';
            $sname = 'All Stores';
        } else {
            $sname = 'Unknown Store';
        }
    }

    echo json_encode([
        'success' => true, 
        'store_code' => $store_code,
        'sname' => $sname ?: 'Unknown Store'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
