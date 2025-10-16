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
    
    echo "<h3>Testing Parameter Binding Step by Step</h3>";
    echo "<p><strong>Game Type:</strong> " . htmlspecialchars($game_type) . "</p>";
    
    // Test 1: Try with just the essential parameters
    echo "<h4>Test 1: Essential Parameters Only</h4>";
    try {
        $stmt = $conn->prepare("INSERT INTO pickleball_games (date, location, game_type, status, season_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", '2024-01-01 10:00:00', 'Test Court', $game_type, 'scheduled', 1);
        
        if ($stmt->execute()) {
            $id1 = $conn->insert_id;
            echo "<p style='color: green;'>✅ Essential insert successful! ID: $id1</p>";
            
            $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $id1");
            $result = $verify->fetch_assoc();
            echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
            
            $conn->query("DELETE FROM pickleball_games WHERE id = $id1");
        } else {
            echo "<p style='color: red;'>❌ Essential insert failed: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Essential insert error: " . $e->getMessage() . "</p>";
    }
    
    // Test 2: Try with all parameters but different order
    echo "<h4>Test 2: Different Parameter Order</h4>";
    try {
        $stmt = $conn->prepare("INSERT INTO pickleball_games (game_type, date, location, status, season_id, player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, is_ladder_match, is_challenge, division_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiiiiiiiiis", $game_type, '2024-01-01 10:00:00', 'Test Court', 'scheduled', 1, null, null, null, null, null, null, 1, 0, null, null);
        
        if ($stmt->execute()) {
            $id2 = $conn->insert_id;
            echo "<p style='color: green;'>✅ Reordered insert successful! ID: $id2</p>";
            
            $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $id2");
            $result = $verify->fetch_assoc();
            echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
            
            $conn->query("DELETE FROM pickleball_games WHERE id = $id2");
        } else {
            echo "<p style='color: red;'>❌ Reordered insert failed: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Reordered insert error: " . $e->getMessage() . "</p>";
    }
    
    // Test 3: Try with explicit NULL values instead of null variables
    echo "<h4>Test 3: Explicit NULL Values</h4>";
    try {
        $stmt = $conn->prepare("INSERT INTO pickleball_games (player1_id, player2_id, player3_id, player4_id, challenger_id, challenged_id, date, location, game_type, is_ladder_match, is_challenge, season_id, division_id, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiisssiiiiiss", null, null, null, null, null, null, '2024-01-01 10:00:00', 'Test Court', $game_type, 1, 0, 1, null, null, 'scheduled');
        
        if ($stmt->execute()) {
            $id3 = $conn->insert_id;
            echo "<p style='color: green;'>✅ Explicit NULL insert successful! ID: $id3</p>";
            
            $verify = $conn->query("SELECT game_type FROM pickleball_games WHERE id = $id3");
            $result = $verify->fetch_assoc();
            echo "<p><strong>Saved game_type:</strong> '" . $result['game_type'] . "'</p>";
            
            $conn->query("DELETE FROM pickleball_games WHERE id = $id3");
        } else {
            echo "<p style='color: red;'>❌ Explicit NULL insert failed: " . $stmt->error . "</p>";
        }
        $stmt->close();
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Explicit NULL insert error: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Parameter Binding</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1>Test Parameter Binding</h1>
        
        <?php echo $message; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>Test Different Binding Approaches</h5>
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



