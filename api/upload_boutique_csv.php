<?php
session_start();
header('Content-Type: application/json');

$is_full_admin = (($_SESSION['role'] ?? 'user') === 'admin' || ($_SESSION['user'] ?? '') === 'admin');
if (!$is_full_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Full Admin access required.']);
    exit;
}

require_once '../includes/db.php';
$db = db_connect();

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$file = $_FILES['csv']['tmp_name'];
$handle = fopen($file, 'r');

if ($handle === false) {
    echo json_encode(['success' => false, 'message' => 'Could not open uploaded file.']);
    exit;
}

$success_count = 0;
$error_count = 0;

$db->begin_transaction();

try {
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < 5) continue;
        
        $date = trim($data[0]);
        $store_name = trim($data[1]);
        $store_code = trim($data[2]);
        $qty_sold = trim($data[3]);
        $amount = trim($data[4]);
        
        if (empty($date) || empty($store_code)) continue;

        $parsed_date = strtotime($date);
        if ($parsed_date !== false) {
            $date = date('Y-m-d', $parsed_date);
        }

        $qty_sold = intval(str_replace(',', '', $qty_sold));
        $amount = floatval(str_replace(',', '', $amount));

        $stmt = $db->prepare("INSERT INTO boutique (date, store_name, store_code, qty_sold, amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssid", $date, $store_name, $store_code, $qty_sold, $amount);
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
        $stmt->close();
    }
    
    $db->commit();
    fclose($handle);
    
    echo json_encode([
        'success' => true, 
        'message' => "Import complete. $success_count records added." . ($error_count > 0 ? " ($error_count errors occurred)" : "")
    ]);

} catch (Exception $e) {
    $db->rollback();
    fclose($handle);
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
