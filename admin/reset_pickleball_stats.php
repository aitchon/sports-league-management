<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = '';

// Handle reset actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_action'])) {
    $reset_type = $_POST['reset_action'];
    $season_id = isset($_POST['season_id']) ? (int)$_POST['season_id'] : null;
    $division_id = isset($_POST['division_id']) ? (int)$_POST['division_id'] : null;
    
    try {
        switch ($reset_type) {
            case 'scores_only':
                // Reset only game scores
                $sql = "UPDATE pickleball_games 
                        SET 
                            match1_team1_score = NULL,
                            match1_team2_score = NULL,
                            match2_team1_score = NULL,
                            match2_team2_score = NULL,
                            match3_team1_score = NULL,
                            match3_team2_score = NULL,
                            status = 'scheduled',
                            updated_at = NOW()
                        WHERE status = 'completed'";
                
                if ($season_id) {
                    $sql .= " AND season_id = $season_id";
                }
                if ($division_id) {
                    $sql .= " AND division_id = $division_id";
                }
                
                $conn->query($sql);
                $message = '<div class="alert alert-success">Game scores reset successfully!</div>';
                break;
                
            case 'ladder_only':
                // Clear only ladder positions
                $sql = "DELETE FROM pickleball_ladder_positions";
                if ($season_id) {
                    $sql .= " WHERE season_id = $season_id";
                }
                
                $conn->query($sql);
                $message = '<div class="alert alert-success">Ladder positions cleared successfully!</div>';
                break;
                
            case 'scores_and_ladder':
                // Reset both scores and ladder positions
                $sql = "UPDATE pickleball_games 
                        SET 
                            match1_team1_score = NULL,
                            match1_team2_score = NULL,
                            match2_team1_score = NULL,
                            match2_team2_score = NULL,
                            match3_team1_score = NULL,
                            match3_team2_score = NULL,
                            status = 'scheduled',
                            updated_at = NOW()
                        WHERE status = 'completed'";
                
                if ($season_id) {
                    $sql .= " AND season_id = $season_id";
                }
                if ($division_id) {
                    $sql .= " AND division_id = $division_id";
                }
                
                $conn->query($sql);
                
                $sql2 = "DELETE FROM pickleball_ladder_positions";
                if ($season_id) {
                    $sql2 .= " WHERE season_id = $season_id";
                }
                
                $conn->query($sql2);
                $message = '<div class="alert alert-success">Game scores and ladder positions reset successfully!</div>';
                break;
                
            case 'availability':
                // Clear availability data
                $sql = "DELETE FROM pickleball_availability";
                if ($season_id) {
                    $sql .= " WHERE season_id = $season_id";
                }
                
                $conn->query($sql);
                $message = '<div class="alert alert-success">Availability data cleared successfully!</div>';
                break;
        }
        
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Get current stats for display
$stats = [];

// Games with scores
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM pickleball_games 
    WHERE match1_team1_score IS NOT NULL 
    OR match1_team2_score IS NOT NULL 
    OR match2_team1_score IS NOT NULL 
    OR match2_team2_score IS NOT NULL 
    OR match3_team1_score IS NOT NULL 
    OR match3_team2_score IS NOT NULL
");
$stats['games_with_scores'] = $result->fetch_assoc()['count'];

// Games without scores
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM pickleball_games 
    WHERE match1_team1_score IS NULL 
    AND match1_team2_score IS NULL 
    AND match2_team1_score IS NULL 
    AND match2_team2_score IS NULL 
    AND match3_team1_score IS NULL 
    AND match3_team2_score IS NULL
");
$stats['games_without_scores'] = $result->fetch_assoc()['count'];

// Ladder positions
$result = $conn->query("SELECT COUNT(*) as count FROM pickleball_ladder_positions");
$stats['ladder_positions'] = $result->fetch_assoc()['count'];

// Availability records
$result = $conn->query("SELECT COUNT(*) as count FROM pickleball_availability");
$stats['availability_records'] = $result->fetch_assoc()['count'];

// Get seasons and divisions for filtering
$seasons = [];
$result = $conn->query("SELECT * FROM pickleball_seasons ORDER BY start_date DESC");
if ($result) {
    $seasons = $result->fetch_all(MYSQLI_ASSOC);
}

$divisions = [];
$result = $conn->query("SELECT * FROM pickleball_divisions ORDER BY name");
if ($result) {
    $divisions = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Pickleball Stats</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        .warning-card {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            border: 2px solid #ff6b6b;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 mb-0">🏓 Reset Pickleball Stats</h1>
                    <p class="lead mb-0">Reset game scores, ladder positions, and availability data</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Current Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <h4><?php echo $stats['games_with_scores']; ?></h4>
                                    <p class="mb-0">Games with Scores</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <h4><?php echo $stats['games_without_scores']; ?></h4>
                                    <p class="mb-0">Games without Scores</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <h4><?php echo $stats['ladder_positions']; ?></h4>
                                    <p class="mb-0">Ladder Positions</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <h4><?php echo $stats['availability_records']; ?></h4>
                                    <p class="mb-0">Availability Records</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Reset Options</h5>
                    </div>
                    <div class="card-body">
                        <div class="warning-card p-3 mb-4">
                            <h6 class="text-danger">⚠️ Warning</h6>
                            <p class="mb-0">These actions cannot be undone. Make sure you have a backup before proceeding.</p>
                        </div>

                        <form method="POST" id="resetForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="season_id" class="form-label">Filter by Season (Optional)</label>
                                    <select class="form-select" id="season_id" name="season_id">
                                        <option value="">All Seasons</option>
                                        <?php foreach ($seasons as $season): ?>
                                            <option value="<?php echo $season['id']; ?>">
                                                <?php echo htmlspecialchars($season['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="division_id" class="form-label">Filter by Division (Optional)</label>
                                    <select class="form-select" id="division_id" name="division_id">
                                        <option value="">All Divisions</option>
                                        <?php foreach ($divisions as $division): ?>
                                            <option value="<?php echo $division['id']; ?>">
                                                <?php echo htmlspecialchars($division['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="reset_action" value="scores_only" 
                                        class="btn btn-warning" 
                                        onclick="return confirm('Reset all game scores? This will change completed games back to scheduled.')">
                                    Reset Game Scores Only
                                </button>
                                
                                <button type="submit" name="reset_action" value="ladder_only" 
                                        class="btn btn-info" 
                                        onclick="return confirm('Clear all ladder positions?')">
                                    Clear Ladder Positions Only
                                </button>
                                
                                <button type="submit" name="reset_action" value="scores_and_ladder" 
                                        class="btn btn-danger" 
                                        onclick="return confirm('Reset both scores AND ladder positions? This is a major reset.')">
                                    Reset Scores + Ladder Positions
                                </button>
                                
                                <button type="submit" name="reset_action" value="availability" 
                                        class="btn btn-secondary" 
                                        onclick="return confirm('Clear all availability data?')">
                                    Clear Availability Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Quick Links</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="pickleball_dashboard.php" class="btn btn-outline-primary btn-sm">Dashboard</a>
                            <a href="update_pickleball_ladder.php" class="btn btn-outline-success btn-sm">Update Ladder</a>
                            <a href="manage_pickleball_games.php" class="btn btn-outline-secondary btn-sm">Manage Games</a>
                            <a href="enter_pickleball_scores.php" class="btn btn-outline-info btn-sm">Enter Scores</a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">What Each Reset Does</h6>
                    </div>
                    <div class="card-body">
                        <small>
                            <strong>Reset Scores:</strong> Clears all match scores, changes completed games back to scheduled<br><br>
                            <strong>Clear Ladder:</strong> Removes all ladder position data<br><br>
                            <strong>Reset Both:</strong> Does both of the above<br><br>
                            <strong>Clear Availability:</strong> Removes all player availability records
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

