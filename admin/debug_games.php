<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

echo "<h2>Debug: Pickleball Games</h2>";

// Check if tables exist
$tables = ['pickleball_seasons', 'pickleball_games', 'pickleball_players'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p>✅ Table '$table' exists</p>";
    } else {
        echo "<p>❌ Table '$table' does not exist</p>";
    }
}

// Check seasons
echo "<h3>Seasons:</h3>";
$result = $conn->query("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<p>Season {$row['id']}: {$row['name']} (Active: " . ($row['is_active'] ? 'Yes' : 'No') . ")</p>";
    }
} else {
    echo "<p>No seasons found</p>";
}

// Check players
echo "<h3>Players:</h3>";
$result = $conn->query("SELECT * FROM pickleball_players ORDER BY name");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<p>Player {$row['id']}: {$row['name']} (Season: {$row['season_id']}, Active: " . ($row['is_active'] ? 'Yes' : 'No') . ")</p>";
    }
} else {
    echo "<p>No players found</p>";
}

// Check games
echo "<h3>Games:</h3>";
$result = $conn->query("SELECT * FROM pickleball_games ORDER BY date DESC");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<p>Game {$row['id']}: {$row['date']} at {$row['location']} - Status: {$row['status']} - Type: {$row['game_type']}</p>";
        echo "<p>&nbsp;&nbsp;Players: {$row['player1_id']}, {$row['player2_id']}, {$row['player3_id']}, {$row['player4_id']}</p>";
    }
} else {
    echo "<p>No games found</p>";
}

// Check games with player names
echo "<h3>Games with Player Names:</h3>";
$result = $conn->query("
    SELECT pg.*, p1.name as player1_name, p2.name as player2_name,
           p3.name as player3_name, p4.name as player4_name
    FROM pickleball_games pg
    LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
    LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
    LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
    LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
    ORDER BY pg.date DESC
");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<p>Game {$row['id']}: {$row['date']} at {$row['location']}</p>";
        echo "<p>&nbsp;&nbsp;Players: {$row['player1_name']}, {$row['player2_name']}, {$row['player3_name']}, {$row['player4_name']}</p>";
        echo "<p>&nbsp;&nbsp;Status: {$row['status']} - Type: {$row['game_type']}</p><hr>";
    }
} else {
    echo "<p>No games with player names found</p>";
}
?>



