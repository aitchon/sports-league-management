<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'];
    $datetime = $_POST['datetime'];
    $location = $_POST['location'];
    $home_team_id = $_POST['home_team_id'];
    $away_team_id = $_POST['away_team_id'];
    $division_id = $_POST['division_id'];

    // Format the datetime to MySQL format
    $formatted_datetime = date('Y-m-d H:i:s', strtotime($datetime));

    // Update the game in the database
    $stmt = $conn->prepare("UPDATE games SET date = ?, location = ?, home_team_id = ?, away_team_id = ?, status = 'scheduled' WHERE id = ?");
    $stmt->bind_param("ssiii", $formatted_datetime, $location, $home_team_id, $away_team_id, $game_id);
    $stmt->execute();
    $stmt->close();

    // Redirect to the games management page after update
    header("Location: manage_games.php");
    exit();
}
?>
