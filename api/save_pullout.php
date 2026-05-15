<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

// Auto-Migration: Ensure table and columns exist
$db->query("CREATE TABLE IF NOT EXISTS pullouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    store_code VARCHAR(50) NOT NULL,
    item_no VARCHAR(150) NOT NULL,
    quantity INT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_store (store_code),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure image_path column exists
$check = $db->query("SHOW COLUMNS FROM pullouts LIKE 'image_path'");
if ($check && $check->num_rows === 0) {
    $db->query("ALTER TABLE pullouts ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER quantity");
}

$username   = $_SESSION['user'];
$store_code = $_SESSION['store_code'];

$entries = $_POST['entries'] ?? [];
$success_count = 0;

if (empty($entries)) {
    echo json_encode(['success' => false, 'message' => 'No valid entries provided.']);
    exit;
}

$stmt = $db->prepare("INSERT INTO pullouts (username, store_code, item_no, quantity, image_path, created_at) VALUES (?, ?, ?, ?, ?, ?)");

foreach ($entries as $index => $row) {
    $item_no  = trim($row['item_no'] ?? '');
    $quantity = intval($row['quantity'] ?? 0);

    if ($item_no === '' || $quantity <= 0) continue;

    $image_path = null;
    
    // Check for uploaded file for this entry
    if (isset($_FILES['entries']['name'][$index]['image']) && $_FILES['entries']['error'][$index]['image'] === UPLOAD_ERR_OK) {
        $upload_dir = '../images/pullouts/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = $_FILES['entries']['name'][$index]['image'];
        $file_tmp  = $_FILES['entries']['tmp_name'][$index]['image'];
        $file_ext  = pathinfo($file_name, PATHINFO_EXTENSION);
        $filename  = time() . '_' . uniqid() . '.' . $file_ext;
        $target_file = $upload_dir . $filename;

        if (move_uploaded_file($file_tmp, $target_file)) {
            $image_path = 'images/pullouts/' . $filename;
        }
    }

    $req_date = $_POST['transaction_date'] ?? date('Y-m-d');
    $created_at = $req_date . ' ' . date('H:i:s');
    $stmt->bind_param("sssiss", $username, $store_code, $item_no, $quantity, $image_path, $created_at);
    
    if ($stmt->execute()) {
        $success_count++;
    }
}

$stmt->close();

if ($success_count > 0) {
    echo json_encode(['success' => true, 'message' => "Successfully recorded $success_count pullout(s)."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record pullouts. Please check your data.']);
}
?>
