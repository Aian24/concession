<?php
date_default_timezone_set('Asia/Manila');

/**
 * Database Connection — Concession System
 * Provides a shared MySQLi connection via db_connect()
 */

define('DB_HOST',   'localhost');
define('DB_USER',   'root');        // Change if your MySQL user is different
define('DB_PASS',   '');            // Change to your MySQL password
define('DB_NAME',   'concession_db');
define('DB_PORT',   3306);

function db_connect(): mysqli {
    static $conn = null;
    if ($conn !== null) return $conn;

    // Connect to MySQL server without specifying database
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);

    if ($conn->connect_error) {
        error_log('DB Server Connection failed: ' . $conn->connect_error);
        die('Database connection failed. Please contact the administrator.');
    }

    $conn->set_charset('utf8mb4');

    // Attempt to select the database
    if (!$conn->select_db(DB_NAME)) {
        // If database does not exist, create it
        $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->select_db(DB_NAME);

        // Run the setup.sql schema automatically
        $sql_file = __DIR__ . '/../database/setup.sql';
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            if ($conn->multi_query($sql)) {
                // Flush multi_query results to avoid "Commands out of sync" errors later
                do {
                    if ($res = $conn->store_result()) {
                        $res->free();
                    }
                } while ($conn->more_results() && $conn->next_result());
            }
        }
    }

    $conn->query("SET time_zone = '+08:00'");

    // Ensure Admin user exists if not present
    $hasAdmin = $conn->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    if ($hasAdmin && $hasAdmin->num_rows === 0) {
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $adminStmt = $conn->prepare("INSERT INTO users (username, password, store_code, role) VALUES ('admin', ?, 'ADMIN-001', 'admin')");
        $adminStmt->bind_param("s", $adminPass);
        $adminStmt->execute();
        $adminStmt->close();
    }

    // Ensure user_store_assignments table exists
    $hasTable = $conn->query("SHOW TABLES LIKE 'user_store_assignments'");
    if ($hasTable && $hasTable->num_rows === 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS user_store_assignments (
            id          INT             AUTO_INCREMENT PRIMARY KEY,
            user_id     INT             NOT NULL,
            store_code  VARCHAR(50)     NOT NULL,
            created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_assignment (user_id, store_code),
            INDEX idx_user_id (user_id),
            INDEX idx_store_code (store_code),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    // Ensure multi_store_admin role exists in ENUM
    $col_res = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($col_res && $col_res->num_rows > 0) {
        $col = $col_res->fetch_assoc();
        if (strpos($col['Type'], 'multi_store_admin') === false) {
            $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','user','admin_view','store_admin','multi_store_admin') DEFAULT 'user'");
        }
    }

    return $conn;
}

/**
 * Get all store codes assigned to a user (for multi_store_admin role)
 * Returns an array of store codes, or empty array if none assigned
 */
function get_user_assigned_stores(mysqli $db, int $user_id): array {
    $stmt = $db->prepare("
        SELECT usa.store_code, sc.sname 
        FROM user_store_assignments usa 
        LEFT JOIN storecode sc ON usa.store_code = sc.scode 
        WHERE usa.user_id = ?
        ORDER BY sc.sname ASC
    ");
    if (!$stmt) return [];
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}

/**
 * Build a SQL WHERE clause for multi-store filtering
 * Returns the SQL fragment and parameters for use with prepared statements
 */
function build_multi_store_clause(string $column, array $store_codes): string {
    if (empty($store_codes)) return " AND 1=0"; // no stores assigned = no access
    $placeholders = implode(',', array_map(function($code) {
        return "'" . addslashes($code) . "'";
    }, $store_codes));
    return " AND $column IN ($placeholders)";
}

/**
 * Log user activity in a unified audit trail table
 */
function log_activity(mysqli $db, string $username, string $action_type, string $module, string $store_code, string $reference, int $quantity, ?string $details = null): bool {
    // Auto-create and backpopulate if table does not exist
    $table_check = $db->query("SHOW TABLES LIKE 'activity_log'");
    if ($table_check && $table_check->num_rows === 0) {
        $db->query("CREATE TABLE activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            module ENUM('Sale', 'Return', 'Receiving', 'Pullout') DEFAULT NULL,
            store_code VARCHAR(50) DEFAULT NULL,
            reference VARCHAR(150) DEFAULT NULL,
            quantity INT NOT NULL DEFAULT 0,
            details TEXT DEFAULT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_action_type (action_type),
            INDEX idx_module (module),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Backpopulate existing entries as 'create' actions
        $db->query("INSERT INTO activity_log (username, action_type, module, store_code, reference, quantity, details, created_at)
            SELECT username, 'create', 'Sale', store_code, item_no, quantity, CONCAT('Created sale entry for item #', item_no), created_at FROM sales");
        $db->query("INSERT INTO activity_log (username, action_type, module, store_code, reference, quantity, details, created_at)
            SELECT username, 'create', 'Return', store_code, COALESCE(return_item, exchange_item, 'Return Item'), quantity, CONCAT('Created return/exchange entry for item #', COALESCE(return_item, exchange_item, 'Return Item')), created_at FROM returns");
        $db->query("INSERT INTO activity_log (username, action_type, module, store_code, reference, quantity, details, created_at)
            SELECT username, 'create', 'Receiving', store_code, os_no, quantity, CONCAT('Created receiving entry for OS #', os_no), created_at FROM receiving");
        $db->query("INSERT INTO activity_log (username, action_type, module, store_code, reference, quantity, details, created_at)
            SELECT username, 'create', 'Pullout', store_code, item_no, quantity, CONCAT('Created pullout entry for item #', item_no), created_at FROM pullouts");
    } else {
        // Table exists, let's verify and alter if columns are missing
        $cols = [];
        $res = $db->query("DESCRIBE activity_log");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[] = strtolower($row['Field']);
            }
        }
        if (!in_array('module', $cols)) {
            $db->query("ALTER TABLE activity_log ADD COLUMN module ENUM('Sale', 'Return', 'Receiving', 'Pullout') DEFAULT NULL AFTER action_type");
        }
        if (!in_array('reference', $cols)) {
            $db->query("ALTER TABLE activity_log ADD COLUMN reference VARCHAR(150) DEFAULT NULL AFTER store_code");
        }
        if (!in_array('quantity', $cols)) {
            $db->query("ALTER TABLE activity_log ADD COLUMN quantity INT NOT NULL DEFAULT 0 AFTER reference");
        }
        if (!in_array('details', $cols)) {
            $db->query("ALTER TABLE activity_log ADD COLUMN details TEXT DEFAULT NULL AFTER quantity");
        }
    }

    $stmt = $db->prepare("INSERT INTO activity_log (username, action_type, module, store_code, reference, quantity, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("sssssis", $username, $action_type, $module, $store_code, $reference, $quantity, $details);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

