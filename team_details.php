<?php
include 'admin/db.php';

// Fetch team ID from the query string
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

if (!$team_id) {
    echo "Team ID is missing.";
    exit();
}

// Fetch team details with coach name
$team = $conn->query("SELECT id, name, coach, team_pic_url FROM teams WHERE id = $team_id")->fetch_assoc();

if (!$team) {
    echo "Team not found.";
    exit();
}

// Fetch the team's games
$games = $conn->query("
    SELECT g.id, g.date, g.location, t1.name AS home_team, t2.name AS away_team, g.home_team_id, g.away_team_id, g.status, s.home_team_score, s.away_team_score
    FROM games g
    JOIN teams t1 ON g.home_team_id = t1.id
    JOIN teams t2 ON g.away_team_id = t2.id
    LEFT JOIN scores s ON g.id = s.game_id
    WHERE (g.home_team_id = $team_id OR g.away_team_id = $team_id)
    ORDER BY g.date
");

// Fetch the team's player statistics
$players = $conn->query("
    SELECT p.id, p.name, 
           SUM(ps.points) AS total_points, 
           SUM(ps.three_pointers_made) AS total_three_pointers_made,
           SUM(ps.free_throws_made) AS total_free_throws_made,
           SUM(ps.free_throws_attempted) AS total_free_throws_attempted
    FROM players p
    LEFT JOIN player_stats ps ON p.id = ps.player_id
    WHERE p.team_id = $team_id
    GROUP BY p.id
    ORDER BY total_points DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Details</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
/* Mobile-specific styles */
@media (max-width: 768px) {
    body, p, span, div, td, th {
        font-size: 2px !important;
    }

    a {
        font-size: 2px !important;
        color: blue !important;  /* Change to your preferred color */
        text-decoration: underline !important;
    }

    a:hover {
        color: red !important;  /* Change hover color */
        text-decoration: none !important;
    }

    a:visited {
        color: purple !important;  /* Change visited link color */
    }
}
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="text-center mb-4">
            <h1 class="display-4"><?php echo htmlspecialchars($team['name']); ?></h1>

            <?php if (!empty($team['coach'])): ?>
                <h4 class="text-muted"><?php echo "Coach: " . htmlspecialchars($team['coach']); ?></h4>
            <?php endif; ?>

            <?php if (!empty($team['team_pic_url'])): ?>
                <img src="<?php echo htmlspecialchars($team['team_pic_url']); ?>" alt="Team Picture" class="img-fluid team-logo mb-3">
            <?php endif; ?>

        </div>

        <!-- Player Stats -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Player Stats</h5>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Player</th>
                            <th>Points</th>
                            <th>3 Ptrs</th>
                            <th>FT%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($player = $players->fetch_assoc()): 
                            $free_throw_percentage = ($player['total_free_throws_attempted'] > 0) 
                                ? number_format($player['total_free_throws_made'] / $player['total_free_throws_attempted'], 3)
                                : '0.000';
                        ?>
                            <tr>
                                <td><?php echo $player['name']; ?></td>
                                <td><?php echo $player['total_points']; ?></td>
                                <td><?php echo $player['total_three_pointers_made']; ?></td>
                                <td><?php echo $free_throw_percentage; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Team Schedule -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Team Schedule</h5>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Opponent</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($game = $games->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M j g:i A', strtotime($game['date'])); ?></td>
                                <td><?php echo htmlspecialchars($game['location']); ?></td>
                                <td>
                                    <?php 
                                        // Determine the opponent team
                                        if ($game['home_team'] == $team['name']) {
                                            $opponent_name = $game['away_team'];
                                            $opponent_id = $game['away_team_id'];
                                        } else {
                                            $opponent_name = $game['home_team'];
                                            $opponent_id = $game['home_team_id'];
                                        }
                                    ?>
                                    <a href="team_details.php?team_id=<?php echo $opponent_id; ?>">
                                        <?php echo htmlspecialchars($opponent_name); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($game['status'] == 'completed'): ?>
                                        <a href="game_details.php?game_id=<?php echo $game['id']; ?>">
                                            <?php 
                                                $home_score = $game['home_team_score'];
                                                $away_score = $game['away_team_score'];

                                                // Check win/loss for the team
                                                if (($game['home_team_id'] == $team_id && $home_score > $away_score) || 
                                                    ($game['away_team_id'] == $team_id && $away_score > $home_score)) {
                                                    $result = 'W';
                                                } elseif (($game['home_team_id'] == $team_id && $home_score < $away_score) || 
                                                          ($game['away_team_id'] == $team_id && $away_score < $home_score)) {
                                                    $result = 'L';
                                                } else {
                                                    $result = 'T';
                                                }

                                                echo "($result) "  . htmlspecialchars($home_score) . " - " . htmlspecialchars($away_score);
                                            ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="text-center">
            <button class="btn btn-primary" onclick="window.location.href = document.referrer;">Back to Previous Page</button>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
