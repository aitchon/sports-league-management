<?php
include 'db.php';

if (isset($_GET['team_id'])) {
    $team_id = intval($_GET['team_id']);
    $stmt = $conn->prepare("SELECT id, name FROM players WHERE team_id = ?");
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $players = [];
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    
    echo json_encode($players);
}
?>
