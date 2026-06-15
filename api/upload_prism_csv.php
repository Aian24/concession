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
        if (count($data) < 6) continue;
        
        $item_no   = trim($data[0]);
        $itemcode  = trim($data[1]);
        $stylename = trim($data[2]);
        $color     = trim($data[3]);
        $size      = trim($data[4]);
        $srp       = trim($data[5]);
        
        if ($item_no === '') continue;

        // Convert SRP to float, remove commas if any
        $srp = floatval(str_replace(',', '', $srp));

        $stmt = $db->prepare("INSERT INTO prismdata (item_no, itemcode, stylename, color, size, srp) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE itemcode = VALUES(itemcode), stylename = VALUES(stylename), color = VALUES(color), size = VALUES(size), srp = VALUES(srp)");
        $stmt->bind_param("sssssd", $item_no, $itemcode, $stylename, $color, $size, $srp);
        
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
        'message' => "Import complete. $success_count records added/updated." . ($error_count > 0 ? " ($error_count errors occurred)" : "")
    ]);

} catch (Exception $e) {
    $db->rollback();
    fclose($handle);
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
