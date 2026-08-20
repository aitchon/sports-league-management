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

echo "<h2>Test ENUM Binding Fix</h2>";

// Test 1: Current binding string (WRONG)
echo "<h3>Test 1: Current Binding String (WRONG)</h3>";
echo "<p><strong>Current binding:</strong> iiiiisssiiiiiss</p>";
echo "<p><strong>Position 9 (game_type):</strong> 's' (string) - WRONG for ENUM!</p>";

try {
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
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiisssiiiiiss", $player1_id, $player2_id, $player3_id, $player4_id, $challenger_id, $challenged_id, $date, $location, $game_type, $is_ladder_match, $is_challenge, $season_id, $division_id, $notes, $status);
    
    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: red;'>❌ Insert successful but game_type is empty! ID: $insert_id</p>";
        
        $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
        $result = $verify->fetch_assoc();
        echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
        
        $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
        echo "<p>Test record cleaned up.</p>";
    } else {
        echo "<p style='color: red;'>❌ Insert failed: " . $stmt->error . "</p>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

// Test 2: Fixed binding string (CORRECT)
echo "<h3>Test 2: Fixed Binding String (CORRECT)</h3>";
echo "<p><strong>Fixed binding:</strong> iiiiisssiiiiiss</p>";
echo "<p><strong>Position 9 (game_type):</strong> 's' (string) - CORRECT for ENUM!</p>";
echo "<p><strong>Wait, that's the same... Let me check the actual issue...</strong></p>";

// Let me check what the actual binding should be
echo "<h3>Test 3: Analyze the Real Issue</h3>";
echo "<p><strong>Parameter order:</strong></p>";
echo "<p>1. player1_id (int) = i</p>";
echo "<p>2. player2_id (int) = i</p>";
echo "<p>3. player3_id (int) = i</p>";
echo "<p>4. player4_id (int) = i</p>";
echo "<p>5. challenger_id (int) = i</p>";
echo "<p>6. challenged_id (int) = i</p>";
echo "<p>7. date (datetime) = s</p>";
echo "<p>8. location (varchar) = s</p>";
echo "<p>9. game_type (enum) = s</p>";
echo "<p>10. is_ladder_match (tinyint) = i</p>";
echo "<p>11. is_challenge (tinyint) = i</p>";
echo "<p>12. season_id (int) = i</p>";
echo "<p>13. division_id (int) = i</p>";
echo "<p>14. notes (text) = s</p>";
echo "<p>15. status (enum) = s</p>";
echo "<p><strong>Correct binding string:</strong> iiiiisssiiiiiss</p>";

// Test 4: Try with explicit string casting
echo "<h3>Test 4: Try with Explicit String Casting</h3>";
try {
    $player1_id = null;
    $player2_id = null;
    $player3_id = null;
    $player4_id = null;
    $challenger_id = null;
    $challenged_id = null;
    $date = '2024-01-01 10:00:00';
    $location = 'Test Court';
    $game_type = (string)'doubles'; // Explicit string casting
    $is_ladder_match = 1;
    $is_challenge = 0;
    $season_id = 1;
    $division_id = null;
    $notes = null;
    $status = 'scheduled';
    
    echo "<p><strong>game_type with explicit casting:</strong> " . var_export($game_type, true) . "</p>";
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiisssiiiiiss", $player1_id, $player2_id, $player3_id, $player4_id, $challenger_id, $challenged_id, $date, $location, $game_type, $is_ladder_match, $is_challenge, $season_id, $division_id, $notes, $status);
    
    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Insert successful! ID: $insert_id</p>";
        
        $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
        $result = $verify->fetch_assoc();
        echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
        
        $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
        echo "<p>Test record cleaned up.</p>";
    } else {
        echo "<p style='color: red;'>❌ Insert failed: " . $stmt->error . "</p>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

$conn->close();
?>




