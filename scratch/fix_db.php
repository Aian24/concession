<?php
$db = new mysqli('localhost', 'root', '', 'concession_db');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
$result = $db->query("ALTER TABLE storecode ADD PRIMARY KEY (scode)");
if ($result) {
    echo "Primary key added successfully.";
} else {
    echo "Error: " . $db->error;
}
$db->close();
?>
