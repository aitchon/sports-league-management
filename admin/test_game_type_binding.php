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
    $game_type = $_POST['game_type'];
    
    echo "<h3>Testing Game Type Binding</h3>";
    echo "<p><strong>Game Type from POST:</strong> " . htmlspecialchars($game_type) . "</p>";
    
    // Test 1: Simple insert with just game_type
    echo "<h4>Test 1: Simple Insert</h4>";
    try {
        $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", '2024-01-01 10:00:00', 'Test Court', $game_type, 'scheduled');
        
        if ($stmt->execute()) {
            $id1 = $conn->insert_id;
            echo "<p style='color: green;'>✅ Simple insert successful! ID: $id1</p>";
            
            $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $id1");
            $result = $verify->fetch_assoc();
            echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
            
            $conn->query("DELETE FROM pickleball_games WHERE id = $id1");
        } else {
            echo "<p style='color: red;'>❌ Simple insert failed: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Simple insert error: " . $e->getMessage() . "</p>";
    }
    
    // Test 2: Full insert with all parameters
    echo "<h4>Test 2: Full Insert with All Parameters</h4>";
    try {
        $player1_id = null;
        $player2_id = null;
        $player3_id = null;
        $player4_id = null;
        $challenger_id = null;
        $challenged_id = null;
        $date = '2024-01-01 10:00:00';
        $location = 'Test Court';
        $is_ladder_match = 1;
        $is_challenge = 0;
        $season_id = 1;
        $division_id = null;
        $notes = null;
        $status = 'scheduled';
        
        echo "<p><strong>game_type variable before binding:</strong> '" . $game_type . "'</p>";
        
        $stmt = $conn->prepare("INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Test the binding step by step
        echo "<p><strong>Binding parameters...</strong></p>";
        $bind_result = $stmt->bind_param("iiiiisssiiiiiss", $player1_id, $player2_id, $player3_id, $player4_id, $challenger_id, $challenged_id, $date, $location, $game_type, $is_ladder_match, $is_challenge, $season_id, $division_id, $notes, $status);
        
        if (!$bind_result) {
            echo "<p style='color: red;'>❌ Bind failed: " . $stmt->error . "</p>";
        } else {
            echo "<p style='color: green;'>✅ Bind successful</p>";
            
            if ($stmt->execute()) {
                $id2 = $conn->insert_id;
                echo "<p style='color: green;'>✅ Full insert successful! ID: $id2</p>";
                
                $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $id2");
                $result = $verify->fetch_assoc();
                echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
                
                $conn->query("DELETE FROM pickleball_games WHERE id = $id2");
            } else {
                echo "<p style='color: red;'>❌ Execute failed: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Full insert error: " . $e->getMessage() . "</p>";
    }
    
    // Test 3: Check if the issue is with NULL values
    echo "<h4>Test 3: Insert with Non-NULL Values</h4>";
    try {
        $player1_id = 1; // Use actual player ID if available
        $player2_id = 2;
        $player3_id = null;
        $player4_id = null;
        $challenger_id = null;
        $challenged_id = null;
        $date = '2024-01-01 10:00:00';
        $location = 'Test Court';
        $is_ladder_match = 1;
        $is_challenge = 0;
        $season_id = 1;
        $division_id = null;
        $notes = 'Test notes';
        $status = 'scheduled';
        
        echo "<p><strong>game_type variable before binding:</strong> '" . $game_type . "'</p>";
        
        $stmt = $conn->prepare("INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $bind_result = $stmt->bind_param("iiiiisssiiiiiss", $player1_id, $player2_id, $player3_id, $player4_id, $challenger_id, $challenged_id, $date, $location, $game_type, $is_ladder_match, $is_challenge, $season_id, $division_id, $notes, $status);
        
        if (!$bind_result) {
            echo "<p style='color: red;'>❌ Bind failed: " . $stmt->error . "</p>";
        } else {
            echo "<p style='color: green;'>✅ Bind successful</p>";
            
            if ($stmt->execute()) {
                $id3 = $conn->insert_id;
                echo "<p style='color: green;'>✅ Non-NULL insert successful! ID: $id3</p>";
                
                $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $id3");
                $result = $verify->fetch_assoc();
                echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
                
                $conn->query("DELETE FROM pickleball_games WHERE id = $id3");
            } else {
                echo "<p style='color: red;'>❌ Execute failed: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Non-NULL insert error: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Game Type Binding</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1>Test Game Type Binding</h1>
        
        <?php echo $message; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>Test Game Type Parameter Binding</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="game_type" class="form-label">Game Type</label>
                        <select class="form-select" id="game_type" name="game_type" required>
                            <option value="">Select Game Type</option>
                            <option value="singles">Singles</option>
                            <option value="doubles">Doubles</option>
                            <option value="mixed">Mixed</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Test Binding</button>
                </form>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="manage_pickleball_games.php" class="btn btn-secondary">← Back to Games</a>
        </div>
    </div>
</body>
</html>

