<?php
session_start();
include 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

$season_id = isset($_GET['season_id']) ? intval($_GET['season_id']) : null;

if ($season_id) {
    $stmt = $conn->prepare("SELECT id, name FROM volleyball_divisions WHERE season_id = ? ORDER BY name");
    $stmt->bind_param("i", $season_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query("SELECT id, name FROM volleyball_divisions ORDER BY name");
}

$divisions = [];
while ($row = $result->fetch_assoc()) {
    $divisions[] = $row;
}

echo json_encode($divisions);
?>
