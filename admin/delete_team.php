<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$team_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($team_id > 0) {
    // Check if team has any games scheduled
    $games_check = $conn->prepare("SELECT COUNT(*) as game_count FROM games WHERE home_team_id = ? OR away_team_id = ?");
    $games_check->bind_param("ii", $team_id, $team_id);
    $games_check->execute();
    $result = $games_check->get_result();
    $game_count = $result->fetch_assoc()['game_count'];
    $games_check->close();
    
    if ($game_count > 0) {
        $_SESSION['error_message'] = "Cannot delete team: Team has " . $game_count . " scheduled game(s). Please delete the games first.";
    } else {
        // Check if team has any players
        $players_check = $conn->prepare("SELECT COUNT(*) as player_count FROM players WHERE team_id = ?");
        $players_check->bind_param("i", $team_id);
        $players_check->execute();
        $result = $players_check->get_result();
        $player_count = $result->fetch_assoc()['player_count'];
        $players_check->close();
        
        if ($player_count > 0) {
            $_SESSION['error_message'] = "Cannot delete team: Team has " . $player_count . " player(s). Please remove the players first.";
        } else {
            // Safe to delete the team
            $delete_stmt = $conn->prepare("DELETE FROM teams WHERE id = ?");
            $delete_stmt->bind_param("i", $team_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "Team deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Error deleting team: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        }
    }
} else {
    $_SESSION['error_message'] = "Invalid team ID.";
}

// Redirect back to the referring page or dashboard
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
header("Location: " . $referer);
exit();
?>
