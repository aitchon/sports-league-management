<?php
include 'db.php';

if (isset($_GET['id'])) {
    $game_id = $_GET['id'];

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

}

// Redirect back to avoid re-adding last game
header("Location: manage_games.php");
exit();
?>
