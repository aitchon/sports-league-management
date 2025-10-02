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
    $game_type = $_POST['game_type'] ?? 'NOT_SET';
    
    echo "<h3>Debug Information:</h3>";
    echo "<p><strong>POST data received:</strong></p>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    echo "<p><strong>Game Type from POST:</strong> " . htmlspecialchars($game_type) . "</p>";
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'pickleball_games'");
    if ($table_check->num_rows == 0) {
        $message = '<div class="alert alert-danger">❌ pickleball_games table does not exist!</div>';
    } else {
        // Check table structure
        $structure = $conn->query("DESCRIBE pickleball_games");
        $columns = [];
        while ($row = $structure->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        echo "<p><strong>Table columns:</strong> " . implode(', ', $columns) . "</p>";
        
        if (!in_array('game_type', $columns)) {
            $message = '<div class="alert alert-danger">❌ game_type column does not exist!</div>';
        } else {
            // Try to insert a simple test record
            try {
                echo "<p><strong>Attempting insert with game_type:</strong> " . htmlspecialchars($game_type) . "</p>";
                
                $sql = "INSERT INTO pickleball_games (date, location, game_type, status) VALUES (?, ?, ?, ?)";
                echo "<p><strong>SQL Query:</strong> " . htmlspecialchars($sql) . "</p>";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    echo "<p><strong>❌ Prepare failed:</strong> " . $conn->error . "</p>";
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                echo "<p><strong>SQL prepared statement created successfully</strong></p>";
                
                $bind_result = $stmt->bind_param("ssss", '2024-01-01 10:00:00', 'Test Court', $game_type, 'scheduled');
                if (!$bind_result) {
                    echo "<p><strong>❌ Bind failed:</strong> " . $stmt->error . "</p>";
                    throw new Exception("Bind failed: " . $stmt->error);
                }
                
                echo "<p><strong>Parameters bound successfully</strong></p>";
                
                if ($stmt->execute()) {
                    $insert_id = $conn->insert_id;
                    $message = '<div class="alert alert-success">✅ Test record inserted successfully! ID: ' . $insert_id . ', Game Type: ' . htmlspecialchars($game_type) . '</div>';
                    
                    // Verify the data was actually saved
                    $verify = $conn->query("SELECT * FROM pickleball_games WHERE id = $insert_id");
                    $record = $verify->fetch_assoc();
                    echo "<p><strong>Saved record:</strong></p>";
                    echo "<pre>" . print_r($record, true) . "</pre>";
                    
                    // Clean up
                    $conn->query("DELETE FROM pickleball_games WHERE id = $insert_id");
                    echo "<p>Test record cleaned up.</p>";
                } else {
                    $message = '<div class="alert alert-danger">❌ Insert failed: ' . $stmt->error . '</div>';
                    echo "<p><strong>Insert failed with error:</strong> " . $stmt->error . "</p>";
                }
                $stmt->close();
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger">❌ Error: ' . $e->getMessage() . '</div>';
                echo "<p><strong>Exception caught:</strong> " . $e->getMessage() . "</p>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Game Type</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1>Test Game Type Saving</h1>
        
        <?php echo $message; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>Simple Test Form</h5>
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
                    <button type="submit" class="btn btn-primary">Test Save</button>
                </form>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="manage_pickleball_games.php" class="btn btn-secondary">← Back to Games</a>
        </div>
    </div>
</body>
</html>
