<?php
session_start();
require '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || (($_SESSION['role'] ?? '') !== 'admin' && empty($_SESSION['is_admin']))) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$db = db_connect();

$promo_enabled   = isset($_POST['promo_enabled']) ? 1 : 0;
$promo_name      = trim($_POST['promo_name'] ?? 'RL Shoe Bag Promo');
$promo_min_spend = floatval($_POST['promo_min_spend'] ?? 1999.00);
$promo_item_name = trim($_POST['promo_item_name'] ?? 'RL Shoe Bag');
$promo_item_no   = trim($_POST['promo_item_no'] ?? '475552');
$promo_style_code = trim($_POST['promo_style_code'] ?? 'RACT95002T26');
$promo_start_date = !empty($_POST['promo_start_date']) ? "'" . $db->real_escape_string($_POST['promo_start_date']) . "'" : "NULL";
$promo_end_date   = !empty($_POST['promo_end_date'])   ? "'" . $db->real_escape_string($_POST['promo_end_date']) . "'"   : "NULL";

if ($promo_name === '') {
    $promo_name = 'RL Shoe Bag Promo';
}
if ($promo_item_name === '') {
    $promo_item_name = 'RL Shoe Bag';
}

$updates = [
    "promo_enabled = " . $promo_enabled,
    "promo_name = '" . $db->real_escape_string($promo_name) . "'",
    "promo_min_spend = " . $promo_min_spend,
    "promo_item_name = '" . $db->real_escape_string($promo_item_name) . "'",
    "promo_item_no = '" . $db->real_escape_string($promo_item_no) . "'",
    "promo_style_code = '" . $db->real_escape_string($promo_style_code) . "'",
    "promo_start_date = " . $promo_start_date,
    "promo_end_date = " . $promo_end_date
];

$query = "UPDATE system_settings SET " . implode(', ', $updates);

if ($db->query($query)) {
    echo json_encode(['success' => true, 'message' => 'Promo settings saved successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save promo settings: ' . $db->error]);
}
