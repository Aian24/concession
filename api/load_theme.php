<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

// Ensure user_themes table exists
$check = $db->query("SHOW TABLES LIKE 'user_themes'");
if ($check && $check->num_rows === 0) {
    echo json_encode(['success' => true, 'theme' => null]);
    exit;
}

$username = $_SESSION['user'];

$stmt = $db->prepare("SELECT ut.theme_data FROM user_themes ut JOIN users u ON ut.user_id = u.id WHERE u.username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($result) {
    echo json_encode(['success' => true, 'theme' => json_decode($result['theme_data'], true)]);
} else {
    echo json_encode(['success' => true, 'theme' => null]);
}
