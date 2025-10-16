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

echo "<h2>Debug Database Issue</h2>";

// Test 1: Check if there are any triggers on the table
echo "<h3>Test 1: Check for Triggers</h3>";
$triggers = $conn->query("SHOW TRIGGERS LIKE 'pickleball_games'");
if ($triggers->num_rows > 0) {
    echo "<p><strong>Triggers found:</strong></p>";
    while ($trigger = $triggers->fetch_assoc()) {
        echo "<pre>" . print_r($trigger, true) . "</pre>";
    }
} else {
    echo "<p>No triggers found on pickleball_games table.</p>";
}

// Test 2: Check table constraints and indexes
echo "<h3>Test 2: Table Constraints</h3>";
$constraints = $conn->query("SHOW CREATE TABLE pickleball_games");
$create_table = $constraints->fetch_assoc();
echo "<pre>" . htmlspecialchars($create_table['Create Table']) . "</pre>";

// Test 3: Try inserting with a different game_type value
echo "<h3>Test 3: Try Different Game Type</h3>";
try {
    $player1_id = null;
    $player2_id = null;
    $player3_id = null;
    $player4_id = null;
    $challenger_id = null;
    $challenged_id = null;
    $date = '2024-01-01 10:00:00';
    $location = 'Test Court';
    $game_type = 'singles'; // Try singles instead
    $is_ladder_match = 1;
    $is_challenge = 0;
    $season_id = 1;
    $division_id = null;
    $notes = null;
    $status = 'scheduled';
    
    echo "<p><strong>Trying with game_type: 'singles'</strong></p>";
    
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

// Test 4: Check if the issue is with the parameter order
echo "<h3>Test 4: Check Parameter Order</h3>";
try {
    // Try with a different parameter order - put game_type first
    $game_type = 'mixed';
    $date = '2024-01-01 10:00:00';
    $location = 'Test Court';
    $status = 'scheduled';
    $season_id = 1;
    $is_ladder_match = 1;
    $is_challenge = 0;
    $player1_id = null;
    $player2_id = null;
    $player3_id = null;
    $player4_id = null;
    $challenger_id = null;
    $challenged_id = null;
    $division_id = null;
    $notes = null;
    
    echo "<p><strong>Trying with game_type: 'mixed' (different order)</strong></p>";
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (game_type, date, location, status, season_id, is_ladder_match, is_challenge, player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, division_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiiiiiiiiis", $game_type, $date, $location, $status, $season_id, $is_ladder_match, $is_challenge, $player1_id, $player2_id, $player3_id, $player4_id, $challenger_id, $challenged_id, $division_id, $notes);
    
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



