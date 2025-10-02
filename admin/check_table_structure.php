<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Pickleball Games Table Structure Check</h2>";

// Get detailed table structure
$structure = $conn->query("DESCRIBE pickleball_games");
echo "<h3>Detailed Table Structure:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $structure->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check for constraints
echo "<h3>Table Constraints:</h3>";
$constraints = $conn->query("SHOW CREATE TABLE pickleball_games");
$create_table = $constraints->fetch_assoc();
echo "<pre>" . htmlspecialchars($create_table['Create Table']) . "</pre>";

// Test a simple insert without prepared statements
echo "<h3>Testing Direct Insert:</h3>";
try {
    $result = $conn->query("INSERT INTO pickleball_games (date, location, game_type, status) VALUES ('2024-01-01 10:00:00', 'Test Court', 'doubles', 'scheduled')");
    if ($result) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Direct insert successful! ID: $insert_id</p>";
        
        // Verify the data
        $verify = $conn->query("SELECT * FROM pickleball_games WHERE id = $insert_id");
        $record = $verify->fetch_assoc();
        echo "<p><strong>Inserted record:</strong></p>";
        echo "<pre>" . print_r($record, true) . "</pre>";
        
        // Clean up
        $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
        echo "<p>Test record cleaned up.</p>";
    } else {
        echo "<p style='color: red;'>❌ Direct insert failed: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Direct insert error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>

