<?php
require_once __DIR__ . '/../includes/db.php';
$db = db_connect();

$sql = "CREATE TABLE IF NOT EXISTS receiving (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)    NOT NULL,
    store_code  VARCHAR(50)     NOT NULL, -- Receiver store
    os_no       VARCHAR(100)    NOT NULL, -- Order Slip #
    item_no     VARCHAR(150)    NOT NULL,
    from_store  VARCHAR(50)     NOT NULL, -- Source store
    quantity    INT             NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username   (username),
    INDEX idx_store_code (store_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($db->query($sql)) {
    echo "Receiving table created successfully.\n";
} else {
    echo "Error creating table: " . $db->error . "\n";
}
?>
