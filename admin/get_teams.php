<?php
include 'db.php';

$division_id = $_GET['division_id'];

$teams = $conn->query("SELECT id, name FROM teams WHERE division_id = $division_id");
$teamsArray = [];

while ($team = $teams->fetch_assoc()) {
    $teamsArray[] = $team;
}

echo json_encode($teamsArray);
?>