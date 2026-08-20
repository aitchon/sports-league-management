<?php
// Fixed version of get_volleyball_players.php with better error handling
session_start();

// Database connection with error handling
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

if (isset($_GET['team_id'])) {
    $team_id = intval($_GET['team_id']);
    $selected_season_id = isset($_SESSION['selected_volleyball_season_id']) ? $_SESSION['selected_volleyball_season_id'] : 1;
    
    try {
        if ($selected_season_id) {
            $stmt = $conn->prepare("SELECT id, name, position, jersey_number FROM volleyball_players WHERE team_id = ? AND season_id = ? ORDER BY name");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param("ii", $team_id, $selected_season_id);
        } else {
            $stmt = $conn->prepare("SELECT id, name, position, jersey_number FROM volleyball_players WHERE team_id = ? ORDER BY name");
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
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
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No team_id provided']);
}

$conn->close();
?>
