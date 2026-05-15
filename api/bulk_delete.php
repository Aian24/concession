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
$table = $data['table'] ?? '';
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No items selected.']);
    exit;
}

$allowed_tables = ['users', 'sales', 'receiving', 'returns', 'pullouts'];
if (!in_array($table, $allowed_tables)) {
    echo json_encode(['success' => false, 'message' => 'Invalid table specified.']);
    exit;
}

// Convert IDs to integers for safety
$id_list = implode(',', array_map('intval', $ids));

// Special protection for admin user
$extra_where = "";
if ($table === 'users') {
    $extra_where = " AND username != 'admin'";
}

// Special Handling for Pullouts (Delete physical images)
if ($table === 'pullouts') {
    $img_stmt = $db->query("SELECT image_path FROM pullouts WHERE id IN ($id_list)");
    while ($img_row = $img_stmt->fetch_assoc()) {
        if (!empty($img_row['image_path'])) {
            $full_path = '../' . $img_row['image_path'];
            if (file_exists($full_path)) {
                @unlink($full_path);
            }
        }
    }
}

$query = "DELETE FROM `$table` WHERE id IN ($id_list) $extra_where";

if ($db->query($query)) {
    $affected = $db->affected_rows;
    echo json_encode(['success' => true, 'message' => "$affected records successfully removed."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
}
?>
