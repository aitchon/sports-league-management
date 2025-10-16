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

echo "<h2>Test Foreign Key Constraints</h2>";

// Test 1: Check if season_id exists
echo "<h3>Test 1: Check if season_id exists</h3>";
$season_check = $conn->query("SELECT id FROM pickleball_seasons WHERE id = 1");
if ($season_check->num_rows > 0) {
    echo "<p style='color: green;'>✅ Season ID 1 exists</p>";
} else {
    echo "<p style='color: red;'>❌ Season ID 1 does not exist!</p>";
    echo "<p>Available seasons:</p>";
    $seasons = $conn->query("SELECT id, name FROM pickleball_seasons");
    while ($season = $seasons->fetch_assoc()) {
        echo "<p>ID: " . $season['id'] . ", Name: " . $season['name'] . "</p>";
    }
}

// Test 2: Try insert without season_id (set to NULL)
echo "<h3>Test 2: Insert without season_id</h3>";
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
    $season_id = null; // Set to NULL instead of 1
    $division_id = null;
    $notes = null;
    $status = 'scheduled';
    
    echo "<p><strong>Trying with season_id = NULL</strong></p>";
    
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

// Test 3: Try with a valid season_id
echo "<h3>Test 3: Insert with valid season_id</h3>";
try {
    // First, get a valid season_id
    $seasons = $conn->query("SELECT id FROM pickleball_seasons LIMIT 1");
    if ($seasons->num_rows > 0) {
        $season = $seasons->fetch_assoc();
        $valid_season_id = $season['id'];
        echo "<p><strong>Using season_id: $valid_season_id</strong></p>";
        
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
        $season_id = $valid_season_id;
        $division_id = null;
        $notes = null;
        $status = 'scheduled';
        
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
    } else {
        echo "<p style='color: red;'>❌ No seasons found in database!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

$conn->close();
?>



