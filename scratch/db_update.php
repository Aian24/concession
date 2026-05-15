<?php
require_once '../includes/db.php';

/**
 * DATABASE UPDATE SCRIPT
 * ----------------------
 * Use this to run SQL commands on Bluehost without opening phpMyAdmin.
 * 
 * INSTRUCTIONS:
 * 1. Put your SQL command in the $sql variable below.
 * 2. Push to GitHub.
 * 3. Visit: https://rustylopez.com/Concession/scratch/db_update.php
 * 4. IMPORTANT: Delete the SQL from this file after it runs successfully.
 */

// --- ADD YOUR SQL HERE ---
$sql = "
    -- Example: CREATE TABLE IF NOT EXISTS test_table (id INT PRIMARY KEY);
";
// -------------------------

if (trim($sql) == "") {
    die("No SQL provided. Please edit the script first.");
}

$db = db_connect();

if ($db->multi_query($sql)) {
    echo "<h1 style='color:green;'>?? Database Updated Successfully!</h1>";
    do {
        if ($result = $db->store_result()) {
            $result->free();
        }
    } while ($db->more_results() && $db->next_result());
} else {
    echo "<h1 style='color:red;'>?? Error updating database:</h1>";
    echo "<pre>" . $db->error . "</pre>";
}

$db->close();
