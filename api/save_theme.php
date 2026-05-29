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
$db->query("CREATE TABLE IF NOT EXISTS user_themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    theme_data JSON NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['theme'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$theme_json = json_encode($input['theme']);
$username = $_SESSION['user'];

// Get user ID
$stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$user_id = $user['id'];

// Upsert theme
$stmt = $db->prepare("INSERT INTO user_themes (user_id, theme_data) VALUES (?, ?) ON DUPLICATE KEY UPDATE theme_data = VALUES(theme_data)");
$stmt->bind_param("is", $user_id, $theme_json);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $ok, 'message' => $ok ? 'Theme saved!' : 'Failed to save theme']);
