<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Get current season
$current_season = null;
$stmt = $conn->prepare("SELECT * FROM pickleball_seasons WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}
$stmt->close();

// Get player statistics
$player_stats = [];
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.name,
            COUNT(ps.id) as total_matches,
            SUM(ps.won_match) as wins,
            SUM(ps.points_scored) as total_points_scored,
            SUM(ps.points_against) as total_points_against,
            ROUND(AVG(ps.points_scored), 1) as avg_points_scored,
            ROUND(AVG(ps.points_against), 1) as avg_points_against,
            ROUND((SUM(ps.won_match) / COUNT(ps.id)) * 100, 1) as win_percentage
        FROM pickleball_players p
        LEFT JOIN pickleball_player_stats ps ON p.id = ps.player_id
        LEFT JOIN pickleball_games pg ON ps.game_id = pg.id
        WHERE p.season_id = ? AND p.is_active = 1
        GROUP BY p.id, p.name
        ORDER BY wins DESC, total_points_scored DESC
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $player_stats[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player Statistics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #2E8B57 0%, #228B22 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2E8B57;
        }
    </style>
</head>
<body>
    <div class="sport-header">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1 class="display-4 mb-0">📊 Player Statistics</h1>
                    <p class="lead mb-0">Track wins, points, and performance</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (empty($player_stats)): ?>
            <div class="alert alert-info">
                <h4>No Player Statistics</h4>
                <p>No player statistics found for the current season.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($player_stats as $player): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="stat-card">
                            <h5 class="text-primary mb-3"><?php echo htmlspecialchars($player['name']); ?></h5>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="stat-number"><?php echo $player['wins']; ?></div>
                                    <small class="text-muted">Wins</small>
                                </div>
                                <div class="col-6">
                                    <div class="stat-number"><?php echo $player['total_matches']; ?></div>
                                    <small class="text-muted">Matches</small>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="stat-number"><?php echo $player['win_percentage']; ?>%</div>
                                    <small class="text-muted">Win Rate</small>
                                </div>
                                <div class="col-6">
                                    <div class="stat-number"><?php echo $player['total_points_scored']; ?></div>
                                    <small class="text-muted">Points Scored</small>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="stat-number"><?php echo $player['avg_points_scored']; ?></div>
                                    <small class="text-muted">Avg Points/Game</small>
                                </div>
                                <div class="col-6">
                                    <div class="stat-number"><?php echo $player['total_points_against']; ?></div>
                                    <small class="text-muted">Points Against</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="row mt-4">
            <div class="col-12">
                <a href="enter_pickleball_scores.php" class="btn btn-primary">Enter Scores</a>
                <a href="pickleball_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



