<?php
include 'admin/db.php';

// Get current volleyball season
$current_season = null;
$result = $conn->query("SELECT * FROM volleyball_seasons WHERE is_active = 1 LIMIT 1");
if ($result && $result->num_rows > 0) {
    $current_season = $result->fetch_assoc();
}

// Fetch volleyball divisions for the dropdown (filtered by current season, excluding divisions with only playoff placeholders)
if ($current_season) {
    $divisions_stmt = $conn->prepare("
        SELECT DISTINCT d.id, d.name 
        FROM volleyball_divisions d 
        JOIN volleyball_teams t ON d.id = t.division_id 
        WHERE d.season_id = ? AND d.name <> 'PRACTICE' AND d.is_playoff = 0 
        AND t.is_playoff_placeholder = 0 
        ORDER BY d.name
    ");
    $divisions_stmt->bind_param("i", $current_season['id']);
    $divisions_stmt->execute();
    $divisions = $divisions_stmt->get_result();
    $divisions_stmt->close();
} else {
    $divisions = $conn->query("
        SELECT DISTINCT d.id, d.name 
        FROM volleyball_divisions d 
        JOIN volleyball_teams t ON d.id = t.division_id 
        WHERE d.name <> 'PRACTICE' AND d.is_playoff = 0 
        AND t.is_playoff_placeholder = 0 
        ORDER BY d.name
    ");
}

// Check if there's only one division and auto-select it
$division_id = isset($_GET['division_id']) ? intval($_GET['division_id']) : null;

// If no division is selected and there's only one division available, auto-select it
if (!$division_id && $current_season) {
    // Reset the divisions result to count them
    $divisions_stmt = $conn->prepare("
        SELECT DISTINCT d.id, d.name 
        FROM volleyball_divisions d 
        JOIN volleyball_teams t ON d.id = t.division_id 
        WHERE d.season_id = ? AND d.name <> 'PRACTICE' AND d.is_playoff = 0 
        AND t.is_playoff_placeholder = 0 
        ORDER BY d.name
    ");
    $divisions_stmt->bind_param("i", $current_season['id']);
    $divisions_stmt->execute();
    $divisions_result = $divisions_stmt->get_result();
    $divisions_array = [];
    while ($row = $divisions_result->fetch_assoc()) {
        $divisions_array[] = $row;
    }
    $divisions_stmt->close();
    
    // If there's exactly one division, auto-select it
    if (count($divisions_array) == 1) {
        $division_id = $divisions_array[0]['id'];
    }
    
    // Recreate the divisions result for the dropdown
    $divisions_stmt = $conn->prepare("
        SELECT DISTINCT d.id, d.name 
        FROM volleyball_divisions d 
        JOIN volleyball_teams t ON d.id = t.division_id 
        WHERE d.season_id = ? AND d.name <> 'PRACTICE' AND d.is_playoff = 0 
        AND t.is_playoff_placeholder = 0 
        ORDER BY d.name
    ");
    $divisions_stmt->bind_param("i", $current_season['id']);
    $divisions_stmt->execute();
    $divisions = $divisions_stmt->get_result();
    $divisions_stmt->close();
}

$standings = [];
$schedule = [];

if ($division_id) {
    // Fetch teams in the division (filtered by current season, excluding playoff placeholders)
    if ($current_season) {
        $teams_stmt = $conn->prepare("SELECT id, name FROM volleyball_teams WHERE division_id = ? AND season_id = ? AND is_playoff_placeholder = 0");
        $teams_stmt->bind_param("ii", $division_id, $current_season['id']);
        $teams_stmt->execute();
        $teams = $teams_stmt->get_result();
        $teams_stmt->close();
    } else {
        $teams = $conn->query("SELECT id, name FROM volleyball_teams WHERE division_id = $division_id AND is_playoff_placeholder = 0");
    }

    while ($team = $teams->fetch_assoc()) {
        $team_id = $team['id'];

        // Calculate wins, losses, and win percentage for volleyball
        if ($current_season) {
            $wins_stmt = $conn->prepare("
                SELECT COUNT(*) AS wins
                FROM volleyball_scores vs
                JOIN volleyball_games vg ON vs.game_id = vg.id
                WHERE ((vg.home_team_id = ? AND vs.home_team_score > vs.away_team_score)
                   OR (vg.away_team_id = ? AND vs.away_team_score > vs.home_team_score))
                   AND vg.is_playoff = 0 AND vg.season_id = ?
            ");
            $wins_stmt->bind_param("iii", $team_id, $team_id, $current_season['id']);
            $wins_stmt->execute();
            $wins = $wins_stmt->get_result()->fetch_assoc()['wins'];
            $wins_stmt->close();

            $losses_stmt = $conn->prepare("
                SELECT COUNT(*) AS losses
                FROM volleyball_scores vs
                JOIN volleyball_games vg ON vs.game_id = vg.id
                WHERE ((vg.home_team_id = ? AND vs.home_team_score < vs.away_team_score)
                   OR (vg.away_team_id = ? AND vs.away_team_score < vs.home_team_score))
                   AND vg.is_playoff = 0 AND vg.season_id = ?
            ");
            $losses_stmt->bind_param("iii", $team_id, $team_id, $current_season['id']);
            $losses_stmt->execute();
            $losses = $losses_stmt->get_result()->fetch_assoc()['losses'];
            $losses_stmt->close();
        } else {
            $wins = $conn->query("
                SELECT COUNT(*) AS wins
                FROM volleyball_scores vs
                JOIN volleyball_games vg ON vs.game_id = vg.id
                WHERE ((vg.home_team_id = $team_id AND vs.home_team_score > vs.away_team_score)
                   OR (vg.away_team_id = $team_id AND vs.away_team_score > vs.home_team_score))
                   AND vg.is_playoff = 0
            ")->fetch_assoc()['wins'];

            $losses = $conn->query("
                SELECT COUNT(*) AS losses
                FROM volleyball_scores vs
                JOIN volleyball_games vg ON vs.game_id = vg.id
                WHERE ((vg.home_team_id = $team_id AND vs.home_team_score < vs.away_team_score)
                   OR (vg.away_team_id = $team_id AND vs.away_team_score < vs.home_team_score))
                   AND vg.is_playoff = 0            
            ")->fetch_assoc()['losses'];
        }

        $win_percentage = ($wins + $losses) > 0 ? round($wins / ($wins + $losses), 3) : 0;

        // Calculate sets for, sets against, and set differential
        if ($current_season) {
            $sets_for_stmt = $conn->prepare("
                SELECT SUM(CASE 
                    WHEN vg.home_team_id = ? THEN vs.home_team_score 
                    WHEN vg.away_team_id = ? THEN vs.away_team_score 
                    ELSE 0 
                END) AS sets_for
                FROM volleyball_scores vs
                JOIN volleyball_games vg ON vs.game_id = vg.id
                WHERE (vg.home_team_id = ? OR vg.away_team_id = ?) AND vg.season_id = ?
            ");
            $sets_for_stmt->bind_param("iiiii", $team_id, $team_id, $team_id, $team_id, $current_season['id']);
            $sets_for_stmt->execute();
            $sets_for = $sets_for_stmt->get_result()->fetch_assoc()['sets_for'];
            $sets_for_stmt->close();

            $sets_against_stmt = $conn->prepare("
                SELECT SUM(CASE 
                    WHEN vg.home_team_id = ? THEN vs.away_team_score 
                    WHEN vg.away_team_id = ? THEN vs.home_team_score 
                    ELSE 0 
                END) AS sets_against
                FROM volleyball_scores vs
                JOIN volleyball_games vg ON vs.game_id = vg.id
                WHERE (vg.home_team_id = ? OR vg.away_team_id = ?) AND vg.season_id = ?
            ");
            $sets_against_stmt->bind_param("iiiii", $team_id, $team_id, $team_id, $team_id, $current_season['id']);
            $sets_against_stmt->execute();
            $sets_against = $sets_against_stmt->get_result()->fetch_assoc()['sets_against'];
            $sets_against_stmt->close();
        } else {
            $sets_for = $conn->query("
                SELECT SUM(CASE 
                    WHEN vg.home_team_id = $team_id THEN vs.home_team_score 
                    WHEN vg.away_team_id = $team_id THEN vs.away_team_score 
                    ELSE 0 
                END) AS sets_for
                FROM volleyball_scores vs
                JOIN volleyball_games vg ON vs.game_id = vg.id
                WHERE (vg.home_team_id = $team_id OR vg.away_team_id = $team_id)
            ")->fetch_assoc()['sets_for'];

            $sets_against = $conn->query("
                SELECT SUM(CASE 
                    WHEN vg.home_team_id = $team_id THEN vs.away_team_score 
                    WHEN vg.away_team_id = $team_id THEN vs.home_team_score 
                    ELSE 0 
                END) AS sets_against
                FROM volleyball_scores vs
                JOIN volleyball_games vg ON vs.game_id = vg.id
                WHERE (vg.home_team_id = $team_id OR vg.away_team_id = $team_id)
            ")->fetch_assoc()['sets_against'];
        }

        $set_differential = $sets_for - $sets_against;

        // Fetch next scheduled game
        if ($current_season) {
            $next_game_stmt = $conn->prepare("
                SELECT vg.id, vg.date, t1.name AS home_team, t2.name AS away_team
                FROM volleyball_games vg
                JOIN volleyball_teams t1 ON vg.home_team_id = t1.id
                JOIN volleyball_teams t2 ON vg.away_team_id = t2.id
                WHERE (vg.home_team_id = ? OR vg.away_team_id = ?)
                  AND vg.status = 'scheduled' AND vg.season_id = ?
                ORDER BY vg.date
                LIMIT 1
            ");
            $next_game_stmt->bind_param("iii", $team_id, $team_id, $current_season['id']);
            $next_game_stmt->execute();
            $next_game = $next_game_stmt->get_result()->fetch_assoc();
            $next_game_stmt->close();

            $last_game_stmt = $conn->prepare("
                SELECT vg.id, vg.date, t1.name AS home_team, t2.name AS away_team, vs.home_team_score, vs.away_team_score
                FROM volleyball_games vg
                JOIN volleyball_teams t1 ON vg.home_team_id = t1.id
                JOIN volleyball_teams t2 ON vg.away_team_id = t2.id
                LEFT JOIN volleyball_scores vs ON vg.id = vs.game_id
                WHERE (vg.home_team_id = ? OR vg.away_team_id = ?)
                  AND vg.status = 'completed' AND vg.season_id = ?
                ORDER BY vg.date DESC
                LIMIT 1
            ");
            $last_game_stmt->bind_param("iii", $team_id, $team_id, $current_season['id']);
            $last_game_stmt->execute();
            $last_game = $last_game_stmt->get_result()->fetch_assoc();
            $last_game_stmt->close();
        } else {
            $next_game = $conn->query("
                SELECT vg.id, vg.date, t1.name AS home_team, t2.name AS away_team
                FROM volleyball_games vg
                JOIN volleyball_teams t1 ON vg.home_team_id = t1.id
                JOIN volleyball_teams t2 ON vg.away_team_id = t2.id
                WHERE (vg.home_team_id = $team_id OR vg.away_team_id = $team_id)
                  AND vg.status = 'scheduled'
                ORDER BY vg.date
                LIMIT 1
            ")->fetch_assoc();

            $last_game = $conn->query("
                SELECT vg.id, vg.date, t1.name AS home_team, t2.name AS away_team, vs.home_team_score, vs.away_team_score
                FROM volleyball_games vg
                JOIN volleyball_teams t1 ON vg.home_team_id = t1.id
                JOIN volleyball_teams t2 ON vg.away_team_id = t2.id
                LEFT JOIN volleyball_scores vs ON vg.id = vs.game_id
                WHERE (vg.home_team_id = $team_id OR vg.away_team_id = $team_id)
                  AND vg.status = 'completed'
                ORDER BY vg.date DESC
                LIMIT 1
            ")->fetch_assoc();
        }

        $standings[] = [
            'team' => $team,
            'wins' => $wins,
            'losses' => $losses,
            'win_percentage' => $win_percentage,
            'sets_for' => $sets_for,
            'sets_against' => $sets_against,
            'set_differential' => $set_differential,
            'next_game' => $next_game,
            'last_game' => $last_game,
        ];
    }

    // Sort standings by win percentage (descending) and then by set differential (descending)
    usort($standings, function ($a, $b) {
        if ($b['win_percentage'] == $a['win_percentage']) {
            return $b['set_differential'] <=> $a['set_differential'];
        }
        return $b['win_percentage'] <=> $a['win_percentage'];
    });

    // Fetch schedule for the division
    if ($current_season) {
        $schedule_stmt = $conn->prepare("
            SELECT vg.id, vg.date, vg.location, t1.id AS home_team_id, t1.name AS home_team, 
            t2.id AS away_team_id, t2.name AS away_team, vg.status, vs.home_team_score, vs.away_team_score
            FROM volleyball_games vg
            JOIN volleyball_teams t1 ON vg.home_team_id = t1.id
            JOIN volleyball_teams t2 ON vg.away_team_id = t2.id
            LEFT JOIN volleyball_scores vs ON vg.id = vs.game_id
            WHERE (t1.division_id = ? OR t2.division_id = ?) AND vg.season_id = ?
            ORDER BY vg.date
        ");
        $schedule_stmt->bind_param("iii", $division_id, $division_id, $current_season['id']);
        $schedule_stmt->execute();
        $schedule = $schedule_stmt->get_result();
        $schedule_stmt->close();
    } else {
        $schedule = $conn->query("
            SELECT vg.id, vg.date, vg.location, t1.id AS home_team_id, t1.name AS home_team, 
            t2.id AS away_team_id, t2.name AS away_team, vg.status, vs.home_team_score, vs.away_team_score
            FROM volleyball_games vg
            JOIN volleyball_teams t1 ON vg.home_team_id = t1.id
            JOIN volleyball_teams t2 ON vg.away_team_id = t2.id
            LEFT JOIN volleyball_scores vs ON vg.id = vs.game_id
            WHERE (t1.division_id = $division_id OR t2.division_id = $division_id)
            ORDER BY vg.date
        ");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volleyball Division Standings and Schedule</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS for smaller font -->
    <style>
        body {
            font-size: 0.6rem; /* Set default font size */
        }
        .table th, .table td {
            font-size: 0.85rem; /* Smaller font for table headers and data */
        }
        h1, h2 {
            font-size: 1.5rem; /* Smaller font for headings */
        }
        .sport-header {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: white;
        }
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
        <div class="sport-header p-4 rounded mb-4">
            <h1 class="display-4 text-center mb-0">🏐 Volleyball Division Standings and Schedule</h1>
        </div>

        <!-- Navigation -->
        <div class="mb-4 text-center">
            <a href="standings.php" class="btn btn-outline-primary me-2">🏀 Basketball</a>
            <a href="volleyball_schedule.php" class="btn btn-outline-primary me-2">Volleyball Schedule</a>
            <a href="history.php" class="btn btn-outline-secondary me-2">League History</a>
            <a href="volleyball_stat_leaders.php" class="btn btn-outline-primary">Stat Leaders</a>
        </div>

        <!-- Division Selection Form -->
        <form method="get" action="volleyball_standings.php" class="mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="division_id" class="col-form-label">Select Division:</label>
                </div>
                <div class="col-auto">
                    <select id="division_id" name="division_id" class="form-select" required onchange="this.form.submit()">
                        <option value="">-- Select Division --</option>
                        <?php while ($division = $divisions->fetch_assoc()): ?>
                            <option value="<?php echo $division['id']; ?>" <?php echo ($division_id == $division['id']) ? 'selected' : ''; ?>>
                                <?php echo $division['name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </form>

        <?php if ($division_id): ?>
            <!-- Standings Table -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title">Standings</h2>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Team</th>
                                <th>Wins</th>
                                <th>Losses</th>
                                <th>Win %</th>
                                <th>Sets For</th>
                                <th>Sets Against</th>
                                <th>Set Differential</th>
                                <th>Next Game</th>
                                <th>Last Game</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($standings as $standing): ?>
                                <tr>
                                    <td><a href="volleyball_team_details.php?team_id=<?php echo $standing['team']['id']; ?>">
                                        <?php echo $standing['team']['name']; ?></a></td>
                                    <td><?php echo $standing['wins']; ?></td>
                                    <td><?php echo $standing['losses']; ?></td>
                                    <td><?php
                                        $win_percentage = $standing['win_percentage'];
                                        echo ltrim(number_format($win_percentage, 3), '0');
                                        ?>
                                        </td>
                                    <td><?php echo $standing['sets_for']; ?></td>
                                    <td><?php echo $standing['sets_against']; ?></td>
                                    <td><?php echo $standing['set_differential']; ?></td>
                                    <td>
                                        <?php if ($standing['next_game']): ?>
                                            <?php
                                            $date = new DateTime($standing['next_game']['date']);
                                            echo $date->format('m-d');
                                            ?>:
                                            <?php echo $standing['next_game']['home_team']; ?> vs <?php echo $standing['next_game']['away_team']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($standing['last_game']): ?>
                                            <a href="volleyball_game_details.php?game_id=<?php echo $standing['last_game']['id']; ?>">
                                            <?php
                                            $date = new DateTime($standing['last_game']['date']);
                                            echo $date->format('m-d');
                                            ?>:
                                                <?php echo $standing['last_game']['home_team']; ?> <?php echo $standing['last_game']['home_team_score']; ?> -
                                                <?php echo $standing['last_game']['away_team_score']; ?> <?php echo $standing['last_game']['away_team']; ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Schedule Table -->
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title">Schedule</h2>
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
                            <?php while ($game = $schedule->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M j g:i A', strtotime($game['date'])); ?></td>
                                    <td><?php echo $game['location']; ?></td>
                                    <td><a href="volleyball_team_details.php?team_id=<?php echo $game['home_team_id']; ?>"><?php echo $game['home_team']; ?></a></td>
                                    <td><a href="volleyball_team_details.php?team_id=<?php echo $game['away_team_id']; ?>"><?php echo $game['away_team']; ?></a></td>
                                    <td>
                                        <?php if ($game['status'] == 'completed'): ?>
                                            <a href="volleyball_game_details.php?game_id=<?php echo $game['id']; ?>">
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
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
