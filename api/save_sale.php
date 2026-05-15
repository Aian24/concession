<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$username   = $_SESSION['user'];
$store_code = $_SESSION['store_code'] ?? '';
$inputData = json_decode(file_get_contents('php://input'), true);
$entries   = $inputData['entries'] ?? [];
$req_date  = $inputData['transaction_date'] ?? ($_SESSION['transaction_date'] ?? date('Y-m-d'));

if (empty($entries) || !is_array($entries)) {
    echo json_encode(['success' => false, 'message' => 'No entries received']);
    exit;
}

$db   = db_connect();
$stmt = $db->prepare(
    "INSERT INTO sales (username, store_code, item_no, amount_sold, quantity, line_total, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);

$saved  = 0;
$errors = [];

foreach ($entries as $entry) {
    $item_no     = trim($entry['item_no']     ?? '');
    $amount_sold = floatval($entry['amount_sold'] ?? 0);
    $quantity    = intval($entry['quantity']   ?? 0);
    $line_total  = $amount_sold * $quantity;

    if ($item_no === '') {
        $errors[] = "Skipped a row: item_no is empty.";
        continue;
    }

    $created_at = $req_date . ' ' . date('H:i:s');
    $stmt->bind_param("sssdids", $username, $store_code, $item_no, $amount_sold, $quantity, $line_total, $created_at);

    if ($stmt->execute()) {
        $saved++;
    } else {
        $errors[] = "DB error for item '{$item_no}': " . $stmt->error;
    }
}

$stmt->close();

echo json_encode([
    'success' => $saved > 0,
    'saved'   => $saved,
    'errors'  => $errors,
    'message' => $saved > 0 ? "{$saved} sale(s) saved successfully." : 'No sales were saved.',
]);
