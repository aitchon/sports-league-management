<?php
session_start();
include 'db_pdo.php';

// Set content type to JSON
header('Content-Type: application/json');

if (isset($_GET['team_id'])) {
    $team_id = intval($_GET['team_id']);
    $selected_season_id = isset($_SESSION['selected_volleyball_season_id']) ? $_SESSION['selected_volleyball_season_id'] : null;
    
    if ($selected_season_id) {
        $stmt = $pdo->prepare("SELECT id, name, position, jersey_number FROM volleyball_players WHERE team_id = ? AND season_id = ? ORDER BY name");
        $stmt->execute([$team_id, $selected_season_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, position, jersey_number FROM volleyball_players WHERE team_id = ? ORDER BY name");
        $stmt->execute([$team_id]);
    }
    
    $players = $stmt->fetchAll();
    echo json_encode($players);
} else {
    echo json_encode(['error' => 'No team_id provided']);
}
?>
