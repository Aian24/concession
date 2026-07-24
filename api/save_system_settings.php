<?php
session_start();
require '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$db = db_connect();

$company_name = $_POST['company_name'] ?? 'Concession System';
$time_format = $_POST['time_format'] ?? '12h';
$logo_radius = isset($_POST['logo_radius']) ? (int)$_POST['logo_radius'] : 0;
$logo_size = isset($_POST['logo_size']) ? (int)$_POST['logo_size'] : 96;

$updates = [
    "company_name = '" . $db->real_escape_string($company_name) . "'",
    "time_format = '" . $db->real_escape_string($time_format) . "'",
    "logo_radius = " . $logo_radius,
    "logo_size = " . $logo_size
];

// Handle Logo Upload
if (isset($_FILES['logo_path']) && $_FILES['logo_path']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
    if (in_array($_FILES['logo_path']['type'], $allowedTypes)) {
        $ext = pathinfo($_FILES['logo_path']['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '.' . $ext;
        $dest = '../images/' . $filename;
        if (move_uploaded_file($_FILES['logo_path']['tmp_name'], $dest)) {
            $updates[] = "logo_path = 'images/" . $filename . "'";
        }
    }
}

// Handle Favicon Upload
if (isset($_FILES['favicon_path']) && $_FILES['favicon_path']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/x-icon', 'image/svg+xml'];
    if (in_array($_FILES['favicon_path']['type'], $allowedTypes)) {
        $ext = pathinfo($_FILES['favicon_path']['name'], PATHINFO_EXTENSION);
        $filename = 'favicon_' . time() . '.' . $ext;
        $dest = '../assets/images/' . $filename;
        if (move_uploaded_file($_FILES['favicon_path']['tmp_name'], $dest)) {
            $updates[] = "favicon_path = 'assets/images/" . $filename . "'";
        }
    }
}

$query = "UPDATE system_settings SET " . implode(', ', $updates);
if ($db->query($query)) {
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $db->error]);
}
