<?php
require_once __DIR__ . '/../includes/db.php';
$db = db_connect();

$res = $db->query("SELECT id, permissions FROM roles WHERE role_name IN ('store_admin', 'multi_store_admin')");
while ($r = $res->fetch_assoc()) {
    $p = json_decode($r['permissions'], true) ?: [];
    if (!in_array('non_submission', $p)) {
        $p[] = 'non_submission';
        $new_p = json_encode($p);
        $db->query("UPDATE roles SET permissions = '$new_p' WHERE id = {$r['id']}");
    }
}
echo "Done";
