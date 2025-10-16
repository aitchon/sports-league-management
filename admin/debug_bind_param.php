<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Debug Bind Param</h2>";

// Test 1: Very simple insert with error checking
echo "<h3>Test 1: Simple Insert with Error Checking</h3>";
try {
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Prepare successful</p>";
        
        // Test bind_param with error checking
        $bind_result = $stmt->bind_param("ssss", '2024-01-01 10:00:00', 'Test Court', 'doubles', 'scheduled');
        if (!$bind_result) {
            echo "<p style='color: red;'>❌ Bind failed: " . $stmt->error . "</p>";
            echo "<p><strong>Connection error:</strong> " . $conn->error . "</p>";
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

// Test 2: Check if the issue is with the specific values
echo "<h3>Test 2: Different Values</h3>";
try {
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Prepare successful</p>";
        
        // Try with different values
        $bind_result = $stmt->bind_param("ssss", '2024-01-02 11:00:00', 'Another Court', 'singles', 'scheduled');
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

// Test 3: Check MySQL version and settings
echo "<h3>Test 3: MySQL Version and Settings</h3>";
$version = $conn->query("SELECT VERSION() as version");
$version_row = $version->fetch_assoc();
echo "<p><strong>MySQL Version:</strong> " . $version_row['version'] . "</p>";

$sql_mode = $conn->query("SELECT @@sql_mode as sql_mode");
$sql_mode_row = $sql_mode->fetch_assoc();
echo "<p><strong>SQL Mode:</strong> " . $sql_mode_row['sql_mode'] . "</p>";

$conn->close();
?>



