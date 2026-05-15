<?php
session_start();
header('Content-Type: application/json');

$is_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    exit;
}

// Optional: Delete image file from server
$stmt_get = $db->prepare("SELECT image_path FROM pullouts WHERE id = ?");
$stmt_get->bind_param("i", $id);
$stmt_get->execute();
$img = $stmt_get->get_result()->fetch_assoc();
if ($img && $img['image_path']) {
    $full_path = '../' . $img['image_path'];
    if (file_exists($full_path)) {
        unlink($full_path);
    }
}
$stmt_get->close();

$stmt = $db->prepare("DELETE FROM pullouts WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete record.']);
}

$stmt->close();
?>
