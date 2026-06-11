<?php
$db = new mysqli('127.0.0.1', 'root', '', 'concession_db');
$perms = json_encode(['dashboard', 'create_sale', 'create_return', 'create_receiving', 'create_ros_supplies', 'history']);
$db->query("UPDATE roles SET permissions = '$perms' WHERE role_name = 'user'");
echo 'Done';
