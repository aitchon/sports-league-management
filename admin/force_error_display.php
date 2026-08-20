<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Force Error Display</h2>";

// Test 1: Force error display
echo "<h3>Test 1: Force Error Display</h3>";
try {
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Prepare successful</p>";
        
        // Force error display
        echo "<p><strong>About to call bind_param...</strong></p>";
        
        $bind_result = $stmt->bind_param("ssss", '2024-01-01 10:00:00', 'Test Court', 'doubles', 'scheduled');
        
        echo "<p><strong>bind_param result:</strong> " . ($bind_result ? 'TRUE' : 'FALSE') . "</p>";
        echo "<p><strong>stmt->error:</strong> " . $stmt->error . "</p>";
        echo "<p><strong>conn->error:</strong> " . $conn->error . "</p>";
        
        if (!$bind_result) {
            echo "<p style='color: red;'>❌ Bind failed: " . $stmt->error . "</p>";
        } else {
            echo "<p style='color: green;'>✅ Bind successful</p>";
            
            echo "<p><strong>About to call execute...</strong></p>";
            
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

// Test 2: Check if the issue is with the specific string values
echo "<h3>Test 2: Test with Variables</h3>";
try {
    $date = '2024-01-01 10:00:00';
    $location = 'Test Court';
    $game_type = 'doubles';
    $status = 'scheduled';
    
    echo "<p><strong>Variables:</strong></p>";
    echo "<p>date: '" . $date . "'</p>";
    echo "<p>location: '" . $location . "'</p>";
    echo "<p>game_type: '" . $game_type . "'</p>";
    echo "<p>status: '" . $status . "'</p>";
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Prepare successful</p>";
        
        $bind_result = $stmt->bind_param("ssss", $date, $location, $game_type, $status);
        
        echo "<p><strong>bind_param result:</strong> " . ($bind_result ? 'TRUE' : 'FALSE') . "</p>";
        echo "<p><strong>stmt->error:</strong> " . $stmt->error . "</p>";
        echo "<p><strong>conn->error:</strong> " . $conn->error . "</p>";
        
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

$conn->close();
?>




