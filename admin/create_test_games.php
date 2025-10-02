<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Create Test Games</h2>";

// Check if we have a season
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
if ($result->num_rows == 0) {
    echo "<p>No active season found. Creating one...</p>";
    $stmt = $conn->prepare("INSERT INTO pickleball_seasons (name, start_date, end_date, ladder_type, is_active) VALUES (?, ?, ?, ?, ?)");
    $season_name = "Test Season " . date('Y');
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+3 months'));
    $ladder_type = 'singles';
    $is_active = 1;
    $stmt->bind_param("ssssi", $season_name, $start_date, $end_date, $ladder_type, $is_active);
    $stmt->execute();
    $season_id = $conn->insert_id;
    $stmt->close();
    echo "<p>✅ Created season with ID: $season_id</p>";
} else {
    $season = $result->fetch_assoc();
    $season_id = $season['id'];
    echo "<p>Using existing season: {$season['name']} (ID: $season_id)</p>";
}

// Check if we have players
$result = $conn->query("SELECT * FROM pickleball_players WHERE season_id = $season_id");
if ($result->num_rows == 0) {
    echo "<p>No players found. Creating test players...</p>";
    $players = ['John Smith', 'Jane Doe', 'Mike Johnson', 'Sarah Wilson'];
    foreach ($players as $player_name) {
        $stmt = $conn->prepare("INSERT INTO pickleball_players (name, season_id, is_active) VALUES (?, ?, ?)");
        $is_active = 1;
        $stmt->bind_param("sii", $player_name, $season_id, $is_active);
        $stmt->execute();
        $stmt->close();
    }
    echo "<p>✅ Created " . count($players) . " test players</p>";
} else {
    echo "<p>Found " . $result->num_rows . " existing players</p>";
}

// Get player IDs
$result = $conn->query("SELECT id, name FROM pickleball_players WHERE season_id = $season_id ORDER BY name");
$players = [];
while ($row = $result->fetch_assoc()) {
    $players[] = $row;
}

if (count($players) >= 2) {
    // Create test games
    echo "<p>Creating test games...</p>";
    
    $test_games = [
        [
            'player1_id' => $players[0]['id'],
            'player2_id' => $players[1]['id'],
            'player3_id' => $players[2]['id'],
            'player4_id' => $players[3]['id'],
            'date' => date('Y-m-d H:i:s', strtotime('+1 day 10:00')),
            'location' => 'Court 1',
            'game_type' => 'doubles',
            'status' => 'scheduled'
        ]
    ];
    
    foreach ($test_games as $game) {
        $stmt = $conn->prepare("
            INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, date, location, game_type, status, season_id, is_ladder_match, is_challenge) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $is_ladder_match = 1;
        $is_challenge = 0;
        $stmt->bind_param("iiiissssiii", 
            $game['player1_id'], $game['player2_id'], $game['player3_id'], $game['player4_id'], 
            $game['date'], $game['location'], $game['game_type'], $game['status'], 
            $season_id, $is_ladder_match, $is_challenge
        );
        $stmt->execute();
        $stmt->close();
    }
    
    echo "<p>✅ Created " . count($test_games) . " test games</p>";
    echo "<p><a href='enter_pickleball_scores.php'>Go to Score Entry</a></p>";
} else {
    echo "<p>❌ Need at least 4 players to create doubles games</p>";
}
?>
