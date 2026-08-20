<?php
// Test PDO connection
include 'db_pdo.php';

echo "<h2>PDO Database Connection Test</h2>";

try {
    // Test basic connection
    echo "<p style='color: green;'>✅ PDO connection successful!</p>";
    
    // Test volleyball tables
    $tables = ['volleyball_seasons', 'volleyball_divisions', 'volleyball_teams', 'volleyball_players'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $result = $stmt->fetch();
        
        if ($result) {
            echo "<p style='color: green;'>✅ $table table exists</p>";
            
            // Get count
            $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
            $count_stmt->execute();
            $count = $count_stmt->fetch()['count'];
            echo "<p>Records in $table: <strong>$count</strong></p>";
        } else {
            echo "<p style='color: red;'>❌ $table table does NOT exist</p>";
        }
    }
    
    // Test volleyball seasons
    $stmt = $pdo->prepare("SELECT * FROM volleyball_seasons ORDER BY start_date DESC");
    $stmt->execute();
    $seasons = $stmt->fetchAll();
    
    echo "<h3>Volleyball Seasons:</h3>";
    if (count($seasons) > 0) {
        foreach ($seasons as $season) {
            echo "<p>• " . htmlspecialchars($season['name']) . " (ID: {$season['id']}, Active: " . ($season['is_active'] ? 'Yes' : 'No') . ")</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ No volleyball seasons found</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ PDO Error: " . $e->getMessage() . "</p>";
}
?>
