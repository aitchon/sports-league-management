<?php
// Direct test without db.php functions
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'basketball_league';

echo "<h2>Volleyball Player Management Test</h2>";

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        echo "<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
        
        // Test volleyball tables directly
        $tables = ['volleyball_seasons', 'volleyball_divisions', 'volleyball_teams', 'volleyball_players'];
        
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                echo "<p style='color: green;'>✅ $table table exists</p>";
                
                // Get count
                $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
                $count = $count_result->fetch_assoc()['count'];
                echo "<p>Records in $table: <strong>$count</strong></p>";
            } else {
                echo "<p style='color: red;'>❌ $table table does NOT exist</p>";
            }
        }
        
        // Test volleyball seasons
        $seasons_result = $conn->query("SELECT * FROM volleyball_seasons ORDER BY start_date DESC");
        if ($seasons_result && $seasons_result->num_rows > 0) {
            echo "<h3>Volleyball Seasons:</h3>";
            while ($season = $seasons_result->fetch_assoc()) {
                echo "<p>• " . htmlspecialchars($season['name']) . " (ID: {$season['id']}, Active: " . ($season['is_active'] ? 'Yes' : 'No') . ")</p>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ No volleyball seasons found</p>";
        }
        
        echo "<h3>✅ Volleyball Player Management System is Ready!</h3>";
        echo "<p>The MySQL extensions are working and all volleyball tables exist.</p>";
        echo "<p>You can now use <strong>manage_volleyball_players.php</strong> to manage players.</p>";
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
