<?php
// Simple diagnostic page for schedule issues
echo "<h1>Pickleball Schedule Diagnostic</h1>";

// Check if we can connect to database
echo "<h2>1. Database Connection Test</h2>";
try {
    include 'db.php';
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Check if pickleball_seasons table exists
    $result = $conn->query("SHOW TABLES LIKE 'pickleball_seasons'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✅ pickleball_seasons table exists</p>";
        
        // Check for active season
        $result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
        if ($result->num_rows > 0) {
            $season = $result->fetch_assoc();
            echo "<p style='color: green;'>✅ Active season found: " . htmlspecialchars($season['name']) . "</p>";
        } else {
            echo "<p style='color: red;'>❌ No active season found</p>";
            echo "<p><a href='manage_pickleball_seasons.php'>Create a season</a></p>";
        }
    } else {
        echo "<p style='color: red;'>❌ pickleball_seasons table does not exist</p>";
        echo "<p>You need to run the database setup first.</p>";
    }
    
    // Check if pickleball_games table exists
    $result = $conn->query("SHOW TABLES LIKE 'pickleball_games'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✅ pickleball_games table exists</p>";
        
        // Check table structure
        $result = $conn->query("DESCRIBE pickleball_games");
        echo "<h3>pickleball_games table structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check for games
        $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_games");
        $count = $result->fetch_assoc()['count'];
        echo "<p><strong>Total games in database:</strong> $count</p>";
        
        if ($count > 0) {
            echo "<h3>Sample games:</h3>";
            $result = $conn->query("SELECT id, date, location, game_type, status, player1_id, player2_id FROM pickleball_games LIMIT 5");
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Date</th><th>Location</th><th>Type</th><th>Status</th><th>Players</th></tr>";
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
                if (!$row['player1_id'] && !$row['player2_id']) echo "No players";
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'>❌ pickleball_games table does not exist</p>";
        echo "<p>You need to run the database setup first.</p>";
    }
    
    // Check if pickleball_players table exists
    $result = $conn->query("SHOW TABLES LIKE 'pickleball_players'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✅ pickleball_players table exists</p>";
        
        $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_players");
        $count = $result->fetch_assoc()['count'];
        echo "<p><strong>Total players in database:</strong> $count</p>";
    } else {
        echo "<p style='color: red;'>❌ pickleball_players table does not exist</p>";
    }
    
    // Check if pickleball_divisions table exists
    $result = $conn->query("SHOW TABLES LIKE 'pickleball_divisions'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✅ pickleball_divisions table exists</p>";
        
        $result = $conn->query("SELECT COUNT(*) as count FROM pickleball_divisions");
        $count = $result->fetch_assoc()['count'];
        echo "<p><strong>Total divisions in database:</strong> $count</p>";
    } else {
        echo "<p style='color: red;'>❌ pickleball_divisions table does not exist</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>2. Test Schedule Query</h2>";
try {
    if (isset($conn)) {
        $result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
        if ($result->num_rows > 0) {
            $season = $result->fetch_assoc();
            echo "<p>Testing query for season: " . htmlspecialchars($season['name']) . "</p>";
            
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
                $stmt->bind_param("i", $season['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $games = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                echo "<p style='color: green;'>✅ Query executed successfully</p>";
                echo "<p><strong>Found " . count($games) . " upcoming games</strong></p>";
                
                if (count($games) > 0) {
                    echo "<h3>Upcoming Games:</h3>";
                    echo "<table border='1' style='border-collapse: collapse;'>";
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
                } else {
                    echo "<p style='color: orange;'>⚠️ No upcoming games found</p>";
                    echo "<p>This could be because:</p>";
                    echo "<ul>";
                    echo "<li>No games have been created yet</li>";
                    echo "<li>All games are in the past</li>";
                    echo "<li>Games don't have the correct season_id</li>";
                    echo "</ul>";
                }
            } else {
                echo "<p style='color: red;'>❌ Query preparation failed: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ No active season found</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Query error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>3. Quick Fixes</h2>";
echo "<p>If you see issues above, try these:</p>";
echo "<ul>";
echo "<li><a href='pickleball_tables.sql' target='_blank'>Run Database Setup</a></li>";
echo "<li><a href='manage_pickleball_seasons.php'>Create a Season</a></li>";
echo "<li><a href='manage_pickleball_games.php'>Create Games</a></li>";
echo "<li><a href='pickleball_schedule.php'>View Schedule Page</a></li>";
echo "</ul>";
?>



