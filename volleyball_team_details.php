<?php
include 'admin/db.php';

// Fetch team ID from the query string
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

if (!$team_id) {
	echo "Team ID is missing.";
	exit();
}

// Determine current volleyball season (if any)
$current_season = null;
$season_result = $conn->query("SELECT * FROM volleyball_seasons WHERE is_active = 1 LIMIT 1");
if ($season_result && $season_result->num_rows > 0) {
	$current_season = $season_result->fetch_assoc();
}

// Fetch volleyball team details
if ($current_season) {
	$stmt = $conn->prepare("SELECT id, name, coach, team_pic_url FROM volleyball_teams WHERE id = ? AND season_id = ?");
	$stmt->bind_param("ii", $team_id, $current_season['id']);
	$stmt->execute();
	$team = $stmt->get_result()->fetch_assoc();
	$stmt->close();
} else {
	$team = $conn->query("SELECT id, name, coach, team_pic_url FROM volleyball_teams WHERE id = $team_id")->fetch_assoc();
}

if (!$team) {
	echo "Team not found.";
	exit();
}

// Fetch the team's games (volleyball)
if ($current_season) {
	$games_stmt = $conn->prepare("
		SELECT vg.id, vg.date, vg.location,
		       t1.name AS home_team, t2.name AS away_team,
		       vg.home_team_id, vg.away_team_id, vg.status,
		       vs.home_team_score, vs.away_team_score
		FROM volleyball_games vg
		JOIN volleyball_teams t1 ON vg.home_team_id = t1.id
		JOIN volleyball_teams t2 ON vg.away_team_id = t2.id
		LEFT JOIN volleyball_scores vs ON vg.id = vs.game_id
		WHERE (vg.home_team_id = ? OR vg.away_team_id = ?) AND vg.season_id = ?
		ORDER BY vg.date
	");
	$games_stmt->bind_param("iii", $team_id, $team_id, $current_season['id']);
	$games_stmt->execute();
	$games = $games_stmt->get_result();
	$games_stmt->close();
} else {
	$games = $conn->query("
		SELECT vg.id, vg.date, vg.location,
		       t1.name AS home_team, t2.name AS away_team,
		       vg.home_team_id, vg.away_team_id, vg.status,
		       vs.home_team_score, vs.away_team_score
		FROM volleyball_games vg
		JOIN volleyball_teams t1 ON vg.home_team_id = t1.id
		JOIN volleyball_teams t2 ON vg.away_team_id = t2.id
		LEFT JOIN volleyball_scores vs ON vg.id = vs.game_id
		WHERE (vg.home_team_id = $team_id OR vg.away_team_id = $team_id)
		ORDER BY vg.date
	");
}

// Fetch roster (if table exists)
$players = $conn->query("SELECT id, name, position, jersey_number FROM volleyball_players WHERE team_id = $team_id ORDER BY jersey_number, name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volleyball Team Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-size: 0.9rem; }
        .sport-header { background: linear-gradient(135deg, #ff6b6b, #ff8e8e); color: white; }
@media (max-width: 768px) {
    body, p, span, div, td, th { font-size: 2px !important; }
    a { font-size: 2px !important; color: blue !important; text-decoration: underline !important; }
    a:hover { color: red !important; text-decoration: none !important; }
    a:visited { color: purple !important; }
}
    </style>
    
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="sport-header p-4 rounded mb-4 text-center">
            <h1 class="display-5 mb-0">🏐 <?php echo htmlspecialchars($team['name']); ?></h1>
            <?php if ($current_season): ?>
                <p class="mb-0">Season: <?php echo htmlspecialchars($current_season['name']); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($team['coach'])): ?>
                <p class="mb-0">Coach: <?php echo htmlspecialchars($team['coach']); ?></p>
            <?php endif; ?>
        </div>

        <div class="text-center mb-4">
            <div class="mb-3">
                <?php if (!empty($team['team_pic_url'])): ?>
                    <img src="<?php echo htmlspecialchars($team['team_pic_url']); ?>" alt="Team Picture" class="img-fluid team-logo" style="max-width: 300px; max-height: 300px; border-radius: 8px;">
                <?php else: ?>
                    <div class="team-picture-placeholder d-inline-block" style="width: 300px; height: 300px; background-color: #e9ecef; border: 2px dashed #adb5bd; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <div class="text-muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-image" viewBox="0 0 16 16">
                                <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/>
                                <path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/>
                            </svg>
                            <div class="mt-2">No Team Picture</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-4 text-center">
            <a href="volleyball_standings.php" class="btn btn-outline-danger me-2">Standings</a>
            <a href="volleyball_schedule.php" class="btn btn-outline-danger">Schedule</a>
        </div>

        <!-- Roster -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Roster</h5>
                <?php if ($players && $players->num_rows > 0): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Player</th>
                            <th>Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($player = $players->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($player['jersey_number']); ?></td>
                                <td><?php echo htmlspecialchars($player['name']); ?></td>
                                <td><?php echo htmlspecialchars($player['position']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="mb-0 text-muted">No players found.</p>
                <?php endif; ?>
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
                            <th>Result</th>
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
                                        if ($game['home_team_id'] == $team_id) {
                                            $opponent_id = $game['away_team_id'];
                                            $opponent_name = $game['away_team'];
                                        } else {
                                            $opponent_id = $game['home_team_id'];
                                            $opponent_name = $game['home_team'];
                                        }
                                    ?>
                                    <a href="volleyball_team_details.php?team_id=<?php echo $opponent_id; ?>">
                                        <?php echo htmlspecialchars($opponent_name); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                        $result = '';
                                        if ($game['status'] === 'completed' && $game['home_team_score'] !== null && $game['away_team_score'] !== null) {
                                            $home_score = (int) $game['home_team_score'];
                                            $away_score = (int) $game['away_team_score'];

                                            if (($game['home_team_id'] == $team_id && $home_score > $away_score) ||
                                                ($game['away_team_id'] == $team_id && $away_score > $home_score)) {
                                                $result = 'Win';
                                            } elseif (($game['home_team_id'] == $team_id && $home_score < $away_score) ||
                                                      ($game['away_team_id'] == $team_id && $away_score < $home_score)) {
                                                $result = 'Loss';
                                            } else {
                                                $result = 'Tie';
                                            }
                                        } elseif (!empty($game['status'])) {
                                            $result = ucfirst($game['status']);
                                        }

                                        echo htmlspecialchars($result);
                                    ?>
                                </td>
                                <td>
                                    <?php if ($game['status'] === 'completed'): ?>
                                        <?php echo htmlspecialchars($game['home_team_score']); ?> - <?php echo htmlspecialchars($game['away_team_score']); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-center">
            <button class="btn btn-primary" onclick="window.location.href = document.referrer;">Back</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



