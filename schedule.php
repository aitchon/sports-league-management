<?php
include 'admin/db.php';

// Fetch schedule for all divisions, ordered by date, location, and start time
$query = "
    SELECT g.id, DATE(g.date) AS game_date, TIME(g.date) AS start_time, 
           g.location, 
           t1.name AS home_team, t2.name AS away_team, 
           g.status, s.home_team_score, s.away_team_score, 
           d.name AS division_name,
           t1.id as home_team_id, t2.id as away_team_id
    FROM games g
    JOIN teams t1 ON g.home_team_id = t1.id
    JOIN teams t2 ON g.away_team_id = t2.id
    LEFT JOIN scores s ON g.id = s.game_id
    JOIN divisions d ON t1.division_id = d.id OR t2.division_id = d.id
    ORDER BY game_date, g.location, start_time
";

$schedule = $conn->query($query);

// Organize data by date and location
$grouped_schedule = [];
if ($schedule) {
    while ($game = $schedule->fetch_assoc()) {
        $date = $game['game_date'];
        $location = $game['location'];
        $grouped_schedule[$date][$location][] = $game;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS for smaller font -->
    <style>
        body {
            font-size: 0.9rem; /* Set default font size */
        }
        .table th, .table td {
            font-size: 0.85rem; /* Smaller font for table headers and data */
        }
        h1, h2 {
            font-size: 1.5rem; /* Smaller font for headings */
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h1 class="mb-4">2025 Spring Basketball League</h1>

        <?php if (!empty($grouped_schedule)): ?>
            <!-- Schedule Display -->
            <?php foreach ($grouped_schedule as $date => $locations): ?>
                <h2 class="mt-4 text-primary"><?php echo date('l, F j, Y', strtotime($date)); ?></h2> <!-- Display Date Header -->
                <?php foreach ($locations as $location => $games): ?>
                    <h4 class="mt-3 text-secondary">Location: <?php echo $location; ?></h4> <!-- Display Location -->
                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Time</th>
                                <th>Home Team</th>
                                <th>Home Score</th>
                                <th>Division Name</th> <!-- Division Name Column -->
                                <th>Visiting Score</th>
                                <th>Visiting Team</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $game): ?>
                                <tr>
                                    <td>
                                        <?php if ($game['status'] == 'completed' && isset($game['home_team_score']) && isset($game['away_team_score'])): ?>
                                            <!-- Make Time Linkable Only if Game is Completed -->
                                            <a href="game_details.php?game_id=<?php echo $game['id']; ?>" class="btn btn-link">
                                                <?php echo date('g:i A', strtotime($game['start_time'])); ?>
                                            </a>
                                        <?php else: ?>
                                            <!-- Display Time as Plain Text if Game Not Completed -->
                                            <?php echo date('g:i A', strtotime($game['start_time'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="team_details.php?team_id=<?php echo $game['home_team_id']; ?>" class="btn btn-link"><?php echo $game['home_team']; ?></a></td>
                                    <td><?php echo isset($game['home_team_score']) ? $game['home_team_score'] : '-'; ?></td>
                                    <td><?php echo $game['division_name']; ?></td> <!-- Division Name Column -->
                                    <td><?php echo isset($game['away_team_score']) ? $game['away_team_score'] : '-'; ?></td>
                                    <td><a href="team_details.php?team_id=<?php echo $game['away_team_id']; ?>" class="btn btn-link"><?php echo $game['away_team']; ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-danger">No games scheduled.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
