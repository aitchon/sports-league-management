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

echo "<h2>Test Parameter Order and Binding</h2>";

// Test 1: Check the exact parameter order from the main form
echo "<h3>Test 1: Exact Main Form Parameter Order</h3>";
echo "<p><strong>Main form uses this order:</strong></p>";
echo "<p>player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status</p>";
echo "<p><strong>Binding string:</strong> iiiiisssiiiiiss</p>";

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
    
    echo "<p><strong>Parameter values:</strong></p>";
    echo "<p>1. player1_id: " . var_export($player1_id, true) . "</p>";
    echo "<p>2. player2_id: " . var_export($player2_id, true) . "</p>";
    echo "<p>3. player3_id: " . var_export($player3_id, true) . "</p>";
    echo "<p>4. player4_id: " . var_export($player4_id, true) . "</p>";
    echo "<p>5. challenger_id: " . var_export($challenger_id, true) . "</p>";
    echo "<p>6. challenged_id: " . var_export($challenged_id, true) . "</p>";
    echo "<p>7. date: " . var_export($date, true) . "</p>";
    echo "<p>8. location: " . var_export($location, true) . "</p>";
    echo "<p>9. game_type: " . var_export($game_type, true) . " ← THIS IS THE PROBLEM</p>";
    echo "<p>10. is_ladder_match: " . var_export($is_ladder_match, true) . "</p>";
    echo "<p>11. is_challenge: " . var_export($is_challenge, true) . "</p>";
    echo "<p>12. season_id: " . var_export($season_id, true) . "</p>";
    echo "<p>13. division_id: " . var_export($division_id, true) . "</p>";
    echo "<p>14. notes: " . var_export($notes, true) . "</p>";
    echo "<p>15. status: " . var_export($status, true) . "</p>";
    
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

// Test 2: Try with game_type in different positions
echo "<h3>Test 2: Try with game_type in different positions</h3>";

// Test 2a: game_type as first parameter
echo "<h4>Test 2a: game_type as first parameter</h4>";
try {
    $game_type = 'doubles';
    $date = '2024-01-01 10:00:00';
    $location = 'Test Court';
    $status = 'scheduled';
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (game_type, date, location, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $game_type, $date, $location, $status);
    
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

// Test 2b: game_type as last parameter
echo "<h4>Test 2b: game_type as last parameter</h4>";
try {
    $date = '2024-01-01 10:00:00';
    $location = 'Test Court';
    $status = 'scheduled';
    $game_type = 'doubles';
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, status, game_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $date, $location, $status, $game_type);
    
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

// Test 3: Check if there's a MySQL version issue
echo "<h3>Test 3: MySQL Version and Settings</h3>";
$version = $conn->query("SELECT VERSION()")->fetch_array()[0];
echo "<p><strong>MySQL Version:</strong> $version</p>";

$sql_mode = $conn->query("SELECT @@sql_mode")->fetch_array()[0];
echo "<p><strong>SQL Mode:</strong> $sql_mode</p>";

$conn->close();
?>

