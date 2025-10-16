<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Debug Table Issue</h2>";

// Check if table is empty
$count = $conn->query("SELECT COUNT(*) as count FROM pickleball_games");
$row = $count->fetch_assoc();
echo "<p><strong>Current records in table:</strong> " . $row['count'] . "</p>";

// Check table structure again
echo "<h3>Table Structure:</h3>";
$structure = $conn->query("DESCRIBE pickleball_games");
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

// Test direct insert without prepared statements
echo "<h3>Testing Direct Insert:</h3>";
try {
    $result = $conn->query("INSERT INTO pickleball_games (date, location, game_type, status) VALUES ('2024-01-01 10:00:00', 'Test Court', 'doubles', 'scheduled')");
    if ($result) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Direct insert successful! ID: $insert_id</p>";
        
        // Check what was actually saved
        $verify = $conn->query("SELECT * FROM pickleball_games WHERE id = $insert_id");
        $record = $verify->fetch_assoc();
        echo "<p><strong>Saved record:</strong></p>";
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

// Test prepared statement with minimal parameters
echo "<h3>Testing Minimal Prepared Statement:</h3>";
try {
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Prepare successful</p>";
        
        $bind_result = $stmt->bind_param("ssss", '2024-01-01 10:00:00', 'Test Court', 'doubles', 'scheduled');
        if (!$bind_result) {
            echo "<p style='color: red;'>❌ Bind failed: " . $stmt->error . "</p>";
        } else {
            echo "<p style='color: green;'>✅ Bind successful</p>";
            
            if ($stmt->execute()) {
                $insert_id = $conn->insert_id;
                echo "<p style='color: green;'>✅ Execute successful! ID: $insert_id</p>";
                
                // Check what was actually saved
                $verify = $conn->query("SELECT * FROM pickleball_games WHERE id = $insert_id");
                $record = $verify->fetch_assoc();
                echo "<p><strong>Saved record:</strong></p>";
                echo "<pre>" . print_r($record, true) . "</pre>";
                
                // Clean up
                $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
                echo "<p>Test record cleaned up.</p>";
            } else {
                echo "<p style='color: red;'>❌ Execute failed: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Prepared statement error: " . $e->getMessage() . "</p>";
}

// Check for any constraints or triggers
echo "<h3>Table Constraints:</h3>";
$constraints = $conn->query("SHOW CREATE TABLE pickleball_games");
$create_table = $constraints->fetch_assoc();
echo "<pre>" . htmlspecialchars($create_table['Create Table']) . "</pre>";

$conn->close();
?>



