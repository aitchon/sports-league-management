<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = '';
$fixed_count = 0;
$checked_count = 0;

// Get all match score columns
$columns = [];
$result = $conn->query("SHOW COLUMNS FROM pickleball_games LIKE 'match%_team%_score'");
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

// Group columns by match number
$match_columns = [];
foreach ($columns as $col) {
    if (preg_match('/^match(\d+)_team([12])_score$/', $col, $matches)) {
        $match_num = (int)$matches[1];
        $team_num = (int)$matches[2];
        if (!isset($match_columns[$match_num])) {
            $match_columns[$match_num] = ['team1' => null, 'team2' => null];
        }
        $match_columns[$match_num]["team{$team_num}"] = $col;
    }
}

// Process fix if requested
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['fix_scores'])) {
    // Get all completed games - use * to get all columns
    $games = $conn->query("
        SELECT *
        FROM pickleball_games
        WHERE status = 'completed'
    ");
    
    while ($game = $games->fetch_assoc()) {
        $game_id = $game['id'];
        $update_fields = [];
        $update_values = [];
        
        // Check each match dynamically
        foreach ($match_columns as $match_num => $cols) {
            $team1_col = $cols['team1'];
            $team2_col = $cols['team2'];
            
            if (!$team1_col || !$team2_col) {
                continue; // Skip if we don't have both columns
            }
            
            $team1_score = $game[$team1_col] ?? null;
            $team2_score = $game[$team2_col] ?? null;
            
            // If one score is NULL and the other is > 0, NULL should be 0
            if ($team1_score === null && ($team2_score !== null && $team2_score > 0)) {
                $update_fields[] = "$team1_col = ?";
                $update_values[] = 0;
                $checked_count++;
            }
            if ($team2_score === null && ($team1_score !== null && $team1_score > 0)) {
                $update_fields[] = "$team2_col = ?";
                $update_values[] = 0;
                $checked_count++;
            }
        }
        
        // Update if any fields need fixing
        if (!empty($update_fields)) {
            $update_fields[] = "updated_at = NOW()";
            $update_values[] = $game_id;
            
            $sql = "UPDATE pickleball_games SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $param_types = str_repeat("i", count($update_values) - 1) . "i";
            $stmt->bind_param($param_types, ...$update_values);
            
            if ($stmt->execute()) {
                $fixed_count++;
            }
            $stmt->close();
        }
    }
    
    $message = '<div class="alert alert-success">Fixed ' . $fixed_count . ' games with NULL scores that should be 0.<br>
                Checked ' . $checked_count . ' matches total.</div>';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix NULL Scores in Database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="display-4 mb-0">🔧 Fix NULL Scores</h1>
                            <p class="lead mb-0">Convert NULL scores to 0 where appropriate</p>
                        </div>
                        <div>
                            <a href="pickleball_dashboard.php" class="btn btn-light btn-lg">
                                ← Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php echo $message; ?>
        
        <div class="card">
            <div class="card-header bg-warning">
                <h5 class="mb-0">⚠️ Fix NULL Scores</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    This script will fix NULL scores in the database that should be 0. It will:
                </p>
                <ul>
                    <li>Find matches where one score is NULL and the other is > 0</li>
                    <li>Convert NULL to 0 in those cases (e.g., 11-NULL becomes 11-0)</li>
                    <li>Only update completed games</li>
                    <li>Leave NULL-NULL pairs unchanged (truly missing scores)</li>
                </ul>
                
                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to fix NULL scores? This will update the database.');">
                    <input type="hidden" name="fix_scores" value="1">
                    <button type="submit" class="btn btn-warning btn-lg">
                        🔧 Fix NULL Scores
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

