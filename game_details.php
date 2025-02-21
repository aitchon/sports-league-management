<?php
include 'admin/db.php';

// Fetch game ID from the query string
$game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : null;

if (!$game_id) {
    echo "Game ID is missing.";
    exit();
}

// Fetch game details
$game = $conn->query("
    SELECT g.id, g.date, g.location, t1.name AS home_team, t2.name AS away_team, s.home_team_score, s.away_team_score
    FROM games g
    JOIN teams t1 ON g.home_team_id = t1.id
    JOIN teams t2 ON g.away_team_id = t2.id
    LEFT JOIN scores s ON g.id = s.game_id
    WHERE g.id = $game_id
")->fetch_assoc();

if (!$game) {
    echo "Game not found.";
    exit();
}

// Fetch player statistics for the home team
$home_team_stats = $conn->query("
    SELECT p.name, ps.points, ps.three_pointers_made, ps.fouls
    FROM player_stats ps
    JOIN players p ON ps.player_id = p.id
    WHERE ps.game_id = $game_id AND p.team_id = (SELECT home_team_id FROM games WHERE id = $game_id)
");

// Fetch player statistics for the away team
$away_team_stats = $conn->query("
    SELECT p.name, ps.points, ps.three_pointers_made, ps.fouls
    FROM player_stats ps
    JOIN players p ON ps.player_id = p.id
    WHERE ps.game_id = $game_id AND p.team_id = (SELECT away_team_id FROM games WHERE id = $game_id)
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Details</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="text-center mb-4">
            <h1 class="display-4">Game Details</h1>
            <p class="lead"><?php echo $game['home_team']; ?> vs <?php echo $game['away_team']; ?></p>
        </div>

        <!-- Game Information -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Game Information</h5>
                <p class="card-text"><strong>Date:</strong> <?php echo $game['date']; ?></p>
                <p class="card-text"><strong>Location:</strong> <?php echo $game['location']; ?></p>
                <p class="card-text"><strong>Final Score:</strong> <?php echo $game['home_team']; ?> <?php echo $game['home_team_score']; ?> - <?php echo $game['away_team_score']; ?> <?php echo $game['away_team']; ?></p>
            </div>
        </div>

        <!-- Home Team Player Stats -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?php echo $game['home_team']; ?></h5>
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
                        <?php while ($player = $home_team_stats->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $player['name']; ?></td>
                                <td><?php echo $player['points']; ?></td>
                                <td><?php echo $player['three_pointers_made']; ?></td>
                                <td><?php echo $player['fouls']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Away Team Player Stats -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?php echo $game['away_team']; ?></h5>
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
                        <?php while ($player = $away_team_stats->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $player['name']; ?></td>
                                <td><?php echo $player['points']; ?></td>
                                <td><?php echo $player['three_pointers_made']; ?></td>
                                <td><?php echo $player['fouls']; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-center">
            <a href="schedule.php" class="btn btn-primary">Back to Schedule</a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>