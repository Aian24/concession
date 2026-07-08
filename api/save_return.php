<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);
$entries   = $inputData['entries'] ?? [];
$req_date  = $inputData['transaction_date'] ?? ($_SESSION['transaction_date'] ?? date('Y-m-d'));

if (empty($entries) || !is_array($entries)) {
    echo json_encode(['success' => false, 'message' => 'No valid entries received.']);
    exit;
}

$db = db_connect();
$stmt = $db->prepare(
    "INSERT INTO returns (username, store_code, return_item, quantity, return_amount, reason, is_exchange, exchange_name, exchange_item, exchange_quantity, exchange_amount, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$username  = $_SESSION['user'];
$store_code = $_SESSION['store_code'] ?? '';
$saved = 0;
$errors = [];

foreach ($entries as $data) {
    $return_item   = trim($data['return_item'] ?? '');
    $qty           = intval($data['quantity'] ?? 0);
    $raw_amount    = floatval($data['return_amount'] ?? 0);
    // Ensure return amount is negative for DB
    $return_amount = -abs($raw_amount);
    $reason      = trim($data['reason'] ?? '');
    $is_exchange = intval($data['is_exchange'] ?? 0);
    $ex_name     = $is_exchange ? trim($data['exchange_name'] ?? '') : null;
    $ex_item     = $is_exchange ? trim($data['exchange_item'] ?? '') : null;
    $ex_qty      = $is_exchange ? ((isset($data['exchange_quantity']) && $data['exchange_quantity'] !== '') ? intval($data['exchange_quantity']) : null) : null;
    $ex_amount   = $is_exchange ? ((isset($data['exchange_amount']) && $data['exchange_amount'] !== '') ? floatval($data['exchange_amount']) : null) : null;

    // A valid entry is either a return OR an exchange (or both)
    $has_return = ($return_item !== '');
    $has_exchange = ($is_exchange && ($ex_name !== '' || $ex_item !== ''));

    if (!$has_return && !$has_exchange) continue;

    // bind_param requires variable references
    // Return amount is always stored as negative
    $v_item   = ($return_item !== '') ? $return_item : null;
    $v_qty    = (isset($data['quantity']) && $data['quantity'] !== '') ? $qty : null;
    $v_amount = (isset($data['return_amount']) && $data['return_amount'] !== '') ? $return_amount : null;
    $v_reason = ($reason !== '') ? $reason : null;
    $created_at = $req_date . ' ' . date('H:i:s');

    $stmt->bind_param("sssidsissids", 
        $username, 
        $store_code, 
        $v_item, 
        $v_qty,
        $v_amount, 
        $v_reason,
        $is_exchange,
        $ex_name,
        $ex_item,
        $ex_qty,
        $ex_amount,
        $created_at
    );

    if ($stmt->execute()) {
        $saved++;
        $ref_item = ($return_item !== '') ? $return_item : ($ex_item !== '' ? $ex_item : 'Return Item');
        $log_qty = ($qty > 0) ? $qty : ($ex_qty > 0 ? $ex_qty : 1);
        log_activity($db, $username, 'create', 'Return', $store_code, $ref_item, $log_qty, "Created return/exchange entry for item #$ref_item");
    } else {
        $errors[] = $stmt->error;
    }
}

$stmt->close();

echo json_encode([
    'success' => $saved > 0,
    'message' => $saved > 0 ? "$saved record(s) saved successfully." : "Failed to save records.",
    'errors'  => $errors
]);
