<?php
// Test script to check pickleball database tables
include 'db.php';

echo "<h2>Pickleball Database Table Check</h2>";

// Check if pickleball_games table exists
$table_check = $conn->query("SHOW TABLES LIKE 'pickleball_games'");
if ($table_check->num_rows == 0) {
    echo "<p style='color: red;'>❌ pickleball_games table does NOT exist</p>";
} else {
    echo "<p style='color: green;'>✅ pickleball_games table exists</p>";
    
    // Check table structure
    $structure = $conn->query("DESCRIBE pickleball_games");
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if game_type column exists
    $columns = [];
    $structure->data_seek(0); // Reset pointer
    while ($row = $structure->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    if (in_array('game_type', $columns)) {
        echo "<p style='color: green;'>✅ game_type column exists</p>";
    } else {
        echo "<p style='color: red;'>❌ game_type column does NOT exist</p>";
        echo "<p>Available columns: " . implode(', ', $columns) . "</p>";
    }
}

// Check other pickleball tables
$tables = ['pickleball_seasons', 'pickleball_divisions', 'pickleball_players'];
foreach ($tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows == 0) {
        echo "<p style='color: red;'>❌ $table table does NOT exist</p>";
    } else {
        echo "<p style='color: green;'>✅ $table table exists</p>";
    }
}

// Test insert if table exists
if ($table_check->num_rows > 0) {
    echo "<h3>Testing Game Insert:</h3>";
    
    // Try to insert a test game
    $test_data = [
        'player1_id' => null,
        'player2_id' => null,
        'player3_id' => null,
        'player4_id' => null,
        'challenger_id' => null,
        'challenged_id' => null,
        'date' => '2024-01-01 10:00:00',
        'location' => 'Test Court',
        'game_type' => 'doubles',
        'is_ladder_match' => 1,
        'is_challenge' => 0,
        'season_id' => 1,
        'division_id' => null,
        'notes' => 'Test game',
        'status' => 'scheduled'
    ];
    
    try {
        $stmt = $conn->prepare("INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiisssiiiiiss", 
            $test_data['player1_id'], 
            $test_data['player2_id'], 
            $test_data['player3_id'], 
            $test_data['player4_id'], 
            $test_data['challenger_id'], 
            $test_data['challenged_id'], 
            $test_data['date'], 
            $test_data['location'], 
            $test_data['game_type'], 
            $test_data['is_ladder_match'], 
            $test_data['is_challenge'], 
            $test_data['season_id'], 
            $test_data['division_id'], 
            $test_data['notes'], 
            $test_data['status']
        );
        
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ Test game insert successful! Game Type: " . $test_data['game_type'] . "</p>";
            
            // Get the inserted game to verify
            $last_id = $conn->insert_id;
            $verify = $conn->query("SELECT * FROM pickleball_games WHERE id = $last_id");
            $game = $verify->fetch_assoc();
            echo "<p>Inserted game details:</p>";
            echo "<pre>" . print_r($game, true) . "</pre>";
            
            // Clean up test data
            $conn->query("DELETE FROM pickleball_games WHERE id = $last_id");
            echo "<p>Test data cleaned up.</p>";
        } else {
            echo "<p style='color: red;'>❌ Test game insert failed: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Test game insert error: " . $e->getMessage() . "</p>";
    }
}

$conn->close();
?>

