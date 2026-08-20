<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Enable All Errors</h2>";

// Test 1: Very basic test with error reporting
echo "<h3>Test 1: Basic Test with Error Reporting</h3>";
try {
    echo "<p><strong>Starting test...</strong></p>";
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Prepare successful</p>";
        
        echo "<p><strong>About to call bind_param...</strong></p>";
        
        // Use a different approach - try to catch any fatal errors
        $bind_result = false;
        try {
            $bind_result = $stmt->bind_param("ssss", '2024-01-01 10:00:00', 'Test Court', 'doubles', 'scheduled');
        } catch (Error $e) {
            echo "<p style='color: red;'>❌ Fatal Error in bind_param: " . $e->getMessage() . "</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Exception in bind_param: " . $e->getMessage() . "</p>";
        }
        
        echo "<p><strong>bind_param completed. Result: " . ($bind_result ? 'TRUE' : 'FALSE') . "</strong></p>";
        
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
} catch (Error $e) {
    echo "<p style='color: red;'>❌ Fatal Error: " . $e->getMessage() . "</p>";
}

// Test 2: Check if it's a memory or timeout issue
echo "<h3>Test 2: Memory and Timeout Check</h3>";
echo "<p><strong>Memory limit:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>Max execution time:</strong> " . ini_get('max_execution_time') . "</p>";
echo "<p><strong>Current memory usage:</strong> " . memory_get_usage(true) . " bytes</p>";

// Test 3: Try a different approach - use mysqli_stmt_bind_param directly
echo "<h3>Test 3: Direct mysqli_stmt_bind_param</h3>";
try {
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Prepare successful</p>";
        
        // Use the procedural style
        $bind_result = mysqli_stmt_bind_param($stmt, "ssss", '2024-01-01 10:00:00', 'Test Court', 'doubles', 'scheduled');
        
        echo "<p><strong>mysqli_stmt_bind_param result: " . ($bind_result ? 'TRUE' : 'FALSE') . "</strong></p>";
        
        if (!$bind_result) {
            echo "<p style='color: red;'>❌ Bind failed: " . mysqli_stmt_error($stmt) . "</p>";
        } else {
            echo "<p style='color: green;'>✅ Bind successful</p>";
            
            if (mysqli_stmt_execute($stmt)) {
                $insert_id = mysqli_insert_id($conn);
                echo "<p style='color: green;'>✅ Execute successful! ID: $insert_id</p>";
                
                $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
                $result = $verify->fetch_assoc();
                echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
                
                $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
                echo "<p>Test record cleaned up.</p>";
            } else {
                echo "<p style='color: red;'>❌ Execute failed: " . mysqli_stmt_error($stmt) . "</p>";
            }
        }
        mysqli_stmt_close($stmt);
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
} catch (Error $e) {
    echo "<p style='color: red;'>❌ Fatal Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>




