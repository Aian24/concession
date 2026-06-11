<?php
require_once __DIR__ . '/../includes/db.php';

$db = db_connect();

$sql = "CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    permissions JSON NULL,
    can_submit TINYINT(1) DEFAULT 0,
    can_edit TINYINT(1) DEFAULT 0,
    can_delete TINYINT(1) DEFAULT 0,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($db->query($sql)) {
    echo "Table 'roles' created or already exists.\n";
} else {
    echo "Error creating table: " . $db->error . "\n";
}

// Seed default roles if empty
$res = $db->query("SELECT COUNT(*) as c FROM roles");
$row = $res->fetch_assoc();
if ($row['c'] == 0) {
    $default_roles = [
        ['admin', 'Full Administrator', '["dashboard", "monitoring", "sale", "return", "receiving", "ros_supplies", "history", "admin", "stores", "roles", "prism_data", "boutique_data", "recent_activity", "non_submission"]', 0, 1, 1, 1],
        ['admin_view', 'View Only Admin', '["dashboard", "monitoring", "sale", "return", "receiving", "ros_supplies", "history", "non_submission"]', 0, 1, 1, 1],
        ['store_admin', 'Store Admin', '["dashboard", "monitoring", "sale", "return", "receiving", "ros_supplies", "history"]', 0, 0, 0, 1],
        ['multi_store_admin', 'Multi-Store Admin', '["dashboard", "monitoring", "sale", "return", "receiving", "ros_supplies", "history"]', 0, 1, 0, 1],
        ['user', 'Sales Agent (User)', '["sale", "return", "receiving", "ros_supplies", "history"]', 1, 0, 0, 0],
    ];

    $stmt = $db->prepare("INSERT INTO roles (role_name, display_name, permissions, can_submit, can_edit, can_delete, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($default_roles as $r) {
        $stmt->bind_param("sssiiii", $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]);
        $stmt->execute();
    }
    echo "Default roles seeded.\n";
} else {
    echo "Roles table already seeded.\n";
}
?>
