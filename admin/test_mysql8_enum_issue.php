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

echo "<h2>Test MySQL 8.0.42 ENUM Issue</h2>";

// Test 1: Direct SQL insert (bypass prepared statements)
echo "<h3>Test 1: Direct SQL Insert (Bypass Prepared Statements)</h3>";
try {
    $sql = "INSERT INTO pickleball_games (date, location, game_type, status) VALUES ('2024-01-01 10:00:00', 'Test Court', 'doubles', 'scheduled')";
    echo "<p><strong>SQL:</strong> $sql</p>";
    
    if ($conn->query($sql)) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Direct insert successful! ID: $insert_id</p>";
        
        $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
        $result = $verify->fetch_assoc();
        echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
        
        $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
        echo "<p>Test record cleaned up.</p>";
    } else {
        echo "<p style='color: red;'>❌ Direct insert failed: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

// Test 2: Prepared statement with only non-NULL values
echo "<h3>Test 2: Prepared Statement with Only Non-NULL Values</h3>";
try {
    $date = '2024-01-01 10:00:00';
    $location = 'Test Court';
    $game_type = 'doubles';
    $status = 'scheduled';
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $date, $location, $game_type, $status);
    
    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Prepared statement successful! ID: $insert_id</p>";
        
        $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
        $result = $verify->fetch_assoc();
        echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
        
        $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
        echo "<p>Test record cleaned up.</p>";
    } else {
        echo "<p style='color: red;'>❌ Prepared statement failed: " . $stmt->error . "</p>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
}

// Test 3: Check if it's a NULL value issue
echo "<h3>Test 3: Check if NULL Values Cause the Issue</h3>";
try {
    $player1_id = 1; // Use a real value instead of NULL
    $player2_id = 2; // Use a real value instead of NULL
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
    
    echo "<p><strong>Using real player IDs instead of NULL</strong></p>";
    
    $stmt = $conn->prepare("INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiisssiiiiiss", $player1_id, $player2_id, $player3_id, $player4_id, $challenger_id, $challenged_id, $date, $location, $game_type, $is_ladder_match, $is_challenge, $season_id, $division_id, $notes, $status);
    
    if ($stmt->execute()) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>✅ Insert with real player IDs successful! ID: $insert_id</p>";
        
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

// Test 4: Check MySQL 8.0.42 specific settings
echo "<h3>Test 4: MySQL 8.0.42 Specific Settings</h3>";
$version = $conn->query("SELECT VERSION()")->fetch_array()[0];
echo "<p><strong>MySQL Version:</strong> $version</p>";

$sql_mode = $conn->query("SELECT @@sql_mode")->fetch_array()[0];
echo "<p><strong>SQL Mode:</strong> $sql_mode</p>";

$enum_handling = $conn->query("SELECT @@sql_mode LIKE '%STRICT_TRANS_TABLES%'")->fetch_array()[0];
echo "<p><strong>Strict Mode:</strong> " . ($enum_handling ? 'YES' : 'NO') . "</p>";

// Test 5: Try with different ENUM values
echo "<h3>Test 5: Try Different ENUM Values</h3>";
$enum_values = ['singles', 'doubles', 'mixed'];
foreach ($enum_values as $value) {
    try {
        $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", '2024-01-01 10:00:00', 'Test Court', $value, 'scheduled');
        
        if ($stmt->execute()) {
            $insert_id = $conn->insert_id;
            $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $insert_id");
            $result = $verify->fetch_assoc();
            echo "<p><strong>$value:</strong> Saved as '" . $result['game_type'] . "' " . ($result['game_type'] === $value ? "✅" : "❌") . "</p>";
            
            $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
        } else {
            echo "<p><strong>$value:</strong> Failed - " . $stmt->error . "</p>";
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<p><strong>$value:</strong> Exception - " . $e->getMessage() . "</p>";
    }
}

$conn->close();
?>

