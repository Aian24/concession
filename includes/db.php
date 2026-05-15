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

    return $conn;
}
