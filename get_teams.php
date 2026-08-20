<?php
session_start();
include 'db.php';

$division_id = isset($_GET['division_id']) ? intval($_GET['division_id']) : null;
$selected_season_id = isset($_SESSION['selected_season_id']) ? $_SESSION['selected_season_id'] : ($current_season ? $current_season['id'] : null);

if ($division_id && $selected_season_id) {
    $stmt = $conn->prepare("SELECT id, name, is_playoff_placeholder FROM teams WHERE division_id = ? AND season_id = ? AND is_playoff_placeholder = 0 ORDER BY name");
    $stmt->bind_param("ii", $division_id, $selected_season_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query("SELECT id, name, is_playoff_placeholder FROM teams WHERE division_id = $division_id AND is_playoff_placeholder = 0 ORDER BY name");
}

$teamsArray = [];

while ($team = $result->fetch_assoc()) {
    $teamsArray[] = $team;
}

echo json_encode($teamsArray);
?>