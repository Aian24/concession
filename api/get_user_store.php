<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

$username = $_GET['username'] ?? '';

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

$db = db_connect();

$stmt = $db->prepare("
    SELECT u.id, u.store_code, u.role, sc.sname 
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
    $user_id = $result['id'];

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

    $assigned_stores = [];
    
    // For roles that might have multiple stores assigned, fetch them
    if ($role === 'multi_store_admin' || $role === 'user' || $role === 'store_admin') {
        $assigned_stores = get_user_assigned_stores($db, $user_id);
    }
    
    // If no specific multi-store assignments, build a default one from primary store_code
    if (empty($assigned_stores) && $store_code !== 'MULTI' && $store_code !== 'ALL' && !empty($store_code)) {
        $assigned_stores[] = [
            'store_code' => $store_code,
            'sname' => $sname ?: 'Unknown Store'
        ];
    }

    echo json_encode([
        'success' => true, 
        'role' => $role,
        'store_code' => $store_code,
        'sname' => $sname ?: 'Unknown Store',
        'assigned_stores' => $assigned_stores
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
