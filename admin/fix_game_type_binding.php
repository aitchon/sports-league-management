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

echo "<h2>Fix Game Type Binding Issue</h2>";

// Test the fix: Use separate queries for different parameter combinations
echo "<h3>Test Fix: Conditional Binding Based on NULL Values</h3>";

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
    
    // Count non-NULL parameters
    $non_null_count = 0;
    $params = [];
    $types = "";
    
    if ($player1_id !== null) { $params[] = $player1_id; $types .= "i"; $non_null_count++; }
    if ($player2_id !== null) { $params[] = $player2_id; $types .= "i"; $non_null_count++; }
    if ($player3_id !== null) { $params[] = $player3_id; $types .= "i"; $non_null_count++; }
    if ($player4_id !== null) { $params[] = $player4_id; $types .= "i"; $non_null_count++; }
    if ($challenger_id !== null) { $params[] = $challenger_id; $types .= "i"; $non_null_count++; }
    if ($challenged_id !== null) { $params[] = $challenged_id; $types .= "i"; $non_null_count++; }
    
    // Always include these (non-NULL)
    $params[] = $date; $types .= "s";
    $params[] = $location; $types .= "s";
    $params[] = $game_type; $types .= "s";
    $params[] = $is_ladder_match; $types .= "i";
    $params[] = $is_challenge; $types .= "i";
    $params[] = $season_id; $types .= "i";
    
    if ($division_id !== null) { $params[] = $division_id; $types .= "i"; $non_null_count++; }
    if ($notes !== null) { $params[] = $notes; $types .= "s"; $non_null_count++; }
    
    $params[] = $status; $types .= "s";
    
    echo "<p><strong>Non-NULL parameter count:</strong> $non_null_count</p>";
    echo "<p><strong>Binding string:</strong> $types</p>";
    echo "<p><strong>Parameter count:</strong> " . count($params) . "</p>";
    
    // Build dynamic SQL
    $sql = "INSERT INTO pickleball_games (";
    $placeholders = "(";
    
    if ($player1_id !== null) { $sql .= "player1_id, "; $placeholders .= "?, "; }
    if ($player2_id !== null) { $sql .= "player2_id, "; $placeholders .= "?, "; }
    if ($player3_id !== null) { $sql .= "player3_id, "; $placeholders .= "?, "; }
    if ($player4_id !== null) { $sql .= "player4_id, "; $placeholders .= "?, "; }
    if ($challenger_id !== null) { $sql .= "challenger_id, "; $placeholders .= "?, "; }
    if ($challenged_id !== null) { $sql .= "challenged_id, "; $placeholders .= "?, "; }
    
    $sql .= "date, location, game_type, is_ladder_match, is_challenge, season_id, ";
    $placeholders .= "?, ?, ?, ?, ?, ?, ";
    
    if ($division_id !== null) { $sql .= "division_id, "; $placeholders .= "?, "; }
    if ($notes !== null) { $sql .= "notes, "; $placeholders .= "?, "; }
    
    $sql .= "status) VALUES ";
    $placeholders .= "?)";
    
    $sql .= $placeholders;
    
    echo "<p><strong>Dynamic SQL:</strong> $sql</p>";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Dynamic binding successful! ID: $insert_id</p>";
        
        $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
        $result = $verify->fetch_assoc();
        echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
        
        $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
        echo "<p>Test record cleaned up.</p>";
    } else {
        echo "<p style='color: red;'>❌ Dynamic binding failed: " . $stmt->error . "</p>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

// Test 2: Simpler approach - Use default values instead of NULL
echo "<h3>Test 2: Use Default Values Instead of NULL</h3>";

try {
    // Use 0 instead of NULL for integer fields
    $player1_id = 0; // Instead of null
    $player2_id = 0; // Instead of null
    $player3_id = 0; // Instead of null
    $player4_id = 0; // Instead of null
    $challenger_id = 0; // Instead of null
    $challenged_id = 0; // Instead of null
    $date = '2024-01-01 10:00:00';
    $location = 'Test Court';
    $game_type = 'doubles';
    $is_ladder_match = 1;
    $is_challenge = 0;
    $season_id = 1;
    $division_id = 0; // Instead of null
    $notes = ''; // Instead of null
    $status = 'scheduled';
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiisssiiiiiss", $player1_id, $player2_id, $player3_id, $player4_id, $challenger_id, $challenged_id, $date, $location, $game_type, $is_ladder_match, $is_challenge, $season_id, $division_id, $notes, $status);
    
    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Default values approach successful! ID: $insert_id</p>";
        
        $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
        $result = $verify->fetch_assoc();
        echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
        
        $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
        echo "<p>Test record cleaned up.</p>";
    } else {
        echo "<p style='color: red;'>❌ Default values approach failed: " . $stmt->error . "</p>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

$conn->close();
?>

