<?php
session_start();

// Direct database connection to avoid db.php issues
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'basketball_league';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

$division_id = isset($_GET['division_id']) ? intval($_GET['division_id']) : null;
$selected_season_id = isset($_SESSION['selected_volleyball_season_id']) ? $_SESSION['selected_volleyball_season_id'] : 1; // Default to season 1

$teamsArray = [];

if ($division_id) {
    try {
        if ($selected_season_id) {
            $stmt = $conn->prepare("SELECT id, name, is_playoff_placeholder FROM volleyball_teams WHERE division_id = ? AND season_id = ? AND is_playoff_placeholder = 0 ORDER BY name");
            $stmt->bind_param("ii", $division_id, $selected_season_id);
        } else {
            $stmt = $conn->prepare("SELECT id, name, is_playoff_placeholder FROM volleyball_teams WHERE division_id = ? AND is_playoff_placeholder = 0 ORDER BY name");
            $stmt->bind_param("i", $division_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($team = $result->fetch_assoc()) {
            $teamsArray[] = $team;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        $conn->close();
        exit();
    }
}

echo json_encode($teamsArray);
$conn->close();
?>