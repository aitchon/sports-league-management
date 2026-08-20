<?php
session_start();
include 'db_pdo.php';

// Set content type to JSON
header('Content-Type: application/json');

$division_id = isset($_GET['division_id']) ? intval($_GET['division_id']) : null;
$selected_season_id = isset($_SESSION['selected_volleyball_season_id']) ? $_SESSION['selected_volleyball_season_id'] : null;

if ($division_id && $selected_season_id) {
    $stmt = $pdo->prepare("SELECT id, name, is_playoff_placeholder FROM volleyball_teams WHERE division_id = ? AND season_id = ? AND is_playoff_placeholder = 0 ORDER BY name");
    $stmt->execute([$division_id, $selected_season_id]);
    $teamsArray = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id, name, is_playoff_placeholder FROM volleyball_teams WHERE division_id = ? AND is_playoff_placeholder = 0 ORDER BY name");
    $stmt->execute([$division_id]);
    $teamsArray = $stmt->fetchAll();
}

echo json_encode($teamsArray);
?>
