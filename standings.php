<?php
include 'admin/db.php';

// Get current season
$current_season = getCurrentSeason($conn);

// Fetch divisions for the dropdown (filtered by current season, excluding divisions with only playoff placeholders)
if ($current_season) {
    $divisions_stmt = $conn->prepare("
        SELECT DISTINCT d.id, d.name 
        FROM divisions d 
        JOIN teams t ON d.id = t.division_id 
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
        FROM divisions d 
        JOIN teams t ON d.id = t.division_id 
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
        FROM divisions d 
        JOIN teams t ON d.id = t.division_id 
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
        FROM divisions d 
        JOIN teams t ON d.id = t.division_id 
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
        $teams_stmt = $conn->prepare("SELECT id, name FROM teams WHERE division_id = ? AND season_id = ? AND is_playoff_placeholder = 0");
        $teams_stmt->bind_param("ii", $division_id, $current_season['id']);
        $teams_stmt->execute();
        $teams = $teams_stmt->get_result();
        $teams_stmt->close();
    } else {
        $teams = $conn->query("SELECT id, name FROM teams WHERE division_id = $division_id AND is_playoff_placeholder = 0");
    }

    while ($team = $teams->fetch_assoc()) {
        $team_id = $team['id'];

        // Calculate wins, losses, and win percentage
        if ($current_season) {
            $wins_stmt = $conn->prepare("
                SELECT COUNT(*) AS wins
                FROM scores s
                JOIN games g ON s.game_id = g.id
                WHERE ((g.home_team_id = ? AND s.home_team_score > s.away_team_score)
                   OR (g.away_team_id = ? AND s.away_team_score > s.home_team_score))
                   AND g.is_playoff = 0 AND g.season_id = ?
            ");
            $wins_stmt->bind_param("iii", $team_id, $team_id, $current_season['id']);
            $wins_stmt->execute();
            $wins = $wins_stmt->get_result()->fetch_assoc()['wins'];
            $wins_stmt->close();

            $losses_stmt = $conn->prepare("
                SELECT COUNT(*) AS losses
                FROM scores s
                JOIN games g ON s.game_id = g.id
                WHERE ((g.home_team_id = ? AND s.home_team_score < s.away_team_score)
                   OR (g.away_team_id = ? AND s.away_team_score < s.home_team_score))
                   AND g.is_playoff = 0 AND g.season_id = ?
            ");
            $losses_stmt->bind_param("iii", $team_id, $team_id, $current_season['id']);
            $losses_stmt->execute();
            $losses = $losses_stmt->get_result()->fetch_assoc()['losses'];
            $losses_stmt->close();
        } else {
            $wins = $conn->query("
                SELECT COUNT(*) AS wins
                FROM scores s
                JOIN games g ON s.game_id = g.id
                WHERE ((g.home_team_id = $team_id AND s.home_team_score > s.away_team_score)
                   OR (g.away_team_id = $team_id AND s.away_team_score > s.home_team_score))
                   AND g.is_playoff = 0
            ")->fetch_assoc()['wins'];

            $losses = $conn->query("
                SELECT COUNT(*) AS losses
                FROM scores s
                JOIN games g ON s.game_id = g.id
                WHERE ((g.home_team_id = $team_id AND s.home_team_score < s.away_team_score)
                   OR (g.away_team_id = $team_id AND s.away_team_score < s.home_team_score))
                   AND g.is_playoff = 0            
            ")->fetch_assoc()['losses'];
        }

        $win_percentage = ($wins + $losses) > 0 ? round($wins / ($wins + $losses), 3) : 0;

        // Calculate points for, points against, and point differential
        if ($current_season) {
            $points_for_stmt = $conn->prepare("
                SELECT SUM(CASE 
                    WHEN g.home_team_id = ? THEN s.home_team_score 
                    WHEN g.away_team_id = ? THEN s.away_team_score 
                    ELSE 0 
                END) AS points_for
                FROM scores s
                JOIN games g ON s.game_id = g.id
                WHERE (g.home_team_id = ? OR g.away_team_id = ?) AND g.season_id = ?
            ");
            $points_for_stmt->bind_param("iiiii", $team_id, $team_id, $team_id, $team_id, $current_season['id']);
            $points_for_stmt->execute();
            $points_for = $points_for_stmt->get_result()->fetch_assoc()['points_for'];
            $points_for_stmt->close();

            $points_against_stmt = $conn->prepare("
                SELECT SUM(CASE 
                    WHEN g.home_team_id = ? THEN s.away_team_score 
                    WHEN g.away_team_id = ? THEN s.home_team_score 
                    ELSE 0 
                END) AS points_against
                FROM scores s
                JOIN games g ON s.game_id = g.id
                WHERE (g.home_team_id = ? OR g.away_team_id = ?) AND g.season_id = ?
            ");
            $points_against_stmt->bind_param("iiiii", $team_id, $team_id, $team_id, $team_id, $current_season['id']);
            $points_against_stmt->execute();
            $points_against = $points_against_stmt->get_result()->fetch_assoc()['points_against'];
            $points_against_stmt->close();
        } else {
            $points_for = $conn->query("
                SELECT SUM(CASE 
                    WHEN g.home_team_id = $team_id THEN s.home_team_score 
                    WHEN g.away_team_id = $team_id THEN s.away_team_score 
                    ELSE 0 
                END) AS points_for
                FROM scores s
                JOIN games g ON s.game_id = g.id
                WHERE (g.home_team_id = $team_id OR g.away_team_id = $team_id)
            ")->fetch_assoc()['points_for'];

            $points_against = $conn->query("
                SELECT SUM(CASE 
                    WHEN g.home_team_id = $team_id THEN s.away_team_score 
                    WHEN g.away_team_id = $team_id THEN s.home_team_score 
                    ELSE 0 
                END) AS points_against
                FROM scores s
                JOIN games g ON s.game_id = g.id
                WHERE (g.home_team_id = $team_id OR g.away_team_id = $team_id)
            ")->fetch_assoc()['points_against'];
        }

        $point_differential = $points_for - $points_against;

        // Fetch next scheduled game
        if ($current_season) {
            $next_game_stmt = $conn->prepare("
                SELECT g.id, g.date, t1.name AS home_team, t2.name AS away_team
                FROM games g
                JOIN teams t1 ON g.home_team_id = t1.id
                JOIN teams t2 ON g.away_team_id = t2.id
                WHERE (g.home_team_id = ? OR g.away_team_id = ?)
                  AND g.status = 'scheduled' AND g.season_id = ?
                ORDER BY g.date
                LIMIT 1
            ");
            $next_game_stmt->bind_param("iii", $team_id, $team_id, $current_season['id']);
            $next_game_stmt->execute();
            $next_game = $next_game_stmt->get_result()->fetch_assoc();
            $next_game_stmt->close();

            $last_game_stmt = $conn->prepare("
                SELECT g.id, g.date, t1.name AS home_team, t2.name AS away_team, s.home_team_score, s.away_team_score
                FROM games g
                JOIN teams t1 ON g.home_team_id = t1.id
                JOIN teams t2 ON g.away_team_id = t2.id
                LEFT JOIN scores s ON g.id = s.game_id
                WHERE (g.home_team_id = ? OR g.away_team_id = ?)
                  AND g.status = 'completed' AND g.season_id = ?
                ORDER BY g.date DESC
                LIMIT 1
            ");
            $last_game_stmt->bind_param("iii", $team_id, $team_id, $current_season['id']);
            $last_game_stmt->execute();
            $last_game = $last_game_stmt->get_result()->fetch_assoc();
            $last_game_stmt->close();
        } else {
            $next_game = $conn->query("
                SELECT g.id, g.date, t1.name AS home_team, t2.name AS away_team
                FROM games g
                JOIN teams t1 ON g.home_team_id = t1.id
                JOIN teams t2 ON g.away_team_id = t2.id
                WHERE (g.home_team_id = $team_id OR g.away_team_id = $team_id)
                  AND g.status = 'scheduled'
                ORDER BY g.date
                LIMIT 1
            ")->fetch_assoc();

            $last_game = $conn->query("
                SELECT g.id, g.date, t1.name AS home_team, t2.name AS away_team, s.home_team_score, s.away_team_score
                FROM games g
                JOIN teams t1 ON g.home_team_id = t1.id
                JOIN teams t2 ON g.away_team_id = t2.id
                LEFT JOIN scores s ON g.id = s.game_id
                WHERE (g.home_team_id = $team_id OR g.away_team_id = $team_id)
                  AND g.status = 'completed'
                ORDER BY g.date DESC
                LIMIT 1
            ")->fetch_assoc();
        }

        $standings[] = [
            'team' => $team,
            'wins' => $wins,
            'losses' => $losses,
            'win_percentage' => $win_percentage,
            'points_for' => $points_for,
            'points_against' => $points_against,
            'point_differential' => $point_differential,
            'next_game' => $next_game,
            'last_game' => $last_game,
        ];
    }

    // Sort standings by win percentage (descending) and then by point differential (descending)
    usort($standings, function ($a, $b) {
        if ($b['win_percentage'] == $a['win_percentage']) {
            return $b['point_differential'] <=> $a['point_differential'];
        }
        return $b['win_percentage'] <=> $a['win_percentage'];
    });

    // Fetch schedule for the division
    if ($current_season) {
        $schedule_stmt = $conn->prepare("
            SELECT g.id, g.date, g.location, t1.id AS home_team_id, t1.name AS home_team, 
            t2.id AS away_team_id, t2.name AS away_team, g.status, s.home_team_score, s.away_team_score
            FROM games g
            JOIN teams t1 ON g.home_team_id = t1.id
            JOIN teams t2 ON g.away_team_id = t2.id
            LEFT JOIN scores s ON g.id = s.game_id
            WHERE (t1.division_id = ? OR t2.division_id = ?) AND g.season_id = ?
            ORDER BY g.date
        ");
        $schedule_stmt->bind_param("iii", $division_id, $division_id, $current_season['id']);
        $schedule_stmt->execute();
        $schedule = $schedule_stmt->get_result();
        $schedule_stmt->close();
    } else {
        $schedule = $conn->query("
            SELECT g.id, g.date, g.location, t1.id AS home_team_id, t1.name AS home_team, 
            t2.id AS away_team_id, t2.name AS away_team, g.status, s.home_team_score, s.away_team_score
            FROM games g
            JOIN teams t1 ON g.home_team_id = t1.id
            JOIN teams t2 ON g.away_team_id = t2.id
            LEFT JOIN scores s ON g.id = s.game_id
            WHERE (t1.division_id = $division_id OR t2.division_id = $division_id)
            ORDER BY g.date
        ");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Division Standings and Schedule</title>
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
            <h1 class="display-4">Division Standings and Schedule</h1>
        </div>

        <!-- Navigation -->
        <div class="mb-4 text-center">
            <a href="schedule.php" class="btn btn-outline-primary me-2">Current Schedule</a>
            <a href="history.php" class="btn btn-outline-secondary me-2">League History</a>
            <a href="stat_leaders.php" class="btn btn-outline-primary">Stat Leaders</a>
        </div>

        <!-- Division Selection Form -->
        <form method="get" action="standings.php" class="mb-4">
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
                                <th>Points For</th>
                                <th>Points Against</th>
                                <th>Point Differential</th>
                                <th>Next Game</th>
                                <th>Last Game</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($standings as $standing): ?>
                                <tr>
                                    <td><a href="team_details.php?team_id=<?php echo $standing['team']['id']; ?>">
                                        <?php echo $standing['team']['name']; ?></a></td>
                                    <td><?php echo $standing['wins']; ?></td>
                                    <td><?php echo $standing['losses']; ?></td>
                                    <td><?php
                                        $win_percentage = $standing['win_percentage'];
                                        echo ltrim(number_format($win_percentage, 3), '0');
                                        ?>
                                        </td>
                                    <td><?php echo $standing['points_for']; ?></td>
                                    <td><?php echo $standing['points_against']; ?></td>
                                    <td><?php echo $standing['point_differential']; ?></td>
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
                                            <a href="game_details.php?game_id=<?php echo $standing['last_game']['id']; ?>">
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
                                    <td><a href="team_details.php?team_id=<?php echo $game['home_team_id']; ?>"><?php echo $game['home_team']; ?></a></td>
                                    <td><a href="team_details.php?team_id=<?php echo $game['away_team_id']; ?>"><?php echo $game['away_team']; ?></a></td>
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
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>