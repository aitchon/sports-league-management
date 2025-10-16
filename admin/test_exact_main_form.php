<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Use the EXACT same variables as the main form
    $player1_id = $_POST['player1_id'] ? (int)$_POST['player1_id'] : null;
    $player2_id = $_POST['player2_id'] ? (int)$_POST['player2_id'] : null;
    $player3_id = $_POST['player3_id'] ? (int)$_POST['player3_id'] : null;
    $player4_id = $_POST['player4_id'] ? (int)$_POST['player4_id'] : null;
    $challenger_id = $_POST['challenger_id'] ? (int)$_POST['challenger_id'] : null;
    $challenged_id = $_POST['challenged_id'] ? (int)$_POST['challenged_id'] : null;
    $date = $_POST['date'] . ' ' . $_POST['time'];
    $location = $_POST['location'];
    $game_type = $_POST['game_type'];
    $is_ladder_match = isset($_POST['is_ladder_match']) ? 1 : 0;
    $is_challenge = isset($_POST['is_challenge']) ? 1 : 0;
    $season_id = (int)$_POST['season_id'];
    $division_id = $_POST['division_id'] ? (int)$_POST['division_id'] : null;
    $notes = $_POST['notes'] ?: null;
    $status = 'scheduled';
    
    echo "<h3>Exact Main Form Test</h3>";
    echo "<p><strong>game_type before binding:</strong> '" . $game_type . "'</p>";
    
    // Use the EXACT same SQL and binding as the main form
    $sql = "INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    echo "<p><strong>SQL:</strong> " . htmlspecialchars($sql) . "</p>";
    echo "<p><strong>Bind string:</strong> iiiiisssiiiiiss</p>";
    
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
        } else {
            echo "<p style='color: green;'>✅ Prepare successful</p>";
            
            // Test binding with debug output
            echo "<p><strong>Binding parameters in order:</strong></p>";
            echo "<ol>";
            echo "<li>player1_id: " . var_export($player1_id, true) . "</li>";
            echo "<li>player2_id: " . var_export($player2_id, true) . "</li>";
            echo "<li>player3_id: " . var_export($player3_id, true) . "</li>";
            echo "<li>player4_id: " . var_export($player4_id, true) . "</li>";
            echo "<li>challenger_id: " . var_export($challenger_id, true) . "</li>";
            echo "<li>challenged_id: " . var_export($challenged_id, true) . "</li>";
            echo "<li>date: " . var_export($date, true) . "</li>";
            echo "<li>location: " . var_export($location, true) . "</li>";
            echo "<li><strong>game_type: " . var_export($game_type, true) . "</strong></li>";
            echo "<li>is_ladder_match: " . var_export($is_ladder_match, true) . "</li>";
            echo "<li>is_challenge: " . var_export($is_challenge, true) . "</li>";
            echo "<li>season_id: " . var_export($season_id, true) . "</li>";
            echo "<li>division_id: " . var_export($division_id, true) . "</li>";
            echo "<li>notes: " . var_export($notes, true) . "</li>";
            echo "<li>status: " . var_export($status, true) . "</li>";
            echo "</ol>";
            
            $bind_result = $stmt->bind_param("iiiiisssiiiiiss", $player1_id, $player2_id, $player3_id, $player4_id, $challenger_id, $challenged_id, $date, $location, $game_type, $is_ladder_match, $is_challenge, $season_id, $division_id, $notes, $status);
            
            if (!$bind_result) {
                echo "<p style='color: red;'>❌ Bind failed: " . $stmt->error . "</p>";
            } else {
                echo "<p style='color: green;'>✅ Bind successful</p>";
                
                if ($stmt->execute()) {
                    $insert_id = $conn->insert_id;
                    echo "<p style='color: green;'>✅ Execute successful! ID: $insert_id</p>";
                    
                    // Check what was actually saved
                    $verify = $conn->query("SELECT * FROM pickleball_games WHERE id = $insert_id");
                    $record = $verify->fetch_assoc();
                    echo "<p><strong>Saved record:</strong></p>";
                    echo "<pre>" . print_r($record, true) . "</pre>";
                    
                    // Clean up
                    $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
                    echo "<p>Test record cleaned up.</p>";
                } else {
                    echo "<p style='color: red;'>❌ Execute failed: " . $stmt->error . "</p>";
                }
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Exact Main Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1>Test Exact Main Form Parameters</h1>
        
        <?php echo $message; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>Exact Main Form Test</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="game_type" class="form-label">Game Type</label>
                            <select class="form-select" id="game_type" name="game_type" required>
                                <option value="">Select Game Type</option>
                                <option value="singles">Singles</option>
                                <option value="doubles">Doubles</option>
                                <option value="mixed">Mixed</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="season_id" class="form-label">Season ID</label>
                            <input type="number" class="form-control" id="season_id" name="season_id" value="1" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" value="2024-01-01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="time" class="form-label">Time</label>
                            <input type="time" class="form-control" id="time" name="time" value="10:00" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" value="Test Court" required>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_ladder_match" name="is_ladder_match" checked>
                            <label class="form-check-label" for="is_ladder_match">Ladder Match</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Test Exact Main Form</button>
                </form>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="manage_pickleball_games.php" class="btn btn-secondary">← Back to Games</a>
        </div>
    </div>
</body>
</html>



