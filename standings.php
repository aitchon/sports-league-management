<?php
include 'admin/db.php';

// Fetch divisions for the dropdown
$divisions = $conn->query("SELECT id, name FROM divisions where (name <> 'PRACTICE') && (is_playoff=0)");

// Fetch standings and schedule for the selected division
$division_id = isset($_GET['division_id']) ? intval($_GET['division_id']) : null;
$standings = [];
$schedule = [];

if ($division_id) {
    // Fetch teams in the division
    $teams = $conn->query("SELECT id, name FROM teams WHERE division_id = $division_id");

    while ($team = $teams->fetch_assoc()) {
        $team_id = $team['id'];

        // Calculate wins, losses, and win percentage
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

        $win_percentage = ($wins + $losses) > 0 ? round($wins / ($wins + $losses), 3) : 0;

        // Calculate points for, points against, and point differential
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

        $point_differential = $points_for - $points_against;

        // Fetch next scheduled game
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

        // Fetch last completed game
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