<?php
require_once __DIR__ . '/../includes/db.php';
$db = db_connect();
$db->query("ALTER TABLE prismdata ADD COLUMN stylename VARCHAR(255) NULL AFTER item_no, ADD COLUMN color VARCHAR(100) NULL AFTER stylename, ADD COLUMN size VARCHAR(100) NULL AFTER color;");
echo $db->error ? "Error: " . $db->error : "Success";
