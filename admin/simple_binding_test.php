<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Simple Binding Test</h2>";

// Check current table state
$count = $conn->query("SELECT COUNT(*) as count FROM pickleball_games");
$row = $count->fetch_assoc();
echo "<p><strong>Current records in table:</strong> " . $row['count'] . "</p>";

// Test 1: Very simple insert
echo "<h3>Test 1: Very Simple Insert</h3>";
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
                
                $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
                $result = $verify->fetch_assoc();
                echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
                
                $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
                echo "<p>Test record cleaned up.</p>";
            } else {
                echo "<p style='color: red;'>❌ Execute failed: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

// Test 2: With season_id
echo "<h3>Test 2: With Season ID</h3>";
try {
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status, season_id) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Prepare successful</p>";
        
        $bind_result = $stmt->bind_param("ssssi", '2024-01-01 10:00:00', 'Test Court', 'doubles', 'scheduled', 1);
        if (!$bind_result) {
            echo "<p style='color: red;'>❌ Bind failed: " . $stmt->error . "</p>";
        } else {
            echo "<p style='color: green;'>✅ Bind successful</p>";
            
            if ($stmt->execute()) {
                $insert_id = $conn->insert_id;
                echo "<p style='color: green;'>✅ Execute successful! ID: $insert_id</p>";
                
                $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
                $result = $verify->fetch_assoc();
                echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
                
                $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
                echo "<p>Test record cleaned up.</p>";
            } else {
                echo "<p style='color: red;'>❌ Execute failed: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

// Test 3: Check if there are any constraints
echo "<h3>Test 3: Check Table Constraints</h3>";
$constraints = $conn->query("SHOW CREATE TABLE pickleball_games");
$create_table = $constraints->fetch_assoc();
echo "<pre>" . htmlspecialchars($create_table['Create Table']) . "</pre>";

$conn->close();
?>

