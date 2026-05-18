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

// Fetch original records BEFORE deleting
$records_to_log = [];
$module_name = '';
if (in_array($table, ['sales', 'receiving', 'returns', 'pullouts'])) {
    $module_map = [
        'sales' => 'Sale',
        'receiving' => 'Receiving',
        'returns' => 'Return',
        'pullouts' => 'Pullout'
    ];
    $module_name = $module_map[$table];
    
    // Choose correct reference column based on table
    $ref_col = 'item_no';
    if ($table === 'receiving') $ref_col = 'os_no';
    elseif ($table === 'returns') $ref_col = 'COALESCE(return_item, exchange_item, \'Return Item\')';
    
    $old_res = $db->query("SELECT id, store_code, $ref_col AS reference, quantity FROM `$table` WHERE id IN ($id_list)");
    if ($old_res) {
        while ($row = $old_res->fetch_assoc()) {
            $records_to_log[] = $row;
        }
    }
}

$query = "DELETE FROM `$table` WHERE id IN ($id_list) $extra_where";

if ($db->query($query)) {
    $affected = $db->affected_rows;
    // Log activity for each deleted record
    if (!empty($records_to_log)) {
        foreach ($records_to_log as $r) {
            log_activity(
                $db,
                $_SESSION['user'],
                'delete',
                $module_name,
                $r['store_code'],
                $r['reference'],
                $r['quantity'],
                "Bulk Deleted $module_name #{$r['id']}: Item '{$r['reference']}', quantity {$r['quantity']}"
            );
        }
    }
    echo json_encode(['success' => true, 'message' => "$affected records successfully removed."]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->error]);
}
?>
