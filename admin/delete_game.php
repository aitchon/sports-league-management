<?php
include 'db.php';

if (isset($_GET['id'])) {
    $game_id = $_GET['id'];
    $sport = isset($_GET['sport']) ? $_GET['sport'] : 'basketball';

    // Determine which tables to delete from based on sport
    switch ($sport) {
        case 'pickleball':
            // Delete from pickleball-related tables
            // First delete match assignments
            $stmt = $conn->prepare("DELETE FROM pickleball_match_assignments WHERE game_id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $stmt->close();

            // Delete from pickleball_scores table
            $stmt = $conn->prepare("DELETE FROM pickleball_scores WHERE game_id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $stmt->close();

            // Delete from pickleball_player_stats table
            $stmt = $conn->prepare("DELETE FROM pickleball_player_stats WHERE game_id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $stmt->close();

            // Delete from pickleball_games table
            $stmt = $conn->prepare("DELETE FROM pickleball_games WHERE id = ?");
            if ($stmt === false) {
                die("Error preparing statement: " . $conn->error);
            }
            $stmt->bind_param("i", $game_id);
            if (!$stmt->execute()) {
                die("Error executing query: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'volleyball':
            // Delete from volleyball-related tables
            // First delete scores
            $stmt = $conn->prepare("DELETE FROM volleyball_scores WHERE game_id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $stmt->close();

            // Delete player stats
            $stmt = $conn->prepare("DELETE FROM volleyball_player_stats WHERE game_id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $stmt->close();

            // Delete from volleyball_games table
            $stmt = $conn->prepare("DELETE FROM volleyball_games WHERE id = ?");
            if ($stmt === false) {
                die("Error preparing statement: " . $conn->error);
            }
            $stmt->bind_param("i", $game_id);
            if (!$stmt->execute()) {
                die("Error executing query: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'basketball':
        default:
            // Delete from basketball-related tables
            // Delete scores for the game from the 'scores' table
            $stmt = $conn->prepare("DELETE FROM scores WHERE game_id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $stmt->close();

            // Delete player stats for the game from the 'player_stats' table
            $stmt = $conn->prepare("DELETE FROM player_stats WHERE game_id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $stmt->close();

            // Delete game from the 'games' table
            $stmt = $conn->prepare("DELETE FROM games WHERE id = ?");
            if ($stmt === false) {
                die("Error preparing statement: " . $conn->error);
            }
            $stmt->bind_param("i", $game_id);
            if (!$stmt->execute()) {
                die("Error executing query: " . $stmt->error);
            }
            $stmt->close();
            break;
    }
}

// Determine redirect based on sport parameter or referer
$redirect_url = "manage_games.php"; // Default to basketball

// Check if there's a sport parameter
if (isset($_GET['sport'])) {
    $sport = $_GET['sport'];
    switch ($sport) {
        case 'pickleball':
            $redirect_url = "manage_pickleball_games.php";
            break;
        case 'volleyball':
            $redirect_url = "manage_volleyball_games.php";
            break;
        case 'basketball':
        default:
            $redirect_url = "manage_games.php";
            break;
    }
} else {
    // Check referer to determine which sport's manage games page we came from
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if (strpos($referer, 'manage_pickleball_games.php') !== false) {
        $redirect_url = "manage_pickleball_games.php";
    } elseif (strpos($referer, 'manage_volleyball_games.php') !== false) {
        $redirect_url = "manage_volleyball_games.php";
    }
}

// Redirect back to avoid re-adding last game
header("Location: " . $redirect_url);
exit();
?>
