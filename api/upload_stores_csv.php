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

// Optional: skip header row if it exists
// $header = fgetcsv($handle); 

$success_count = 0;
$error_count = 0;

$db->begin_transaction();

try {
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < 2) continue;
        
        $scode = trim($data[0]);
        $sname = trim($data[1]);
        
        if ($scode === '' || $sname === '') continue;

        $stmt = $db->prepare("INSERT INTO storecode (scode, sname) VALUES (?, ?) ON DUPLICATE KEY UPDATE sname = VALUES(sname)");
        $stmt->bind_param("ss", $scode, $sname);
        
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
        'message' => "Import complete. $success_count stores added/updated." . ($error_count > 0 ? " ($error_count errors occurred)" : "")
    ]);

} catch (Exception $e) {
    $db->rollback();
    fclose($handle);
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>
