<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>PHP 8 Fix Test</h2>";

// Test with the exact same approach as the main form
echo "<h3>Test: Exact Main Form Approach</h3>";
try {
    // Use the exact same variables as the main form
    $player1_id = null;
    $player2_id = null;
    $player3_id = null;
    $player4_id = null;
    $challenger_id = null;
    $challenged_id = null;
    $date = '2024-01-01 10:00:00';
    $location = 'Test Court';
    $game_type = 'doubles';
    $is_ladder_match = 1;
    $is_challenge = 0;
    $season_id = 1;
    $division_id = null;
    $notes = null;
    $status = 'scheduled';
    
    echo "<p><strong>Variables prepared:</strong></p>";
    echo "<p>game_type: '" . $game_type . "'</p>";
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Prepare successful</p>";
        
        $bind_result = $stmt->bind_param("iiiiisssiiiiiss", $player1_id, $player2_id, $player3_id, $player4_id, $challenger_id, $challenged_id, $date, $location, $game_type, $is_ladder_match, $is_challenge, $season_id, $division_id, $notes, $status);
        
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
} catch (Error $e) {
    echo "<p style='color: red;'>❌ Fatal Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>




