<?php
include 'admin/db.php';

// Get current pickleball season
$current_season = null;
$result = $conn->query("SELECT * FROM pickleball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

// Get round robin games with match assignments
$round_robin_games = [];
if ($current_season) {
    $stmt = $conn->prepare("
        SELECT 
            pg.id as game_id,
            pg.date,
            pg.location,
            pg.notes,
            d.name as division_name,
            GROUP_CONCAT(
                CONCAT(p1.name, ' (P1)') ORDER BY p1.id
            ) as players
        FROM pickleball_games pg
        LEFT JOIN pickleball_divisions d ON pg.division_id = d.id
        LEFT JOIN pickleball_players p1 ON pg.player1_id = p1.id
        WHERE pg.game_type = 'round_robin' 
        AND pg.season_id = ?
        GROUP BY pg.id
        ORDER BY pg.date
    ");
    $stmt->bind_param("i", $current_season['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $round_robin_games[] = $row;
    }
    $stmt->close();
}

// Get match assignments for each game
$game_assignments = [];
foreach ($round_robin_games as $game) {
    $stmt = $conn->prepare("
        SELECT 
            pma.match_number,
            p1.name as team1_p1_name,
            p2.name as team1_p2_name,
            p3.name as team2_p1_name,
            p4.name as team2_p2_name
        FROM pickleball_match_assignments pma
        LEFT JOIN pickleball_players p1 ON pma.team1_player1_id = p1.id
        LEFT JOIN pickleball_players p2 ON pma.team1_player2_id = p2.id
        LEFT JOIN pickleball_players p3 ON pma.team2_player1_id = p3.id
        LEFT JOIN pickleball_players p4 ON pma.team2_player2_id = p4.id
        WHERE pma.game_id = ?
        ORDER BY pma.match_number
    ");
    $stmt->bind_param("i", $game['game_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $game_assignments[$game['game_id']] = $assignments;
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickleball Match Assignments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sport-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
        }
        .game-card {
            margin-bottom: 2rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .game-header {
            background: linear-gradient(135deg, #4CAF50, #66BB6A);
            color: white;
            padding: 1rem 1.5rem;
        }
        .match-card {
            border: 1px solid #e9ecef;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
        }
        .team {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem;
            background: white;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }
        .vs-badge {
            background: #007bff;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row">
            <div class="col-12 sport-header py-4 mb-4">
                <div class="container">
                    <div class="text-center">
                        <h1 class="display-4 mb-0">🏓 Pickleball Match Assignments</h1>
                        <p class="mb-0">Round Robin Game Matchups</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="container mb-4">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body py-3">
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <a href="pickleball_standings.php" class="btn btn-success">🏆 Standings</a>
                                <a href="pickleball_ladder.php" class="btn btn-primary">📈 Ladder</a>
                                <a href="pickleball_schedule.php" class="btn btn-info">📅 Schedule</a>
                                <a href="pickleball_stat_leaders.php" class="btn btn-warning">⭐ Stat Leaders</a>
                                <a href="pickleball_availability.php" class="btn btn-secondary">📋 Availability</a>
                                <a href="pickleball_match_assignments.php" class="btn btn-dark">🎯 Match Assignments</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php if (empty($round_robin_games)): ?>
                <div class="alert alert-info">
                    <h4>No Round Robin Games</h4>
                    <p>No round robin games found for the current season.</p>
                </div>
            <?php else: ?>
                <?php foreach ($round_robin_games as $game): ?>
                    <div class="game-card">
                        <div class="game-header">
                            <h5 class="mb-0">
                                <?php echo date('l, F j, Y', strtotime($game['date'])); ?> at <?php echo date('g:i A', strtotime($game['date'])); ?>
                            </h5>
                            <p class="mb-0">
                                <strong>Location:</strong> <?php echo htmlspecialchars($game['location']); ?>
                                <?php if ($game['division_name']): ?>
                                    | <strong>Division:</strong> <?php echo htmlspecialchars($game['division_name']); ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($game['notes']): ?>
                                <p class="mb-0"><strong>Notes:</strong> <?php echo htmlspecialchars($game['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body">
                            <?php if (isset($game_assignments[$game['game_id']]) && !empty($game_assignments[$game['game_id']])): ?>
                                <?php foreach ($game_assignments[$game['game_id']] as $match): ?>
                                    <div class="match-card">
                                        <h6 class="mb-3">Match <?php echo $match['match_number']; ?></h6>
                                        
                                        <div class="team">
                                            <div>
                                                <strong>Team 1:</strong> 
                                                <?php echo htmlspecialchars($match['team1_p1_name']); ?> & 
                                                <?php echo htmlspecialchars($match['team1_p2_name']); ?>
                                            </div>
                                            <span class="vs-badge">VS</span>
                                        </div>
                                        
                                        <div class="team">
                                            <div>
                                                <strong>Team 2:</strong> 
                                                <?php echo htmlspecialchars($match['team2_p1_name']); ?> & 
                                                <?php echo htmlspecialchars($match['team2_p2_name']); ?>
                                            </div>
                                            <span class="vs-badge">VS</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <p class="mb-0">No match assignments found for this game. Please regenerate match assignments.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
