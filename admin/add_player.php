<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $team_id = intval($_POST['team_id']);
    $selected_season_id = isset($_SESSION['selected_season_id']) ? $_SESSION['selected_season_id'] : ($current_season ? $current_season['id'] : null);

    if ($name && $team_id) {
        // Check if season_id column exists in players table
        $checkColumn = $conn->query("SHOW COLUMNS FROM players LIKE 'season_id'");
        $hasSeasonId = $checkColumn && $checkColumn->num_rows > 0;
        
        if ($hasSeasonId && $selected_season_id) {
            $stmt = $conn->prepare("INSERT INTO players (name, team_id, season_id) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $name, $team_id, $selected_season_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO players (name, team_id) VALUES (?, ?)");
            $stmt->bind_param("si", $name, $team_id);
        }
        
        $stmt->execute();
        $stmt->close();
    }
}
?>
