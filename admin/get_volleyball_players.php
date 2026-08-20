<?php
session_start();
include 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

if (isset($_GET['team_id'])) {
    $team_id = intval($_GET['team_id']);
    $selected_season_id = isset($_SESSION['selected_volleyball_season_id']) ? $_SESSION['selected_volleyball_season_id'] : 1;
    
    if ($selected_season_id) {
        $stmt = $conn->prepare("SELECT id, name, position, jersey_number FROM volleyball_players WHERE team_id = ? AND season_id = ? ORDER BY name");
        $stmt->bind_param("ii", $team_id, $selected_season_id);
    } else {
        $stmt = $conn->prepare("SELECT id, name, position, jersey_number FROM volleyball_players WHERE team_id = ? ORDER BY name");
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
