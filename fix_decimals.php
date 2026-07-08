<?php
require_once 'includes/db.php';
$db = db_connect();

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>";
echo "<h2 style='color: #333;'>Database Fix: Restoring Sales Decimals</h2>";

// Run the query to restore decimals
$query = "UPDATE sales SET amount_sold = line_total / quantity WHERE quantity > 0";
$result = $db->query($query);

if ($result) {
    echo "<p style='color: #16a34a;'><strong>&#10004; Success!</strong> Successfully restored missing decimals in the sales table.</p>";
    echo "<p>Total Sales Records Fixed: <strong>" . $db->affected_rows . "</strong></p>";
} else {
    echo "<p style='color: #dc2626;'><strong>&#10008; Error executing query:</strong> " . $db->error . "</p>";
}

echo "<hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>";
echo "<p style='color: #d97706; font-weight: bold;'>⚠️ IMPORTANT SECURITY REMINDER:</p>";
echo "<p style='font-size: 14px;'>Please delete this file (<code>fix_decimals.php</code>) from your server immediately to prevent unauthorized database modifications.</p>";
echo "</div>";
?>
