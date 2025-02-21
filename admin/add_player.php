<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $team_id = intval($_POST['team_id']);

    $stmt = $conn->prepare("INSERT INTO players (name, team_id) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $team_id);
    $stmt->execute();
    $stmt->close();
}
?>
