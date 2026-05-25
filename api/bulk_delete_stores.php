<?php
session_start();
header('Content-Type: application/json');

$is_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['role'] ?? 'user') === 'admin_view' || ($_SESSION['user'] ?? '') === 'admin');
if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin access required.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

$data = json_decode(file_get_contents('php://input'), true);
$scodes = $data['scodes'] ?? [];

if (empty($scodes)) {
    echo json_encode(['success' => false, 'message' => 'No stores selected.']);
    exit;
}

// Escape strings for query
$escaped_scodes = array_map(function($s) use ($db) {
    return "'" . $db->real_escape_string($s) . "'";
}, $scodes);

$id_list = implode(',', $escaped_scodes);

$query = "DELETE FROM storecode WHERE scode IN ($id_list)";

if ($db->query($query)) {
    $affected = $db->affected_rows;
    echo json_encode(['success' => true, 'message' => "$affected stores successfully removed."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
}
?>
