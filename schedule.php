<?php
include 'admin/db.php';

// Fetch all unique divisions
$divisions_query = "SELECT DISTINCT d.id, d.name FROM divisions d JOIN teams t ON d.id = t.division_id";
$divisions_result = $conn->query($divisions_query);
$divisions = [];
while ($row = $divisions_result->fetch_assoc()) {
    $divisions[] = $row;
}

// Fetch schedule data
$query = "
    SELECT g.id, DATE(g.date) AS game_date, TIME(g.date) AS start_time, 
           g.location, 
           t1.name AS home_team, t2.name AS away_team, 
           g.status, s.home_team_score, s.away_team_score, 
           d.name AS division_name, d.id AS division_id,
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
    <style>
        body {
            font-size: 0.9rem; 
        }
        .table th, .table td {
            font-size: 0.85rem; 
        }
        h1, h2 {
            font-size: 1.4rem; 
        }
        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h1 class="mb-3">2025 Spring Basketball League</h1>

        <!-- Filter Dropdown -->
        <div class="filter-container mb-3">
            <label for="divisionFilter" class="fw-bold">Select Division:</label>
            <select id="divisionFilter" class="form-select" style="max-width: 300px;">
                <option value="all">All Divisions</option>
                <?php foreach ($divisions as $division): ?>
                    <option value="<?php echo htmlspecialchars($division['id']); ?>">
                        <?php echo htmlspecialchars($division['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($grouped_schedule)): ?>
            <!-- Schedule Display -->
            <?php foreach ($grouped_schedule as $date => $locations): ?>
                <h2 class="mt-4 text-primary"><?php echo date('l, F j, Y', strtotime($date)); ?></h2> <!-- Display Date Header -->
                <?php foreach ($locations as $location => $games): ?>
                    <h4 class="mt-3 text-secondary">Location: <?php echo htmlspecialchars($location); ?></h4> <!-- Display Location -->
                    <div class="table-responsive"> <!-- Mobile-friendly table -->
                        <table class="table table-bordered table-striped">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Time</th>
                                    <th>Home Team</th>
                                    <th>Score</th>
                                    <th>Division</th>
                                    <th>Score</th>
                                    <th>Visiting Team</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($games as $game): ?>
                                    <tr class="game-row" data-division="<?php echo htmlspecialchars($game['division_id']); ?>">
                                        <td>
                                            <?php if ($game['status'] == 'completed' && isset($game['home_team_score']) && isset($game['away_team_score'])): ?>
                                                <a href="game_details.php?game_id=<?php echo $game['id']; ?>" class="btn btn-link">
                                                    <?php echo date('g:i A', strtotime($game['start_time'])); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo date('g:i A', strtotime($game['start_time'])); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><a href="team_details.php?team_id=<?php echo $game['home_team_id']; ?>" class="btn btn-link"><?php echo htmlspecialchars($game['home_team']); ?></a></td>
                                        <td><?php echo isset($game['home_team_score']) ? $game['home_team_score'] : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($game['division_name']); ?></td>
                                        <td><?php echo isset($game['away_team_score']) ? $game['away_team_score'] : '-'; ?></td>
                                        <td><a href="team_details.php?team_id=<?php echo $game['away_team_id']; ?>" class="btn btn-link"><?php echo htmlspecialchars($game['away_team']); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-danger">No games scheduled.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('divisionFilter').addEventListener('change', function() {
            let selectedDivision = this.value;
            document.querySelectorAll('.game-row').forEach(row => {
                if (selectedDivision === 'all' || row.getAttribute('data-division') === selectedDivision) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
