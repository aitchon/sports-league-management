<?php
// Test MySQL connection with XAMPP default credentials
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'basketball_league';

echo "<h2>Testing MySQL Connection</h2>";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        echo "<p style='color: red;'>❌ Connection failed: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>✅ MySQL connection successful!</p>";
        
        // Test if volleyball tables exist
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
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
