<?php
include 'db.php';

echo "<h1>Debug Pickleball Schedule</h1>";

// Check if current season exists
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
    echo "<h2>Current Season Found:</h2>";
    echo "<pre>" . print_r($current_season, true) . "</pre>";
} else {
    echo "<h2>No Active Season Found</h2>";
}

// Check if pickleball_games table exists and has data
$result = $conn->query("SHOW TABLES LIKE 'pickleball_games'");
if ($result->num_rows > 0) {
    echo "<h2>pickleball_games table exists</h2>";
    
    // Check table structure
    $result = $conn->query("DESCRIBE pickleball_games");
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
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
    
    // Check for games
    $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_games");
    $count = $result->fetch_assoc()['count'];
    echo "<h3>Total Games: $count</h3>";
    
    if ($count > 0) {
        $result = $conn->query("SELECT * FROM pickleball_games LIMIT 5");
        echo "<h3>Sample Games:</h3>";
        echo "<pre>" . print_r($result->fetch_all(MYSQLI_ASSOC), true) . "</pre>";
    }
} else {
    echo "<h2>pickleball_games table does NOT exist</h2>";
}

// Check if pickleball_players table exists
$result = $conn->query("SHOW TABLES LIKE 'pickleball_players'");
if ($result->num_rows > 0) {
    echo "<h2>pickleball_players table exists</h2>";
    $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_players");
    $count = $result->fetch_assoc()['count'];
    echo "<h3>Total Players: $count</h3>";
} else {
    echo "<h2>pickleball_players table does NOT exist</h2>";
}

// Check if pickleball_divisions table exists
$result = $conn->query("SHOW TABLES LIKE 'pickleball_divisions'");
if ($result->num_rows > 0) {
    echo "<h2>pickleball_divisions table exists</h2>";
    $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_divisions");
    $count = $result->fetch_assoc()['count'];
    echo "<h3>Total Divisions: $count</h3>";
} else {
    echo "<h2>pickleball_divisions table does NOT exist</h2>";
}

// Test the actual query from schedule.php
if ($current_season) {
    echo "<h2>Testing Schedule Query</h2>";
    try {
        $query = "
            SELECT pg.id, DATE(pg.date) AS game_date, TIME(pg.date) AS start_time, 
                   pg.location, pg.is_playoff, pg.game_type, pg.is_challenge,
                   p1.name AS player1_name, p2.name AS player2_name, 
                   p3.name AS player3_name, p4.name AS player4_name,
                   pg.status, 
                   pg.match1_team1_score, pg.match1_team2_score,
                   pg.match2_team1_score, pg.match2_team2_score,
                   pg.match3_team1_score, pg.match3_team2_score,
                   d.name AS division_name, d.id AS division_id,
                   pg.player1_id, pg.player2_id, pg.player3_id, pg.player4_id
            FROM pickleball_games pg
            LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
            LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
            LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
            LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
            LEFT JOIN pickleball_divisions d ON pg.division_id = d.id
            WHERE DATE(pg.date) >= CURDATE() AND pg.season_id = ?
            ORDER BY game_date, pg.location, start_time
        ";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $current_season['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $games = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            echo "<h3>Query Results:</h3>";
            echo "<p>Found " . count($games) . " games</p>";
            if (count($games) > 0) {
                echo "<pre>" . print_r($games, true) . "</pre>";
            }
        } else {
            echo "<h3>Query Preparation Failed:</h3>";
            echo "<p>Error: " . $conn->error . "</p>";
        }
    } catch (Exception $e) {
        echo "<h3>Query Error:</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
    }
}
?>

