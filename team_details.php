<?php
include 'admin/db.php';

// Fetch team ID from the query string
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

if (!$team_id) {
    echo "Team ID is missing.";
    exit();
}

// Fetch team details
$team = $conn->query("SELECT id, name FROM teams WHERE id = $team_id")->fetch_assoc();

if (!$team) {
    echo "Team not found.";
    exit();
}

// Fetch the team's games
$games = $conn->query("
    SELECT g.id, g.date, g.location, t1.name AS home_team, t2.name AS away_team, g.status, s.home_team_score, s.away_team_score
    FROM games g
    JOIN teams t1 ON g.home_team_id = t1.id
    JOIN teams t2 ON g.away_team_id = t2.id
    LEFT JOIN scores s ON g.id = s.game_id
    WHERE (g.home_team_id = $team_id OR g.away_team_id = $team_id)
    ORDER BY g.date
");

// Fetch the team's player statistics
$players = $conn->query("
    SELECT p.id, p.name, SUM(ps.points) AS total_points, SUM(ps.three_pointers_made) AS total_three_pointers_made, SUM(ps.fouls) AS total_fouls
    FROM players p
    LEFT JOIN player_stats ps ON p.id = ps.player_id
    WHERE p.team_id = $team_id
    GROUP BY p.id
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
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="text-center mb-4">
            <h1 class="display-4"><?php echo $team['name']; ?></h1>
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
                            <th>Fouls</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($player = $players->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $player['name']; ?></td>
                                <td><?php echo $player['total_points']; ?></td>
                                <td><?php echo $player['total_three_pointers_made']; ?></td>
                                <td><?php echo $player['total_fouls']; ?></td>
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
                            <th>Home Team</th>
                            <th>Away Team</th>
                            <th>Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($game = $games->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('M j g:i A', strtotime($game['date'])); ?></td>
                                <td><?php echo $game['location']; ?></td>
                                <td><?php echo $game['home_team']; ?></td>
                                <td><?php echo $game['away_team']; ?></td>
                                <td>
                                    <?php if ($game['status'] == 'completed'): ?>
                                        <a href="game_details.php?game_id=<?php echo $game['id']; ?>">
                                            <?php echo $game['home_team_score']; ?> - <?php echo $game['away_team_score']; ?>
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