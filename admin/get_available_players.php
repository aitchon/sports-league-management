<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

include 'db.php';

header('Content-Type: application/json');

$match_date = $_POST['match_date'] ?? null;
$season_id = $_POST['season_id'] ?? null;

// Debug logging
error_log("get_available_players.php - match_date: $match_date, season_id: $season_id");

if (!$match_date || !$season_id) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

try {
    // First, let's check what availability records exist
    $debug_stmt = $conn->prepare("
        SELECT p.id, p.name, a.is_available, a.match_date
        FROM pickleball_players p
        LEFT JOIN pickleball_availability a ON p.id = a.player_id AND a.match_date = ?
        WHERE p.season_id = ? AND p.is_active = 1
        ORDER BY p.name
    ");
    $debug_stmt->bind_param("si", $match_date, $season_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    
    $debug_data = [];
    while ($row = $debug_result->fetch_assoc()) {
        $debug_data[] = $row;
    }
    $debug_stmt->close();
    
    error_log("Debug data: " . print_r($debug_data, true));
    
    // Get players who are available for this specific date
    $stmt = $conn->prepare("
        SELECT p.*
        FROM pickleball_players p
        LEFT JOIN pickleball_availability a ON p.id = a.player_id AND a.match_date = ?
        WHERE p.season_id = ? AND p.is_active = 1 
        AND (a.is_available IS NULL OR a.is_available = 1)
        ORDER BY p.name
    ");
    $stmt->bind_param("si", $match_date, $season_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $players = [];
    while ($row = $result->fetch_assoc()) {
        $players[] = $row;
    }
    $stmt->close();
    
    error_log("Available players count: " . count($players));
    
    echo json_encode(['success' => true, 'players' => $players, 'debug' => $debug_data]);
    
} catch (Exception $e) {
    error_log("Error in get_available_players.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
