<?php
session_start();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../includes/db.php';

    $is_admin = (($_SESSION['role'] ?? '') === 'admin' || ($_SESSION['user'] ?? '') === 'admin' || !empty($_SESSION['is_admin']));
    if (!isset($_SESSION['user']) || !$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin privileges required.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $db = db_connect();

    // Auto-migrate: Ensure promo columns exist in system_settings table
    $existing_cols = [];
    $sc_res = $db->query("SHOW COLUMNS FROM system_settings");
    if ($sc_res) {
        while ($r = $sc_res->fetch_assoc()) {
            $existing_cols[] = strtolower($r['Field']);
        }
    }

    $column_definitions = [
        'promo_enabled'    => "ALTER TABLE system_settings ADD COLUMN promo_enabled TINYINT(1) NOT NULL DEFAULT 1",
        'promo_name'       => "ALTER TABLE system_settings ADD COLUMN promo_name VARCHAR(150) NOT NULL DEFAULT 'RL Shoe Bag Promo'",
        'promo_min_spend'  => "ALTER TABLE system_settings ADD COLUMN promo_min_spend DECIMAL(10,2) NOT NULL DEFAULT 1999.00",
        'promo_item_name'  => "ALTER TABLE system_settings ADD COLUMN promo_item_name VARCHAR(150) NOT NULL DEFAULT 'RL Shoe Bag'",
        'promo_item_no'    => "ALTER TABLE system_settings ADD COLUMN promo_item_no VARCHAR(50) NOT NULL DEFAULT '475552'",
        'promo_style_code' => "ALTER TABLE system_settings ADD COLUMN promo_style_code VARCHAR(100) NOT NULL DEFAULT 'RACT95002T26'",
        'promo_start_date' => "ALTER TABLE system_settings ADD COLUMN promo_start_date DATE NULL DEFAULT '2026-10-01'",
        'promo_end_date'   => "ALTER TABLE system_settings ADD COLUMN promo_end_date DATE NULL DEFAULT NULL",
        'promo_stores'     => "ALTER TABLE system_settings ADD COLUMN promo_stores TEXT NULL"
    ];

    foreach ($column_definitions as $col_name => $sql_query) {
        if (!in_array($col_name, $existing_cols)) {
            @$db->query($sql_query);
        }
    }

    // Check if JSON body or POST form data
    $inputData = [];
    if (!empty($_POST)) {
        $inputData = $_POST;
    } else {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $inputData = $decoded;
            }
        }
    }

    $promo_enabled   = !empty($inputData['promo_enabled']) ? 1 : 0;
    $promo_name      = trim($inputData['promo_name'] ?? 'RL Shoe Bag Promo');
    $promo_min_spend = floatval($inputData['promo_min_spend'] ?? 1999.00);
    $promo_item_name = trim($inputData['promo_item_name'] ?? 'RL Shoe Bag');
    $promo_item_no   = trim($inputData['promo_item_no'] ?? '475552');
    $promo_style_code = trim($inputData['promo_style_code'] ?? 'RACT95002T26');
    $promo_start_date = !empty($inputData['promo_start_date']) ? "'" . $db->real_escape_string($inputData['promo_start_date']) . "'" : "NULL";
    $promo_end_date   = !empty($inputData['promo_end_date'])   ? "'" . $db->real_escape_string($inputData['promo_end_date']) . "'"   : "NULL";

    // Handle promo_stores: can be 'ALL' or array/comma-separated list of store codes
    $store_scope = $inputData['promo_store_scope'] ?? '';
    if ($store_scope === 'all') {
        $promo_stores = 'ALL';
    } else {
        $stores_raw = $inputData['promo_stores'] ?? 'ALL';
        if (is_array($stores_raw)) {
            $cleaned = array_values(array_unique(array_filter(array_map('trim', $stores_raw))));
            $promo_stores = count($cleaned) > 0 ? json_encode($cleaned) : 'ALL';
        } else {
            $stores_str = trim($stores_raw);
            if ($stores_str === '' || strtoupper($stores_str) === 'ALL') {
                $promo_stores = 'ALL';
            } else {
                $promo_stores = $stores_str;
            }
        }
    }

    if ($promo_name === '') $promo_name = 'RL Shoe Bag Promo';
    if ($promo_item_name === '') $promo_item_name = 'RL Shoe Bag';
    if ($promo_item_no === '') $promo_item_no = '475552';
    if ($promo_style_code === '') $promo_style_code = 'RACT95002T26';

    // Ensure system_settings has at least one row
    $chk = $db->query("SELECT id FROM system_settings LIMIT 1");
    if (!$chk || $chk->num_rows === 0) {
        $db->query("INSERT INTO system_settings (company_name, promo_enabled, promo_name, promo_min_spend, promo_item_name, promo_item_no, promo_style_code, promo_start_date, promo_stores) VALUES ('Concession System', $promo_enabled, '" . $db->real_escape_string($promo_name) . "', $promo_min_spend, '" . $db->real_escape_string($promo_item_name) . "', '" . $db->real_escape_string($promo_item_no) . "', '" . $db->real_escape_string($promo_style_code) . "', $promo_start_date, '" . $db->real_escape_string($promo_stores) . "')");
        echo json_encode(['success' => true, 'message' => 'Promo settings saved successfully.']);
        exit;
    }

    $updates = [
        "promo_enabled = " . $promo_enabled,
        "promo_name = '" . $db->real_escape_string($promo_name) . "'",
        "promo_min_spend = " . $promo_min_spend,
        "promo_item_name = '" . $db->real_escape_string($promo_item_name) . "'",
        "promo_item_no = '" . $db->real_escape_string($promo_item_no) . "'",
        "promo_style_code = '" . $db->real_escape_string($promo_style_code) . "'",
        "promo_start_date = " . $promo_start_date,
        "promo_end_date = " . $promo_end_date,
        "promo_stores = '" . $db->real_escape_string($promo_stores) . "'"
    ];

    $query = "UPDATE system_settings SET " . implode(', ', $updates);

    if ($db->query($query)) {
        echo json_encode(['success' => true, 'message' => 'Promo settings saved successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save promo settings: ' . $db->error]);
    }
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
