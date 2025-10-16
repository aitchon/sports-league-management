<?php
include 'db.php';

echo "<h1>Test Pickleball Schedule</h1>";

// Check if current season exists
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
    echo "<h2>✅ Current Season Found:</h2>";
    echo "<p><strong>Name:</strong> " . htmlspecialchars($current_season['name']) . "</p>";
    echo "<p><strong>ID:</strong> " . $current_season['id'] . "</p>";
} else {
    echo "<h2>❌ No Active Season Found</h2>";
    echo "<p>You need to create a pickleball season first.</p>";
    echo "<p><a href='manage_pickleball_seasons.php' class='btn btn-primary'>Create Season</a></p>";
    exit();
}

// Check if pickleball_games table exists
$result = $conn->query("SHOW TABLES LIKE 'pickleball_games'");
if ($result->num_rows > 0) {
    echo "<h2>✅ pickleball_games table exists</h2>";
    
    // Check for games
    $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_games WHERE season_id = " . $current_season['id']);
    $count = $result->fetch_assoc()['count'];
    echo "<p><strong>Games in current season:</strong> $count</p>";
    
    if ($count > 0) {
        echo "<h3>Sample Games:</h3>";
        $result = $conn->query("SELECT * FROM pickleball_games WHERE season_id = " . $current_season['id'] . " LIMIT 3");
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Date</th><th>Location</th><th>Game Type</th><th>Status</th><th>Players</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['date'] . "</td>";
            echo "<td>" . htmlspecialchars($row['location']) . "</td>";
            echo "<td>" . $row['game_type'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>";
            if ($row['player1_id']) echo "P1:" . $row['player1_id'] . " ";
            if ($row['player2_id']) echo "P2:" . $row['player2_id'] . " ";
            if ($row['player3_id']) echo "P3:" . $row['player3_id'] . " ";
            if ($row['player4_id']) echo "P4:" . $row['player4_id'] . " ";
            if (!$row['player1_id'] && !$row['player2_id'] && !$row['player3_id'] && !$row['player4_id']) echo "No players assigned";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No games found. <a href='manage_pickleball_games.php' class='btn btn-primary'>Create Games</a></p>";
    }
} else {
    echo "<h2>❌ pickleball_games table does NOT exist</h2>";
    echo "<p>You need to run the database setup first.</p>";
    echo "<p><a href='pickleball_tables.sql' class='btn btn-primary'>Run SQL Setup</a></p>";
}

// Test the actual schedule query
echo "<h2>Testing Schedule Query</h2>";
try {
    $query = "
        SELECT pg.id, DATE(pg.date) AS game_date, TIME(pg.date) AS start_time, 
               pg.location, pg.game_type, pg.status,
               p1.name AS player1_name, p2.name AS player2_name, 
               p3.name AS player3_name, p4.name AS player4_name
        FROM pickleball_games pg
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pg.player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pg.player3_id = p3.id
        LEFT JOIN pickleball_players p4 ON pg.player4_id = p4.id
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
        
        echo "<p><strong>✅ Query successful!</strong> Found " . count($games) . " upcoming games</p>";
        
        if (count($games) > 0) {
            echo "<h3>Upcoming Games:</h3>";
            echo "<table border='1'>";
            echo "<tr><th>Date</th><th>Time</th><th>Location</th><th>Type</th><th>Players</th><th>Status</th></tr>";
            foreach ($games as $game) {
                echo "<tr>";
                echo "<td>" . $game['game_date'] . "</td>";
                echo "<td>" . $game['start_time'] . "</td>";
                echo "<td>" . htmlspecialchars($game['location']) . "</td>";
                echo "<td>" . $game['game_type'] . "</td>";
                echo "<td>";
                if ($game['player1_name']) echo $game['player1_name'] . " ";
                if ($game['player2_name']) echo "vs " . $game['player2_name'] . " ";
                if ($game['player3_name']) echo "& " . $game['player3_name'] . " ";
                if ($game['player4_name']) echo "vs " . $game['player4_name'] . " ";
                if (!$game['player1_name'] && !$game['player2_name']) echo "TBD";
                echo "</td>";
                echo "<td>" . $game['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p><strong>❌ Query preparation failed:</strong> " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p><strong>❌ Query error:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='pickleball_schedule.php' class='btn btn-success'>View Schedule Page</a></p>";
echo "<p><a href='pickleball_dashboard.php' class='btn btn-primary'>Back to Dashboard</a></p>";
?>



