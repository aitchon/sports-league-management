<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $team_id = intval($_POST['team_id']);
    $position = $_POST['position'] ?? null;
    $jersey_number = !empty($_POST['jersey_number']) ? intval($_POST['jersey_number']) : null;
    $selected_season_id = isset($_SESSION['selected_volleyball_season_id']) ? $_SESSION['selected_volleyball_season_id'] : null;

    if ($name && $team_id) {
        if ($selected_season_id) {
            $stmt = $conn->prepare("INSERT INTO volleyball_players (name, team_id, season_id, position, jersey_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("siisi", $name, $team_id, $selected_season_id, $position, $jersey_number);
        } else {
            $stmt = $conn->prepare("INSERT INTO volleyball_players (name, team_id, position, jersey_number) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sisi", $name, $team_id, $position, $jersey_number);
        }
        
        $stmt->execute();
        $stmt->close();
    }
}
?>
