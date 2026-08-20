<?php
session_start();
include 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

if (isset($_GET['team_id'])) {
    $team_id = intval($_GET['team_id']);
    $selected_season_id = isset($_SESSION['selected_season_id']) ? $_SESSION['selected_season_id'] : ($current_season ? $current_season['id'] : null);
    
    // Check if season_id column exists in players table
    $checkColumn = $conn->query("SHOW COLUMNS FROM players LIKE 'season_id'");
    $hasSeasonId = $checkColumn && $checkColumn->num_rows > 0;
    
    if ($hasSeasonId && $selected_season_id) {
        $stmt = $conn->prepare("SELECT id, name FROM players WHERE team_id = ? AND season_id = ? ORDER BY name");
        $stmt->bind_param("ii", $team_id, $selected_season_id);
    } else {
        $stmt = $conn->prepare("SELECT id, name FROM players WHERE team_id = ? ORDER BY name");
        $stmt->bind_param("i", $team_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $players = [];
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    
    echo json_encode($players);
    $stmt->close();
} else {
    echo json_encode(['error' => 'No team_id provided']);
}
?>
